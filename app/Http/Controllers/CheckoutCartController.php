<?php

namespace App\Http\Controllers;

use App\Models\Cart;
use App\Models\CheckoutCart;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CheckoutCartController extends Controller
{

    public function index(Request $request)
    {
        try {
            $page = $request->get('page', 1);
            $limit = $request->get('limit', 10);
            $sort_column = $request->get('sort_column', 'created_at');
            $sort_type = $request->get('sort_type', 'asc');
            $search = $request->get('search', '');
            $search_column = $request->get('search_column', 'name');

            $query = CheckoutCart::query()->with([
                'product:id,name,image,stock,category_id,unit_id',
                'product.category:id,name',
                'product.unit:id,name'
            ]);

            if ($search_column && $search) {
                $query->where($search_column, 'like', '%' . $search . '%');
            } else if ($search) {
                $query
                    ->where('name', 'like', '%' . $search . '%');
            }

            $carts = $query
                ->orderBy($sort_column, $sort_type)
                ->paginate($limit, ['*'], 'page', $page);

            return response()->json([
                'status' => 'success',
                'message' => 'Carts fetched successfully',
                'data' => $carts,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Internal server error',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'product_id' => 'required|exists:products,id',
                'checkout_quantity' => 'required|integer|min:1',
            ],
                [
                    'product_id.required' => 'Pilih produk',
                    'product_id.exists' => 'Produk tidak ditemukan',
                    'checkout_quantity.required' => 'Jumlah pengambilan wajib diisi',
                    'checkout_quantity.integer' => 'Jumlah pengambilan harus berupa angka',
                    'checkout_quantity.min' => 'Jumlah pengambilan minimal 1',
                ]
            );

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation error',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $product = Product::find($request->product_id);

            if ($product->stock < 1) {
                return response()->json([
                    'status' => 'error',
                    'message' => "Produk {$product->name} habis."
                ], 409);
            }

            if ($request->checkout_quantity > $product->stock) {
                return response()->json([
                    'status' => 'error',
                    'message' => "Stok {$product->name} tidak cukup."
                ], 409);
            }

            if (CheckoutCart::where('product_id', $request->product_id)->exists()) {
                return response()->json([
                    'status' => 'error',
                    'message' => "Produk {$product->name} sudah ada di daftar ATK",
                ], 409);
            }

            $checkoutCart = CheckoutCart::create([
                'product_id' => $request->product_id,
                'checkout_quantity' => $request->checkout_quantity,
            ]);

            return response()->json([
                'status' => 'success',
                'message' => "Produk {$product->name} ditambahkan ke daftar ATK",
                'data' => $checkoutCart,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Internal server error',
            ], 500);
        }
    }

    public function show($checkout_cart_id)
    {
        try {
            $checkoutCart = CheckoutCart::find($checkout_cart_id);

            if (!$checkoutCart) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Checkout cart not found',
                ], 404);
            }

            return response()->json([
                'status' => 'success',
                'data' => $checkoutCart->load('product:id,name,price,stock,economic_order_quantity'),
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Internal server error',
            ], 500);
        }
    }

    public function update(Request $request, $checkout_cart_id)
    {
        try {
            $checkoutCart = CheckoutCart::find($checkout_cart_id);

            if (!$checkoutCart) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Checkout cart not found',
                ], 404);
            }

            $validator = Validator::make($request->all(), [
                'checkout_quantity' => 'required|integer|min:1',
            ],
                [
                    'checkout_quantity.required' => 'Jumlah pengambilan wajib diisi',
                    'checkout_quantity.integer' => 'Jumlah pengambilan harus berupa angka',
                    'checkout_quantity.min' => 'Jumlah pengambilan minimal 1',
                ]
            );

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation error',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $product = Product::find($checkoutCart->product_id);

            if ($product->stock < $request->checkout_quantity) {
                return response()->json([
                    'status' => 'error',
                    'message' => "Stok {$product->name} tidak cukup."
                ], 409);
            }

            $checkoutCart->update([
                'checkout_quantity' => $request->checkout_quantity,
            ]);

            return response()->json([
                'status' => 'success',
                'message' => "Jumlah pengambilan {$product->name} diperbarui",
                'data' => $checkoutCart->load('product:id,name,price,stock,economic_order_quantity'),
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Internal server error',
            ], 500);
        }
    }

    public function destroy($checkout_cart_id)
    {
        try {
            $checkoutCart = CheckoutCart::find($checkout_cart_id);

            if (!$checkoutCart) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Product not found',
                ], 404);
            }

            $checkoutCart->delete();

            return response()->json([
                'status' => 'success',
                'message' => "{$checkoutCart->product->name} dihapus dari daftar ATK",
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Internal server error',
                'error' => $e->getMessage(), // Tambahkan pesan error untuk debugging
            ], 500);
        }
    }
}
