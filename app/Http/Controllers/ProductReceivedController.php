<?php

namespace App\Http\Controllers;

use App\Models\FundTransaction;
use App\Models\Reorder;
use App\Models\ReorderDetail;
use App\Models\Product;
use App\Models\ProductReceived;
use App\Models\ProductReceivedDetail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Illuminate\Support\Facades\Validator;

class ProductReceivedController extends Controller
{
    public function index(Request $request)
    {
        try {
            $page = $request->get('page', 1);
            $limit = $request->get('limit', 10);
            $sort_column = $request->get('sort_column', 'received_date');
            $sort_type = $request->get('sort_type', 'desc');
            $search = $request->get('search', '');
            $search_column = $request->get('search_column', 'received_date');

            // Mulai dengan query builder, JANGAN gunakan get() di sini
            $query = ProductReceived::with('details.product');

            // Terapkan pencarian
            if ($search_column && $search) {
                $query->where($search_column, 'like', '%' . $search . '%');
            } else if ($search) {
                $query->where('received_date', 'like', '%' . $search . '%');
            }

            // Terapkan sorting dan pagination
            $productReceived = $query
                ->orderBy($sort_column, $sort_type)
                ->paginate($limit, ['*'], 'page', $page);

            return response()->json([
                'status' => 'success',
                'message' => 'Product received fetched successfully',
                'data' => $productReceived,
            ], 200);

        } catch (\Exception $e) {
            // Tambahkan logging untuk debugging
            \Log::error('Product Received Index Error: ' . $e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Internal server error: ' . $e->getMessage(), // Sementara untuk debugging
            ], 500);
        }
    }

    public function store(Request $request)
    {
        try {
            // 1. Ambil Reorder dan detailnya
            $reorderId = $request->input('reorder_id');
            $reorder = Reorder::with('items.product')->findOrFail($reorderId);
            $reorderDetails = ReorderDetail::with('product')
                ->where('reorder_id', $reorder->id)
                ->get();

            // 2. Validation rules + custom closure untuk cek jumlah
            $rules = [
                'reorder_id' => 'required|uuid|exists:reorders,id',
                'received_date' => 'required|date_format:d-m-Y',
                'products' => 'required|array',
                'products.*.product_id' => 'required|uuid|exists:products,id',
                'products.*.received_quantity' => [
                    'required',
                    'integer',
                    function ($attribute, $value, $fail) use ($reorderDetails, $request) {
                        if (preg_match('/products\.(\d+)\.received_quantity/', $attribute, $m)) {
                            $idx = (int) $m[1];
                            $item = data_get($request->products, $idx);
                            $detail = $reorderDetails->firstWhere('product_id', $item['product_id']);

                            if ($value < 0) {
                                $fail("Jumlah diterima untuk {$detail->product->name} minimal 0.");
                                return;
                            }
                            if ($detail && $value > $detail->reorder_quantity) {
                                $fail("Jumlah diterima untuk {$detail->product->name} tidak boleh lebih dari jumlah dipesan {$detail->reorder_quantity}.");
                            }
                        }
                    }
                ],
                'products.*.price' => 'required|integer|min:0',
            ];

            $messages = [
                'reorder_id.required' => 'ID pengadaan barang harus diisi.',
                'received_date.required' => 'Tanggal diterima harus diisi.',
                'products.required' => 'Produk yang diterima harus diisi.',
                'products.*.product_id.required' => 'ID produk harus diisi.',
                'products.*.received_quantity.required' => 'Jumlah diterima harus diisi.',
                'products.*.price.required' => 'Harga produk harus diisi.',
            ];

            $validator = Validator::make($request->all(), $rules, $messages);
            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation errors',
                    'errors' => $validator->errors()->messages(),
                ], 422);
            }

            $validated = $validator->validated();

            // 3. Business checks
            if ($reorder->reorder_status !== 'proses') {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Pengadaan harus berstatus proses.'
                ], 422);
            }
            if (!in_array($reorder->whatsapp_status, ['sudah_dikirim', 'update_sudah_dikirim'])) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Status WhatsApp tidak valid untuk menerima produk.'
                ], 422);
            }

            $receivedDate = Carbon::createFromFormat('d-m-Y', $validated['received_date']);
            if ($receivedDate->lt($reorder->reorder_date)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Tanggal diterima tidak boleh lebih kecil dari tanggal permintaan.'
                ], 422);
            }

            // 4. Cegah duplicate receive
            if (ProductReceived::where('reorder_id', $reorder->id)->exists()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Data penerimaan produk untuk pengadaan ini sudah ada'
                ], 422);
            }

            // 5. Simpan header & detail (tanpa ubah stok)
            DB::beginTransaction();

            $allZero = collect($validated['products'])->every(fn($p) => $p['received_quantity'] === 0);
            $initialStatus = $allZero ? 'barang_tidak_tersedia' : 'pending';

            $productReceived = ProductReceived::create([
                'id' => (string) Str::uuid(),
                'reorder_id' => $reorder->id,
                'received_date' => $receivedDate->format('Y-m-d'),
                'total_received_price' => 0,
                'received_status' => $initialStatus,
            ]);

            $totalPrice = 0;
            $totalQty = 0;

            foreach ($validated['products'] as $prod) {
                $lineTotal = $prod['received_quantity'] * $prod['price'];

                ProductReceivedDetail::create([
                    'id' => (string) Str::uuid(),
                    'product_received_id' => $productReceived->id,
                    'product_id' => $prod['product_id'],
                    'received_quantity' => $prod['received_quantity'],
                    'price' => $prod['received_quantity'] ? $prod['price'] : 0,
                    'total_product_price' => $lineTotal,
                ]);

                $totalPrice += $lineTotal;
                $totalQty += $prod['received_quantity'];
            }

            // Update total_received_price
            $productReceived->update(['total_received_price' => $totalPrice]);

            // Buat FundTransaction nanti di complete()

            // Update status di Reorder
            $reorder->update([
                'received_status' => $totalQty ? 'pending' : 'barang_tidak_tersedia'
            ]);

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Data penerimaan produk berhasil disimpan.',
                'data' => $productReceived->load('details.product'),
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error menyimpan product received: ' . $e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Terjadi error: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function show($id)
    {
        try {
            $productReceived = ProductReceived::with('reorder', 'details.product')
                ->findOrFail($id);

            return response()->json([
                'status' => 'success',
                'message' => 'Product received fetched successfully',
                'data' => $productReceived,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Internal server error',
            ], 500);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            // 1. Load ProductReceived beserta details & reorder
            $productReceived = ProductReceived::with('details', 'reorder')->findOrFail($id);
            $reorder = $productReceived->reorder;

            // 2. Hanya bisa update saat masih pending
            if ($productReceived->received_status !== 'pending') {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Hanya data penerimaan dengan status pending yang bisa diperbarui'
                ], 422);
            }

            // 3. Ambil detail Reorder untuk validasi
            $reorderDetails = ReorderDetail::with('product')
                ->where('reorder_id', $reorder->id)
                ->get();

            // 4. Validation rules + custom closure
            $rules = [
                'received_date' => 'required|date_format:d-m-Y',
                'products' => 'required|array',
                'products.*.product_id' => 'required|uuid|exists:products,id',
                'products.*.received_quantity' => [
                    'required',
                    'integer',
                    function ($attribute, $value, $fail) use ($reorderDetails, $request) {
                        if (preg_match('/products\.(\d+)\.received_quantity/', $attribute, $m)) {
                            $idx = (int) $m[1];
                            $item = data_get($request->products, $idx);
                            $detail = $reorderDetails->firstWhere('product_id', $item['product_id']);

                            if ($value < 0) {
                                $fail("Jumlah diterima untuk {$detail->product->name} minimal 0.");
                                return;
                            }
                            if ($detail && $value > $detail->reorder_quantity) {
                                $fail("Jumlah diterima untuk {$detail->product->name} tidak boleh lebih dari jumlah dipesan {$detail->reorder_quantity}.");
                            }
                        }
                    }
                ],
                'products.*.price' => 'required|integer|min:0',
            ];

            $messages = [
                'received_date.required' => 'Tanggal diterima harus diisi.',
                'products.required' => 'Produk yang diterima harus diisi.',
                'products.*.product_id.required' => 'ID produk harus diisi.',
                'products.*.received_quantity.required' => 'Jumlah diterima harus diisi.',
                'products.*.price.required' => 'Harga produk harus diisi.',
            ];

            $validator = Validator::make($request->all(), $rules, $messages);
            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation errors',
                    'errors' => $validator->errors()->messages(),
                ], 422);
            }

            $validated = $validator->validated();

            // 5. Business rule: tanggal tidak boleh sebelum reorder_date
            $newDate = Carbon::createFromFormat('d-m-Y', $validated['received_date']);
            if ($newDate->lt($reorder->reorder_date)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Tanggal diterima tidak boleh lebih kecil dari tanggal permintaan.'
                ], 422);
            }

            // 6. Transactional update header & detail
            DB::beginTransaction();

            // Siapkan map existing & incoming
            $existing = $productReceived->details->keyBy('product_id');
            $incoming = collect($validated['products'])->keyBy('product_id');
            $totalPrice = 0;
            $totalQty = 0;

            // 6a. Update atau buat detail baru
            foreach ($incoming as $pid => $item) {
                $qty = $item['received_quantity'];
                $price = $qty > 0 ? $item['price'] : 0;
                $line = $qty * $price;

                if ($existing->has($pid)) {
                    $existing[$pid]->update([
                        'received_quantity' => $qty,
                        'price' => $price,
                        'total_product_price' => $line,
                    ]);
                } else {
                    ProductReceivedDetail::create([
                        'id' => (string) Str::uuid(),
                        'product_received_id' => $productReceived->id,
                        'product_id' => $pid,
                        'received_quantity' => $qty,
                        'price' => $price,
                        'total_product_price' => $line,
                    ]);
                }

                $totalPrice += $line;
                $totalQty += $qty;
            }

            // 6b. Hapus detail yang sudah dihilangkan
            $toDelete = $existing->keys()->diff($incoming->keys());
            if ($toDelete->isNotEmpty()) {
                ProductReceivedDetail::where('product_received_id', $productReceived->id)
                    ->whereIn('product_id', $toDelete)
                    ->delete();
            }

            // 6c. Update header
            $productReceived->update([
                'received_date' => $newDate->format('Y-m-d'),
                'total_received_price' => $totalPrice,
                'received_status' => $totalQty > 0 ? 'pending' : 'diretur',
            ]);

            // 6d. Update received_status di Reorder
            $reorder->update([
                'received_status' => $totalQty ? 'pending' : 'barang_tidak_tersedia',
            ]);

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Data penerimaan berhasil diperbarui.',
                'data' => $productReceived->load('details.product'),
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error updating product received: ' . $e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Terjadi error: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function complete(string $id)
    {
        try {
            // 1. Ambil ProductReceived beserta details & reorder
            $productReceived = ProductReceived::with('details', 'reorder')->findOrFail($id);

            // 2. Hanya bisa complete jika masih pending
            if ($productReceived->received_status !== 'pending') {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Hanya data penerimaan dengan status pending yang bisa diselesaikan.'
                ], 422);
            }

            DB::beginTransaction();

            // 3. Tambah stok per produk sesuai received_quantity
            foreach ($productReceived->details as $detail) {
                if ($detail->received_quantity > 0) {
                    Product::find($detail->product_id)
                        ->increment('stock', $detail->received_quantity);
                }
            }

            // 4. Buat FundTransaction (dana keluar) sekali, berdasarkan total_received_price
            FundTransaction::create([
                'id' => (string) Str::uuid(),
                'date' => now(),
                'type' => 'out',
                'amount' => $productReceived->total_received_price,
                'product_received_id' => $productReceived->id,
            ]);

            // 5. Update status penerimaan menjadi 'selesai'
            $productReceived->update([
                'received_status' => 'selesai',
            ]);

            // 6. Update reorder_status & whatsapp_status di Reorder
            if ($reorder = $productReceived->reorder) {
                $reorder->update([
                    'reorder_status' => 'selesai',
                    'whatsapp_status' => 'selesai',
                ]);
            }

            DB::commit();

            // 7. Reload relations untuk respons
            $productReceived->load('details.product', 'reorder');

            return response()->json([
                'status' => 'success',
                'message' => 'Proses penerimaan selesai: stok, dana keluar, dan status sudah diperbarui.',
                'data' => $productReceived,
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error completing product received: ' . $e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Terjadi error saat menyelesaikan penerimaan: ' . $e->getMessage(),
            ], 500);
        }
    }
}
