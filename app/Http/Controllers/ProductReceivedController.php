<?php

namespace App\Http\Controllers;

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

            $productReceived = new ProductReceived();
            $productReceived->id = (string) Str::uuid();
            $productReceived->reorder_id = $reorder->id;
            $productReceived->received_date = $receivedDate->format('Y-m-d');
            $productReceived->total_received_price = 0;
            $productReceived->save();
            Log::info('Record product_received berhasil dibuat.', ['productReceived' => $productReceived]);

            $totalReceivedPrice = 0;

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

                $totalProductPrice = $prod['received_quantity'] * $reorderDetail->original_price;


                $prd = new ProductReceivedDetail();
                $prd->id = (string) Str::uuid();
                $prd->product_received_id = $productReceived->id;
                $prd->product_id = $prod['product_id'];
                $prd->received_quantity = $prod['received_quantity'];
                $prd->price = $reorderDetail->original_price;
                $prd->total_product_price = $totalProductPrice;
                $prd->save();
                Log::info('Record product_received_detail berhasil dibuat.', ['productReceivedDetail' => $prd]);

                $product = Product::findOrFail($prod['product_id']);
                $product->stock += $prod['received_quantity'];
                $product->save();
                Log::info('Stock produk berhasil diperbarui.', ['product' => $product]);

                $totalReceivedPrice += $totalProductPrice;
            }

            $productReceived->total_received_price = $totalReceivedPrice;
            $productReceived->save();

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
}
