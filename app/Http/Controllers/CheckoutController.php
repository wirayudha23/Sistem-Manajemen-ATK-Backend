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

class CheckoutController extends Controller
{

    public function index(Request $request)
    {
        try {
            $page = $request->get('page', 1);
            $limit = $request->get('limit', 10);
            $sort_column = $request->get('sort_column', 'checkout_date');
            $sort_type = $request->get('sort_type', 'desc');
            $search = $request->get('search', '');

            $query = Checkout::query()->with('items.product');

            if ($search) {
                $query->where('initial', 'like', '%' . $search . '%');
            }

            $checkouts = $query
                ->orderBy($sort_column, $sort_type)
                ->paginate($limit, ['*'], 'page', $page);

            return response()->json([
                'status' => 'success',
                'message' => 'Checkouts fetched successfully',
                'data' => $checkouts->load('items.product'),
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
        $validator = Validator::make($request->all(), [
            'user_id' => [
                'required',
                Rule::exists('users', 'id')->where(function ($query) {
                    $query->where('role', 'dosen');
                }),
            ]
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 400);
        }

        $checkoutCart = CheckoutCart::with('product')->get();

        if ($checkoutCart->isEmpty()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Cart is empty.',
            ], 400);
        }

        DB::beginTransaction();

        try {
            // Check stock availability
            foreach ($checkoutCart as $item) {
                if ($item->product->stock < $item->checkout_quantity) {
                    throw new \Exception('Product ' . $item->product->name . ' out of stock');
                }
            }

            // Create checkout
            $checkout = Checkout::create([
                'user_id' => $request->user_id,
                // 'checkout_date' => Carbon::now('Asia/Jakarta'),
                'checkout_date' => $request->checkout_date,
            ]);

            // Create checkout details and update product stock
            foreach ($checkoutCart as $item) {
                CheckoutDetail::create([
                    'checkout_id' => $checkout->id,
                    'product_id' => $item->product_id,
                    'checkout_quantity' => $item->checkout_quantity,
                ]);

                $item->product->decrement('stock', $item->checkout_quantity);
            }

            // Clear the cart
            CheckoutCart::query()->delete();

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Checkout created.',
                'data' => $checkout,
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error during checkout: ' . $e->getMessage(), [
                'exception' => $e,
            ]);

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
        $validator = Validator::make($request->all(), [
            'user_id' => [
                'required',
                Rule::exists('users', 'id')->where(function ($query) {
                    $query->where('role', 'dosen');
                }),
            ],
            'details' => 'required|array',
            'details.*.product_id' => 'required|exists:products,id',
            'details.*.checkout_quantity' => 'required|integer|min:1'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 400);
        }

        DB::beginTransaction();

        try {
            $checkout = Checkout::with('items')->find($id);

            if (!$checkout) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Checkout not found',
                ], 404);
            }

            // Update user_id
            $checkout->update(['user_id' => $request->user_id]);

            $existingDetails = $checkout->items->keyBy('product_id');
            $requestDetails = collect($request->details)->keyBy('product_id');

            // Proses untuk product yang diupdate/ditambahkan
            foreach ($request->details as $detail) {
                $product = Product::find($detail['product_id']);

                // Cek stok untuk product baru atau perubahan quantity
                if (!$existingDetails->has($detail['product_id'])) {
                    if ($product->stock < $detail['checkout_quantity']) {
                        throw new \Exception('Insufficient stock for product ' . $product->name);
                    }
                    // Kurangi stok untuk product baru
                    $product->decrement('stock', $detail['checkout_quantity']);
                } else {
                    // Hitung selisih quantity untuk product yang diupdate
                    $oldQuantity = $existingDetails->get($detail['product_id'])->checkout_quantity;
                    $quantityDiff = $detail['checkout_quantity'] - $oldQuantity;

                    if ($quantityDiff > 0 && $product->stock < $quantityDiff) {
                        throw new \Exception('Insufficient stock for product ' . $product->name);
                    }

                    // Update stok sesuai selisih
                    $product->decrement('stock', $quantityDiff);
                }

                // Update atau create detail
                CheckoutDetail::updateOrCreate(
                    [
                        'checkout_id' => $checkout->id,
                        'product_id' => $detail['product_id']
                    ],
                    ['checkout_quantity' => $detail['checkout_quantity']]
                );
            }

            // Proses untuk product yang dihapus
            $removedProducts = $existingDetails->diffKeys($requestDetails);
            foreach ($removedProducts as $removed) {
                $product = Product::find($removed->product_id);
                $product->increment('stock', $removed->checkout_quantity);
                $removed->delete();
            }

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Checkout updated successfully',
                'data' => $checkout->load('items')
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error updating checkout: ' . $e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Update failed',
                'error' => $e->getMessage()
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
                'message' => 'Checkout deleted successfully',
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
