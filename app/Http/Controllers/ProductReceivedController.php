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

            $query = ProductReceived::query();

            if ($search_column && $search) {
                $query->where($search_column, 'like', '%' . $search . '%');
            } else if ($search) {
                $query
                    ->where('received_date', 'like', '%' . $search . '%');
            }

            $productReceived = $query
                ->orderBy($sort_column, $sort_type)
                ->paginate($limit, ['*'], 'page', $page);

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

    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'reorder_id' => 'required|uuid|exists:reorders,id',
                'received_date' => 'required|date_format:d-m-Y',
                'products' => 'required|array',
                'products.*.product_id' => 'required|uuid|exists:products,id',
                'products.*.received_quantity' => 'required|integer|min:0',
                'products.*.price' => 'required|integer|min:0',
            ]);

            $reorder = Reorder::findOrFail($validated['reorder_id']);
            Log::info('Data reorder ditemukan.', ['reorder' => $reorder]);

            $receivedDate = Carbon::createFromFormat('d-m-Y', $validated['received_date']);
            Log::info('Tanggal received_date berhasil dikonversi.', ['receivedDate' => $receivedDate]);

            if ($receivedDate->lt($reorder->reorder_date)) {
                Log::warning('Tanggal received_date lebih kecil dari reorder_date.', ['receivedDate' => $receivedDate, 'reorder_date' => $reorder->reorder_date]);
                return response()->json([
                    'message' => 'Received date tidak boleh lebih kecil dari reorder date.'
                ], 422);
            }

            DB::beginTransaction();

            // 1) Buat ProductReceived
            $productReceived = new ProductReceived();
            $productReceived->id = (string) Str::uuid();
            $productReceived->reorder_id = $reorder->id;
            $productReceived->received_date = $receivedDate->format('Y-m-d');
            $productReceived->total_received_price = 0;
            $productReceived->save();
            Log::info('Record product_received berhasil dibuat.', ['productReceived' => $productReceived]);

            $totalReceivedPrice = 0;

            // 2) Proses setiap produk detail
            foreach ($validated['products'] as $prod) {
                $reorderDetail = ReorderDetail::where('reorder_id', $reorder->id)
                    ->where('product_id', $prod['product_id'])
                    ->first();

                if (!$reorderDetail) {
                    throw new \Exception("Produk dengan ID {$prod['product_id']} tidak ditemukan di detail reorder.");
                }

                if ($prod['received_quantity'] > $reorderDetail->reorder_quantity) {
                    throw new \Exception("Received quantity untuk produk {$prod['product_id']} melebihi jumlah yang dipesan.");
                }

                $totalProductPrice = $prod['received_quantity'] * $prod['price'];

                $prd = new ProductReceivedDetail();
                $prd->id = (string) Str::uuid();
                $prd->product_received_id = $productReceived->id;
                $prd->product_id = $prod['product_id'];
                $prd->received_quantity = $prod['received_quantity'];
                $prd->price = $prod['price'];
                $prd->total_product_price = $totalProductPrice;
                $prd->save();
                Log::info('Record product_received_detail berhasil dibuat.', ['productReceivedDetail' => $prd]);

                // Update stock produk
                $product = Product::findOrFail($prod['product_id']);
                $product->stock += $prod['received_quantity'];
                $product->save();
                Log::info('Stock produk berhasil diperbarui.', ['product' => $product]);

                $totalReceivedPrice += $totalProductPrice;
            }

            // 3) Update total_received_price dan simpan
            $productReceived->total_received_price = $totalReceivedPrice;
            $productReceived->save();

            // 4) Catat dana keluar di fund_transactions
            FundTransaction::create([
                'id' => (string) Str::uuid(),
                'date' => now(),
                'type' => 'out',
                'amount' => $totalReceivedPrice,
                'product_received_id' => $productReceived->id,
            ]);
            Log::info('Fund transaction (out) berhasil dibuat.', ['amount' => $totalReceivedPrice, 'product_received_id' => $productReceived->id]);

            DB::commit();

            return response()->json([
                'message' => 'Product received berhasil disimpan.'
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error menyimpan product received: ' . $e->getMessage(), [
                'exception' => $e,
            ]);

            return response()->json([
                'message' => 'Terjadi error: ' . $e->getMessage()
            ], 500);
        }
    }

    public function show($id)
    {
        try {
            $productReceived = ProductReceived::with('reorder', 'productReceivedDetails.product')->findOrFail($id);

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

    public function update(Request $request, string $id)
    {
        // Validasi input
        $validated = $request->validate([
            'received_date' => 'required|date_format:d-m-Y',
            'products' => 'required|array',
            'products.*.product_id' => 'required|uuid|exists:products,id',
            'products.*.received_quantity' => 'required|integer|min:0',
            'products.*.price' => 'required|integer|min:0',
        ]);

        DB::beginTransaction();
        try {
            // 1) Ambil record ProductReceived beserta detail lama
            $pr = ProductReceived::with('details')->findOrFail($id);

            // Map old quantities per product
            $oldDetails = $pr->details;
            $oldQtyMap = $oldDetails->pluck('received_quantity', 'product_id')->toArray();

            // 2) Update field received_date
            $pr->received_date = Carbon::createFromFormat('d-m-Y', $validated['received_date'])
                ->format('Y-m-d');
            $pr->total_received_price = 0; // akan dihitung ulang
            $pr->save();

            $newTotal = 0;
            $newProductIds = collect($validated['products'])->pluck('product_id')->all();

            // 3) Hapus detail yang tidak ada di update baru & rollback stock
            foreach ($oldDetails as $oldDetail) {
                if (!in_array($oldDetail->product_id, $newProductIds)) {
                    // Kurangi stock lama
                    $prod = Product::findOrFail($oldDetail->product_id);
                    $prod->stock -= $oldDetail->received_quantity;
                    $prod->save();

                    // Hapus detail
                    $oldDetail->delete();
                }
            }

            // 4) Loop produk baru: update/insert detail + adjust stock
            foreach ($validated['products'] as $item) {
                $pid = $item['product_id'];
                $qty = $item['received_quantity'];
                $price = $item['price'];
                $lineTotal = $qty * $price;

                // Hitung selisih stock: new - old
                $oldQty = $oldQtyMap[$pid] ?? 0;
                $diff = $qty - $oldQty;

                // Update atau buat detail baru
                $detail = $pr->details()->where('product_id', $pid)->first();
                if ($detail) {
                    $detail->received_quantity = $qty;
                    $detail->price = $price;
                    $detail->total_product_price = $lineTotal;
                    $detail->save();
                } else {
                    $detail = new ProductReceivedDetail();
                    $detail->id = (string) Str::uuid();
                    $detail->product_received_id = $pr->id;
                    $detail->product_id = $pid;
                    $detail->received_quantity = $qty;
                    $detail->price = $price;
                    $detail->total_product_price = $lineTotal;
                    $detail->save();
                }

                // Update stock produk
                $product = Product::findOrFail($pid);
                $product->stock += $diff;
                $product->save();

                $newTotal += $lineTotal;
            }

            // 5) Update total_received_price
            $pr->total_received_price = $newTotal;
            $pr->save();

            // 6) Update fund transaction (type = 'out') terkait
            $fundOut = FundTransaction::where('product_received_id', $pr->id)
                ->where('type', 'out')->first();
            if ($fundOut) {
                $fundOut->amount = $newTotal;
                $fundOut->date = now();
                $fundOut->save();
            } else {
                // Fallback: buat record baru jika belum ada
                FundTransaction::create([
                    'id' => (string) Str::uuid(),
                    'date' => now(),
                    'type' => 'out',
                    'amount' => $newTotal,
                    'product_received_id' => $pr->id,
                ]);
            }

            DB::commit();

            return response()->json([
                'message' => 'ProductReceived dan FundTransaction berhasil diperbarui.'
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error updating ProductReceived: ' . $e->getMessage(), ['exception' => $e]);

            return response()->json([
                'message' => 'Terjadi error: ' . $e->getMessage()
            ], 500);
        }
    }
}
