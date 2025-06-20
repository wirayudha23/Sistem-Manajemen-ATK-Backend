<?php

namespace App\Http\Controllers;

use App\Models\Purpose;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;

class PurposeController extends Controller
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

            $query = Purpose::query();

            if ($search_column && $search) {
                $query->where($search_column, 'like', '%' . $search . '%');
            } else if ($search) {
                $query
                    ->where('name', 'like', '%' . $search . '%');
            }

            $purposes = $query
                ->orderBy($sort_column, $sort_type)
                ->paginate($limit, ['*'], 'page', $page);

            return response()->json([
                'status' => 'success',
                'message' => 'Purposes fetched successfully',
                'data' => $purposes,
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
                'name' => [
                    'required',
                    'string',
                    Rule::unique('purposes')->where(function ($query) use ($request) {
                        $query->whereRaw('LOWER(name) = ?', [strtolower($request->name)]);
                    }),
                ],
            ], [
                'name.required' => 'Nama kebutuhan wajib diisi',
                'name.string' => 'Nama kebutuhan harus berupa teks',
                'name.unique' => 'Nama kebutuhan sudah ada',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation error',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $purpose = Purpose::create([
                'name' => $request->name,
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Kebutuhan berhasil ditambahkan',
                'data' => $purpose,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Internal server error',
            ], 500);
        }
    }

    public function show($id)
    {
        try {
            $purpose = Purpose::findOrFail($id);

            return response()->json([
                'status' => 'success',
                'message' => 'Purpose fetched successfully',
                'data' => $purpose,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Internal server error',
            ], 500);
        }
    }

    public function update(Request $request, $purpose_id)
    {
        try {
            $purpose = Purpose::find($purpose_id);

            if (!$purpose) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Purpose not found',
                ], 404);
            }

            $validator = Validator::make($request->all(), [
                'name' => 'sometimes',
                'string',
                Rule::unique('purposes')->where(function ($query) use ($request) {
                    $query->whereRaw('LOWER(name) = ?', [strtolower($request->name)]);
                }),
            ], [
                'name.sometimes' => 'Nama kebutuhan tidak boleh kosong',
                'name.string' => 'Nama kebutuhan harus berupa teks',
                'name.unique' => 'Nama kebutuhan sudah ada',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation error',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $purpose->update([
                'name' => $request->name ?? $purpose->name,
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Kebutuhan berhasil diperbarui',
                'data' => $purpose,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Internal server error',
                'errors' => $e->getMessage(),
            ], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $purpose = Purpose::findOrFail($id);
            $purpose->delete();

            return response()->json([
                'status' => 'success',
                'message' => 'Kebutuhan berhasil dihapus',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Internal server error',
            ], 500);
        }
    }
}
