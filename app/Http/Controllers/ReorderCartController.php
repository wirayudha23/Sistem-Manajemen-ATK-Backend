<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use App\Models\Product;
use App\Models\ReorderCart;
use Illuminate\Support\Facades\Validator;

class ReorderCartController extends Controller
{
    public function index(Request $request)
    {
        try {
            $page = $request->get('page', 1);
            $limit = $request->get('limit', 10);
            $sort_column = $request->get('sort_column', 'id');
            $sort_type = $request->get('sort_type', 'asc');
            $search = $request->get('search', '');
            $search_column = $request->get('search_column', '');

            $query = ReorderCart::query();

            if ($search_column && $search) {
                $query->where($search_column, 'like', '%' . $search . '%');
            }

            $reorderCarts = $query
                ->orderBy($sort_column, $sort_type)
                ->paginate($limit, ['*'], 'page', $page);

            return response()->json([
                'status' => 'success',
                'message' => 'Reorder cart list',
                'data' => $reorderCarts->load('product'),
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
            $validator = Validator::make($request->all(), [
                'product_id' => 'required|exists:products,id',
            ],
            [
                'product_id.required' => 'Pilih produk yang akan dipesan ulang.',
                'product_id.exists' => 'Produk yang dipilih tidak ditemukan.',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation error',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $product = Product::find($request->product_id);

            $exist = ReorderCart::where('product_id', $request->product_id)->first();
            if ($exist) {
                return response()->json([
                    'status' => 'error',
                    'message' => "Product {$product->name} sudah ada di keranjang"
                ], 409);
            }

            // $eoq = (float) $product->economic_order_quantity;
            // if ($eoq <= 0) {
            //     $eoq = 1;
            // }

            $reorderCart = ReorderCart::create([
                'product_id' => $request->product_id,
                'reorder_quantity' => $product->economic_order_quantity,
            ]);

            return response()->json([
                'status' => 'success',
                'message' => "Product {$product->name} berhasil ditambahkan ke keranjang",
                'data' => $reorderCart,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Internal server error',
            ], 500);
        }
    }

    public function show($reorder_cart_id)
    {
        try {
            $reorderCart = ReorderCart::find($reorder_cart_id);

            if (!$reorderCart) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Reorder cart not found',
                ], 404);
            }

            return response()->json([
                'status' => 'success',
                'data' => $reorderCart->load('product'),
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Internal server error',
            ], 500);
        }
    }

    public function update(Request $request, $reorder_cart_id)
    {
        try {
            $validator = Validator::make($request->all(), [
                'reorder_quantity' => 'required|integer|min:1',
            ],
            [
                'reorder_quantity.required' => 'Jumlah pemesanan ulang harus diisi.',
                'reorder_quantity.integer' => 'Jumlah pemesanan ulang harus berupa angka.',
                'reorder_quantity.min' => 'Jumlah pemesanan ulang minimal 1.',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation error',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $reorderCart = ReorderCart::find($reorder_cart_id);

            if (!$reorderCart) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Reorder cart not found',
                ], 404);
            }

            $reorderCart->reorder_quantity = $request->reorder_quantity;
            $reorderCart->save();

            return response()->json([
                'status' => 'success',
                'message' => "Jumlah {$reorderCart->product->name} berhasil diperbarui",
                'data' => $reorderCart->load('product'),
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Internal server error',
            ], 500);
        }
    }

    public function destroy($reorder_cart_id)
    {
        try {
            $reorderCart = ReorderCart::find($reorder_cart_id);

            if (!$reorderCart) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Product not found',
                ], 404);
            }

            $reorderCart->delete();

            return response()->json([
                'status' => 'success',
                'message' => "{$reorderCart->product->name} berhasil dihapus dari keranjang",
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Internal server error',
            ], 500);
        }
    }
}
