<?php

namespace App\Http\Controllers;

use App\Models\CheckoutDetail;
use App\Models\Product;
use App\Models\Checkout;
use App\Models\CheckoutItem;
use App\Models\Reorder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;
use App\Models\CheckoutCart;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\Mail\CheckoutMail;
use Illuminate\Support\Facades\Mail;


class CheckoutController extends Controller
{
    public function index(Request $request)
{
    try {
        $page       = $request->get('page', 1);
        $limit      = $request->get('limit', 10);
        $sortColumn = $request->get('sort_column', 'checkout_date');
        $sortType   = $request->get('sort_type', 'desc');
        $search     = $request->get('search', '');

        $query = Checkout::query()->with('items.product');

        if ($search) {
            $query->where('initial', 'like', '%' . $search . '%');
        }

        $paginator = $query
            ->orderBy($sortColumn, $sortType)
            ->paginate($limit, ['*'], 'page', $page);

        // Mengambil data lengkap pagination termasuk URLs
        $responseData = $paginator->toArray();

        return response()->json([
            'status'  => 'success',
            'message' => 'Checkouts fetched successfully',
            'data'    => $responseData,
        ], 200);
    } catch (\Exception $e) {
        return response()->json([
            'status'  => 'error',
            'message' => 'Internal server error',
        ], 500);
    }
}


