<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Product;
use App\Models\Category;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Log;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;


class ProductController extends Controller
{
    public function index(Request $request)
    {
        try {
            $page = $request->get('page', 1);
            $limit = $request->get('limit', 10);
            $sortColumn = $request->get('sort_column', 'name');
            $sortType = $request->get('sort_type', 'asc');
            $search = $request->get('search', '');
            $searchColumn = $request->get('search_column', 'name');

            $query = Product::with('category:id,name', 'unit:id,name');

            // -- SEARCH --
            if ($search) {
                if ($searchColumn) {
                    $query->where($searchColumn, 'like', "%{$search}%");
                } else {
                    $query->where('name', 'like', "%{$search}%");
                }
            }

            // -- SORTING --
            if ($sortColumn === 'stock') {
                // Jika sort_column=stock, artinya klien ingin urutan berdasarkan kedekatan stock ke ROP
                $query->orderByRaw("ABS(stock - reorder_point) {$sortType}")
                    // tiebreaker: urutkan juga by stock/reorder_point kalau perlu
                    ->orderBy('stock', $sortType);
            } else {
                // Urutan normal sesuai kolom yang diminta
                $query->orderBy($sortColumn, $sortType);
            }

            $products = $query->paginate($limit, ['*'], 'page', $page);

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
            $validator = Validator::make(
                $request->all(),
                [
                    'name' => [
                        'required',
                        'string',
                        Rule::unique('products', 'name')->where(function ($query) use ($request) {
                            $query->whereRaw('LOWER(name) = ?', [strtolower($request->name)]);
                        }),
                    ],
                    'price' => 'required|integer|min:0',
                    // 'stock' => 'required|integer|min:0',
                    'image' => 'required|image|mimes:png,jpg,jpeg|max:2048',
                    'category_id' => 'required|exists:categories,id',
                    'unit_id' => 'required|exists:units,id',
                ],
                [
                    'name.required' => 'Nama produk wajib diisi',
                    'name.unique' => 'Nama produk sudah ada',
                    'name.string' => 'Nama produk harus berupa teks',
                    'price.required' => 'Harga produk wajib diisi',
                    'price.integer' => 'Harga produk harus berupa angka',
                    'price.min' => 'Harga produk tidak boleh kurang dari 0',
                    'stock.required' => 'Stok produk wajib diisi',
                    'stock.integer' => 'Stok produk harus berupa angka',
                    'stock.min' => 'Stok produk tidak boleh kurang dari 0',
                    'image.required' => 'Gambar produk wajib diisi',
                    'image.image' => 'File yang diupload harus berupa gambar',
                    'image.mimes' => 'Gambar harus berupa png, jpg, atau jpeg',
                    'image.max' => 'Ukuran gambar tidak boleh lebih dari 2MB',
                    'category_id.exists' => 'Kategori produk tidak ditemukan',
                    'category_id.required' => 'Kategori produk wajib diisi',
                    'unit_id.exists' => 'Satuan produk tidak ditemukan',
                    'unit_id.required' => 'Satuan produk wajib diisi',
                ]
            );

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation error',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $data = $validator->validated();
            $data['stock'] = 0;

            DB::transaction(function () use ($request, &$product, &$data) {
                $data['image'] =  $request->file('image')->store('images', 'public');
                // $data['image'] = 'storage/' . $request->file('image')->store('images', 'public');
                $product = Product::create($data);
            });

            return response()->json([
                'status' => 'success',
                'message' => 'Data produk berhasil ditambahkan',
                'data' => $product->load('category:id,name', 'unit:id,name'),
            ], 201);

        } catch (\Exception $e) {
            if (!empty($data['image']) && Storage::disk('public')->exists($data['image'])) {
                Storage::disk('public')->delete($data['image']);
            }
            return response()->json([
                'status' => 'error',
                'message' => 'Internal server error',
                'errors' => $e->getMessage(),
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

            // 1. Validasi
            $validator = Validator::make(
                $request->all(),
                [
                    'name' => ['sometimes', 'string', Rule::unique('products', 'name')->ignore($product->id)->where(fn($q) => $q->whereRaw('LOWER(name)=?', [strtolower($request->name)]))],
                    'price' => 'sometimes|integer|min:0',
                    // 'stock' => 'sometimes|integer|min:0',
                    'image' => 'sometimes|image|mimes:png,jpg,jpeg|max:2048',
                    'category_id' => 'sometimes|exists:categories,id',
                    'unit_id' => 'sometimes|exists:units,id',
                ],
                [
                    'name.string' => 'Nama produk harus berupa teks.',
                    'name.unique' => 'Nama produk sudah ada.',

                    'price.integer' => 'Harga harus berupa angka.',
                    'price.min' => 'Harga tidak boleh kurang dari 0',

                    // 'stock.integer' => 'Stok harus berupa angka.',
                    // 'stock.min' => 'Stok tidak boleh kurang dari 0',

                    'image.image' => 'File harus berupa gambar.',
                    'image.mimes' => 'Format gambar hanya boleh: png, jpg, jpeg.',
                    'image.max' => 'Ukuran gambar maksimal :max kilobyte.',

                    'category_id.exists' => 'Kategori tidak ditemukan.',
                    'category_id.required' => 'Kategori wajib diisi.',
                    'unit_id.exists' => 'Satuan tidak ditemukan.',
                    'unit_id.required' => 'Satuan wajib diisi.',
                ]
            );

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation error',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $data = $validator->validated();

            // 2. Simpan update dalam transaksi
            DB::transaction(function () use ($request, $product, &$data) {
                if ($request->hasFile('image')) {
                    // hapus gambar lama
                    if ($product->image && Storage::disk('public')->exists($product->image)) {
                        Storage::disk('public')->delete($product->image);
                    }
                    // simpan yang baru
                    $data['image'] = $request->file('image')->store('images', 'public');
                }
                // update data
                $product->update($data);
            });

            return response()->json([
                'status' => 'success',
                'message' => 'Data produk berhasil diperbarui',
                'data' => $product->load('category:id,name', 'unit:id,name'),
            ], 200);

        } catch (\Exception $e) {
            Log::error('Error updating product: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Internal server error',
                'errors' => $e->getMessage(),
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
                'message' => 'Data produk berhasil dihapus',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Internal server error',
            ], 500);
        }
    }

    public function publicIndex()
    {
        return Product::select('id', 'name', 'image', 'price', 'stock')->get();
    }

    public function template()
    {
        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Exports\ProductTemplate,
            'product_template.xlsx',
            'Xlsx');
    }
}
