<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Product;
use App\Models\Category;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Log;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        try {
            $page = $request->get('page', 1);
            $limit = $request->get('limit', 10);
            $sort_column = $request->get('sort_column', 'name');
            $sort_type = $request->get('sort_type', 'asc');
            $search = $request->get('search', '');
            $search_column = $request->get('search_column', 'name');

            $query = Product::query()->with('category:id,name', 'unit:id,name');

            if ($search_column && $search) {
                $query->where($search_column, 'like', '%' . $search . '%');
            } else if ($search) {
                $query
                    ->where('name', 'like', '%' . $search . '%');
            }

            $products = $query
                ->orderBy($sort_column, $sort_type)
                ->paginate($limit,  ['*'], 'page', $page);

            return response()->json([
                'status' => 'success',
                'message' => 'Products fetched successfully',
                'data' => $products,
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
                'name' => 'required|string|unique:products,name',
                'price' => 'required|integer',
                'stock' => 'required|integer',
                'image' => 'required|image|mimes:png,jpg,jpeg',
                'category_id' => [
                    'required',
                    Rule::exists('categories', 'id')->where(function ($query) {
                        $query->whereNotNull('id');
                    })
                ],
                'unit_id' => [
                    'required',
                    Rule::exists('units', 'id')->where(function ($query) {
                        $query->whereNotNull('id');
                    })
                ]
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation error',
                    'errors' => $validator->errors(),
                ], 400);
            }

            $imagePath = $request->file('image')->store('images', 'public');

            $product = Product::create([
                'name' => $request->name,
                'price' => $request->price,
                'stock' => $request->stock,
                'category_id' => $request->category_id,
                'unit_id' => $request->unit_id,
                'image' => $imagePath
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Product created successfully',
                'data' => $product->load('category:id,name','unit:id,name'),
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Internal server error',
            ], 500);
        }
    }

    public function show($product_id)
    {
        try {
            $product = Product::find($product_id);

            if (!$product) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Product not found',
                ], 404);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Product fetched successfully',
                'data' => $product->load('category:id,name'),
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error during show product: ' . $e->getMessage(), [
                'exception' => $e,
            ]);
            return response()->json([
                'status' => 'error',
                'message' => 'Internal server error',
            ], 500);
        }
    }

    public function update(Request $request, $product_id)
    {
        try {
            $product = Product::find($product_id);

            if (!$product) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Product not found',
                ], 404);
            }

            $validator = Validator::make($request->all(), [
                'name' => 'required|string|unique:products,name',
                'price' => 'required|integer',
                'stock' => 'required|integer',
                // 'unit' => 'required|string',
                'image' => 'required|image|mimes:png,jpg,jpeg',
                'category_id' => [
                    'required',
                    Rule::exists('categories', 'id')->where(function ($query) {
                        $query->whereNotNull('id');
                    })
                ],
                'unit_id' => [
                    'required',
                    Rule::exists('units', 'id')->where(function ($query) {
                        $query->whereNotNull('id');
                    })
                ]
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation error',
                    'errors' => $validator->errors(),
                ], 400);
            }

            if ($request->hasFile('image')) {
                if ($product->image && Storage::disk('public')->exists($product->image)) {
                    Storage::disk('public')->delete($product->image);
                }

                $imagePath = $request->file('image')->store('images', 'public');
                $product->image = $imagePath;
            }

            $product->update([
                'name' => $request->name,
                'price' => $request->price,
                'stock' => $request->stock,
                // 'unit' => $request->unit,
                'category_id' => $request->category_id,
                'unit_id' => $request->unit_id,
            ]);


            return response()->json([
                'status' => 'success',
                'message' => 'Product updated successfully',
                'data' => $product->load('category:id,name'),
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error during update product: ' . $e->getMessage(), [
                'exception' => $e,
            ]);
            return response()->json([
                'status' => 'error',
                'message' => 'Internal server error',
                Log::error('Error during update product: ' . $e->getMessage(), [
                    'exception' => $e,
                ])
            ], 500);
        }
    }

    public function destroy(Product $product)
    {
        try {
            if ($product->image && Storage::disk('public')->exists($product->image)) {
                Storage::disk('public')->delete($product->image);
            }

            $product->delete();

            return response()->json([
                'status' => 'success',
                'message' => 'Product deleted successfully',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Internal server error',
            ], 500);
        }
    }
}