    public function store(Request $request)
    {
        // Validasi input
        $validator = Validator::make(
            $request->all(),
            [
                'user_id' => [
                    'required',
                    Rule::exists('users', 'id')->where(function ($query) {
                        $query->where('role', 'Staff');
                    }),
                ],
                'purpose_id' => ['required', Rule::exists('purposes', 'id')],
                'description' => ['nullable', 'string', 'max:2000'],
                'checkout_date' => ['nullable', 'date', 'after_or_equal:today'],
            ],
            [
                'user_id.required' => 'Inisial wajib diisi',
                'user_id.exists' => 'Inisial tidak ditemukan atau bukan Staff',
                'purpose_id.required' => 'Kebutuhan wajib diisi',
                'purpose_id.exists' => 'Kebutuhan tidak ditemukan',
                'description.max' => 'Deskripsi tidak boleh lebih dari 2000 karakter',
                'checkout_date.required' => 'Tanggal pengambilan wajib diisi',
                'checkout_date.date' => 'Format tanggal pengambilan tidak valid',
                'checkout_date.after_or_equal' => 'Tanggal pengambilan tidak boleh sebelum hari ini',
            ]
        );

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Ambil keranjang
        $checkoutCart = CheckoutCart::with('product')->get();
        if ($checkoutCart->isEmpty()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Daftar ATK kosong',
            ], 400);
        }

        DB::beginTransaction();
        try {
            // Cek ketersediaan stok
            foreach ($checkoutCart as $item) {
                if ($item->product->stock < $item->checkout_quantity) {
                    throw new \Exception('Product ' . $item->product->name . ' habis.');
                }
            }

            // Buat checkout (UUID otomatis di model)
            $checkout = Checkout::create([
                'user_id' => $request->user_id,
                'purpose_id' => $request->purpose_id,
                'description' => $request->description,
                'checkout_date' => $request->checkout_date
                    ? Carbon::parse($request->checkout_date)->setTimezone('Asia/Jakarta')
                    : Carbon::now()->setTimezone('Asia/Jakarta'),
            ]);

            // Buat detail checkout dan kurangi stok
            foreach ($checkoutCart as $item) {
                CheckoutDetail::create([
                    'checkout_id' => $checkout->id,
                    'product_id' => $item->product_id,
                    'checkout_quantity' => $item->checkout_quantity,
                ]);
                $item->product->decrement('stock', $item->checkout_quantity);
            }

            // Kosongkan keranjang
            CheckoutCart::query()->delete();

            DB::commit();

            // Load relasi dengan nama items
            $checkout->load('purpose', 'user', 'items.product');

            // Siapkan data items
            $items = $checkout->items->map(function ($detail) {
                return [
                    'product_id' => $detail->product_id,
                    'product_name' => $detail->product->name,
                    'checkout_quantity' => $detail->checkout_quantity,
                ];
            });

            Mail::to($checkout->user->email)
                ->send(new CheckoutMail($checkout));

            return response()->json([
                'status' => 'success',
                'message' => 'Pengambilan ATK berhasil dan email terkirim',
                'data' => [
                    'checkout_id' => $checkout->id,
                    'checkout_date' => $checkout->checkout_date->toDateTimeString(),
                    'purpose_name' => $checkout->purpose->name,
                    'user' => [
                        'name' => $checkout->user->name,
                        'role' => $checkout->user->role,
                    ],
                    'description' => $checkout->description,
                    'items' => $items,
                ],
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error during checkout: ' . $e->getMessage(), ['exception' => $e]);

            return response()->json([
                'status' => 'error',
                'message' => 'Internal server error',
                'error' => $e->getMessage(),
            ], 500);
        }
    }


    public function show($id)
    {
        try {
            $checkout = Checkout::with('items.product')->findOrFail($id);

            return response()->json([
                'status' => 'success',
                'message' => 'Checkout fetched successfully',
                'data' => $checkout,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Checkout not found',
            ], 404);
        }
    }

    public function update(Request $request, $id)
    {
        // Ambil header checkout awal beserta items
        $checkout = Checkout::with('items')->findOrFail($id);
        $minDate = Carbon::parse($checkout->checkout_date)->toDateString();

        // 1) Validasi parsial
        $validator = Validator::make(
            $request->all(),
            [
                // 'user_id' => [
                //     'sometimes',
                //     'required',
                //     Rule::exists('users', 'id')
                //         ->where(fn($q) => $q->where('role', 'Staff'))
                // ],
                'purpose_id' => ['sometimes', 'required', Rule::exists('purposes', 'id')],
                'description' => ['sometimes', 'nullable', 'string', 'max:2000'],
                // 'checkout_date' => ['sometimes', 'required', 'date', 'after_or_equal:' . $minDate],
                'details' => ['sometimes', 'array'],
                'details.*.product_id' => ['required_with:details', Rule::exists('products', 'id')],
                'details.*.checkout_quantity' => ['required_with:details', 'integer', 'min:1'],
            ],
            [
                // 'user_id.required' => 'Inisial wajib diisi',
                // 'user_id.exists' => 'Inisial tidak ditemukan atau bukan Staff',
                'purpose_id.required' => 'Kebutuhan wajib diisi',
                'purpose_id.exists' => 'Kebutuhan tidak ditemukan',
                'description.max' => 'Deskripsi tidak boleh lebih dari 2000 karakter',
                // 'checkout_date.required' => 'Tanggal pengambilan wajib diisi',
                // 'checkout_date.date' => 'Format tanggal pengambilan tidak valid',
                // 'checkout_date.after_or_equal' => 'Tanggal pengambilan harus sama atau setelah ' . $minDate,
                // 'details.required' => 'Daftar detail wajib diisi',
                'details.array' => 'Format daftar detail tidak valid',
                'details.*.product_id.required_with' => 'Produk wajib dipilih',
                'details.*.product_id.exists' => 'Produk tidak ditemukan',
                'details.*.checkout_quantity.required_with' => 'Jumlah pengambilan wajib diisi',
                'details.*.checkout_quantity.integer' => 'Jumlah pengambilan harus berupa angka',
                'details.*.checkout_quantity.min' => 'Jumlah pengambilan minimal 1',
            ]
        );

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        DB::beginTransaction();

        try {
            // Refresh data
            $checkout = Checkout::with('items')->findOrFail($id);

            // 2) Update header jika ada
            $dataToUpdate = [];
            // if ($request->has('user_id')) {
            //     $dataToUpdate['user_id'] = $request->user_id;
            // }
            if ($request->has('purpose_id')) {
                $dataToUpdate['purpose_id'] = $request->purpose_id;
            }
            if ($request->has('description')) {
                $dataToUpdate['description'] = $request->description;
            }
            // if ($request->has('checkout_date')) {
            //     $dataToUpdate['checkout_date'] = Carbon::parse($request->checkout_date)
            //         ->setTimezone('Asia/Jakarta');
            // }
            if (!empty($dataToUpdate)) {
                $checkout->update($dataToUpdate);
            }

            // 3) Proses detail & stok hanya bila ada details
            if ($request->has('details')) {
                $existing = $checkout->items->keyBy('product_id');
                $incoming = collect($request->details)->keyBy('product_id');

                // 3a) Cek tidak boleh ada produk baru
                $newIds = $incoming->keys()->diff($existing->keys());
                if ($newIds->isNotEmpty()) {
                    $productNames = Product::whereIn('id', $newIds->toArray())
                        ->pluck('name')
                        ->toArray();
                    DB::rollBack();
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Tidak boleh menambahkan produk baru saat update',
                        'errors' => [
                            'details' => ['Produk baru tidak diizinkan: ' . implode(', ', $productNames)]
                        ],
                    ], 422);
                }

                // 3b) Loop pertama: cek semua stok, kumpulkan error
                $stockErrors = [];
                foreach ($request->details as $det) {
                    $prod = Product::findOrFail($det['product_id']);
                    $newQty = (int) $det['checkout_quantity'];
                    $oldQty = $existing[$prod->id]->checkout_quantity;
                    $diff = $newQty - $oldQty;
                    $maxQty = $prod->stock + $oldQty;

                    if ($diff > 0 && $prod->stock < $diff) {
                        $stockErrors[] = "Produk {$prod->name}: Maksimal bisa diambil {$maxQty}, tetapi jumlah pengambilan {$newQty}.";
                    }
                }

                // Jika ada error stok, abort semua
                if (!empty($stockErrors)) {
                    DB::rollBack();
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Stok tidak cukup untuk beberapa produk',
                        'errors' => [
                            'details' => $stockErrors
                        ],
                    ], 422);
                }

                // 3c) Loop kedua: stok cukup, lakukan decrement & update detail
                foreach ($request->details as $det) {
                    $prod = Product::findOrFail($det['product_id']);
                    $newQty = (int) $det['checkout_quantity'];
                    $oldQty = $existing[$prod->id]->checkout_quantity;
                    $diff = $newQty - $oldQty;

                    if ($diff > 0) {
                        $prod->decrement('stock', $diff);
                    } else if ($diff < 0) {
                        $prod->increment('stock', -$diff);
                    }

                    CheckoutDetail::updateOrCreate(
                        ['checkout_id' => $checkout->id, 'product_id' => $prod->id],
                        ['checkout_quantity' => $newQty]
                    );
                }

                // 3d) Hapus detail yang di-remove & rollback stok
                $toRemove = $existing->diffKeys($incoming);
                foreach ($toRemove as $old) {
                    $prod = Product::findOrFail($old->product_id);
                    $prod->increment('stock', $old->checkout_quantity);
                    $old->delete();
                }

                $checkout->load('items');
                if ($checkout->items->isEmpty()) {
                    // Jika tidak ada item, hapus checkout
                    $checkout->delete();

                    DB::commit();
                    return response()->json([
                        'status' => 'success',
                        'message' => 'Data pengambilan dihapus karena tidak ada produk yang diambil',
                    ], 200);
                }
            }

            DB::commit();

            // 4) Susun dan kirim response sukses
            $checkout->load('purpose', 'user', 'items.product');
            $items = $checkout->items->map(fn($d) => [
                'product_id' => $d->product_id,
                'product_name' => $d->product->name,
                'checkout_quantity' => $d->checkout_quantity,
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Data pengambilan ATK berhasil diperbarui',
                'data' => [
                    'checkout_id' => $checkout->id,
                    'checkout_date' => $checkout->checkout_date,
                    'purpose_name' => $checkout->purpose->name,
                    'user' => [
                        'name' => $checkout->user->name,
                        'role' => $checkout->user->role,
                    ],
                    'description' => $checkout->description,
                    'items' => $items,
                ],
            ], 200);

        } catch (ModelNotFoundException $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Checkout tidak ditemukan',
            ], 404);

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Error updating checkout: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Internal server error',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function destroy($id)
    {
        DB::beginTransaction();

        try {
            $checkout = Checkout::findOrFail($id);

            foreach ($checkout->items as $item) {
                $item->product->increment('stock', $item->checkout_quantity);
            }

            $checkout->delete();

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Data pengambilan ATK berhasil dihapus',
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'status' => 'error',
                'message' => 'Internal server error',
            ], 500);
        }
    }
}
