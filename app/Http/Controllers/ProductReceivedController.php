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
            // 1. Basic validation + custom closure for quantity check
            $reorderId = $request->input('reorder_id');
            $reorder = Reorder::with('items.product')->findOrFail($reorderId);
            $reorderDetails = ReorderDetail::with('product')
                ->where('reorder_id', $reorder->id)
                ->get();

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

                            // cek minimal 0
                            if ($value < 0) {
                                $fail("Jumlah diterima untuk {$detail->product->name} minimal 0.");
                                return;
                            }

                            // cek tidak melebihi jumlah yang dipesan
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

            // 2. Business checks
            $validated = $validator->validated();
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

            // 3. Prevent duplicate receives
            $exists = ProductReceived::where('reorder_id', $reorder->id)
                ->exists();
            if ($exists) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Data penerimaan produk untuk pengadaan ini sudah ada'
                ], 422);
            }

            // 4. Transactional store
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

                Product::find($prod['product_id'])->increment('stock', $prod['received_quantity']);
                $totalPrice += $lineTotal;
                $totalQty += $prod['received_quantity'];
            }

            $productReceived->update(['total_received_price' => $totalPrice]);

            if ($totalPrice > 0) {
                FundTransaction::create([
                    'id' => (string) Str::uuid(),
                    'date' => now(),
                    'type' => 'out',
                    'amount' => $totalPrice,
                    'product_received_id' => $productReceived->id,
                ]);
            }

            $reorder->update(['received_status' => ($totalQty ? 'pending' : 'barang_tidak_tersedia')]);

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Data Penerimaan produk berhasil disimpan.',
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
            // 1. Load Reorder and its details
            $productReceived = ProductReceived::with('details', 'reorder')->findOrFail($id);
            $reorder = $productReceived->reorder;
            $reorderDetails = ReorderDetail::with('product')
                ->where('reorder_id', $reorder->id)
                ->get();

            if ($productReceived->received_status !== 'pending') {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Hanya data penerimaan dengan status pending yang bisa diperbarui'
                ], 422);
            }

            // 2. Validation rules with custom closure for quantity
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

                            // cek minimal 0
                            if ($value < 0) {
                                $fail("Jumlah diterima untuk {$detail->product->name} minimal 0.");
                                return;
                            }

                            // cek tidak melebihi jumlah yang dipesan
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

            $newDate = Carbon::createFromFormat('d-m-Y', $request->received_date);
            if ($newDate->lt($reorder->reorder_date)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Tanggal diterima tidak boleh lebih kecil dari tanggal permintaan.'
                ], 422);
            }

            // 4. Update process in transaction
            DB::beginTransaction();

            // rollback old stock
            foreach ($productReceived->details as $detail) {
                Product::find($detail->product_id)
                    ->decrement('stock', $detail->received_quantity);
            }

            // prepare lookups
            $existing = $productReceived->details->keyBy('product_id');
            $incoming = collect($validator->validated()['products'])->keyBy('product_id');
            $totalPrice = 0;

            // process each reorder detail
            foreach ($reorderDetails as $rd) {
                $pid = $rd->product_id;
                $qty = $incoming->has($pid) ? $incoming[$pid]['received_quantity'] : 0;
                $price = ($qty > 0 && $incoming->has($pid)) ? $incoming[$pid]['price'] : 0;
                $line = $qty * $price;

                Product::find($pid)->increment('stock', $qty);

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
            }

            // update header and fund transaction
            $status = ProductReceivedDetail::where('product_received_id', $productReceived->id)
                ->where('received_quantity', '>', 0)->exists() ? 'pending' : 'diretur';

            $productReceived->update([
                'received_date' => $newDate->format('Y-m-d'),
                'total_received_price' => $totalPrice,
                'received_status' => $status,
            ]);

            $fund = FundTransaction::where('product_received_id', $productReceived->id)->first();
            if ($totalPrice === 0) {
                $fund && $fund->delete();
            } elseif ($fund) {
                $fund->update(['amount' => $totalPrice]);
            }

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
        $productReceived = ProductReceived::with('details.product')
            ->findOrFail($id);

        if ($productReceived->received_status !== 'pending') {
            return response()->json([
                'status' => 'error',
                'message' => 'Hanya data penerimaan dengan status pending yang bisa diselesaikan.'
            ], 422);
        }

        $productReceived->update([
            'received_status' => 'selesai',
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Data penerimaan produk berhasil diselesaikan',
            'data' => $productReceived->load('details.product'),
        ], 200);
    }
}
