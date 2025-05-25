<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;

class CategoryController extends Controller
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

            $query = Category::query();

            if ($search_column && $search) {
                $query->where($search_column, 'like', '%' . $search . '%');
            } else if ($search) {
                $query
                    ->where('name', 'like', '%' . $search . '%');
            }

            $categories = $query
                ->orderBy($sort_column, $sort_type)
                ->paginate($limit, ['*'], 'page', $page);

            return response()->json([
                'status' => 'success',
                'message' => 'Categories fetched successfully',
                'data' => $categories,
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

                        Rule::unique('categories')->where(function ($query) use ($request) {
                            $query->whereRaw('LOWER(name) = ?', [strtolower($request->name)]);
                        }),
                    ],
                ],
                [
                    'name.required' => 'Name kategori wajib diisi',
                    'name.unique' => 'Nama kategori sudah ada',
                    'name.string' => 'Nama kategori harus berupa tesk'
                ]
            );

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation error',
                    'errors' => $validator->errors(),
                ], 422);
            }

            // $name = Str::title($request->name);

            $category = Category::create([
                'name' => $request->name,
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Kategori berhasil ditambahkan',
                'data' => $category,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Internal server error',
            ], 500);
        }
    }

    public function show($category_id)
    {
        try {
            $category = Category::find($category_id);

            if (!$category) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Category not found',
                ], 404);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Category fetched successfully',
                'data' => $category,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Internal server error',
            ], 500);
        }
    }

    public function update(Request $request, $category_id)
    {
        try {
            $category = Category::find($category_id);
            if (!$category) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Category not found',
                ], 404);
            }

            // 1. Validasi
            $validator = Validator::make($request->all(), [
                'name' => [
                    'sometimes',
                    'string',
                    Rule::unique('categories', 'name')
                        ->ignore($category->id, 'id')
                        ->where(
                            function ($query) use ($request) {
                                $query->whereRaw('LOWER(name)=?', [strtolower($request->name)]);
                            }
                        ),
                ],
            ],
            [
                'name.required' => 'Name kategori wajib diisi',
                'name.unique' => 'Nama kategori sudah ada',
                'name.string' => 'Nama kategori harus berupa tesk'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation error',
                    'errors' => $validator->errors(),
                ], 400);
            }

            // 2. Ambil data yang tervalidasi (hanya 'name' kalau dikirim)
            $data = $validator->validated();

            // 3. Update mass‑assignment
            $category->update($data);

            return response()->json([
                'status' => 'success',
                'message' => 'Kategori berhasil diupdate',
                'data' => $category,
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Internal server error',
            ], 500);
        }
    }


    public function destroy(Category $category)
    {
        try {
            $category->delete();

            return response()->json([
                'status' => 'success',
                'message' => 'Kategori berhasil dihapus',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Internal server error',
            ], 500);
        }
    }
}
