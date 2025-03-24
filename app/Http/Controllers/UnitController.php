<?php

namespace App\Http\Controllers;

use App\Models\Unit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class UnitController extends Controller
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

            $query = Unit::query();

            if ($search_column && $search) {
                $query->where($search_column, 'like', '%' . $search . '%');
            } else if ($search) {
                $query
                    ->where('name', 'like', '%' . $search . '%');
            }

            $units = $query
                ->orderBy($sort_column, $sort_type)
                ->paginate($limit, ['*'], 'page', $page);

            return response()->json([
                'status' => 'success',
                'message' => 'Units fetched successfully',
                'data' => $units,
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
                'name' => 'required|string|unique:units,name'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation error',
                    'errors' => $validator->errors(),
                ], 400);
            }

            $unit = Unit::create([
                'name' => $request->name
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Unit created successfully',
                'data' => $unit,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Internal server error',
            ], 500);
        }
    }

    public function show($unit_id)
    {
        try {
            $unit = Unit::find($unit_id);

            if (!$unit) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Unit not found',
                ], 404);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Unit fetched successfully',
                'data' => $unit,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Internal server error',
            ], 500);
        }
    }

    public function update(Request $request, $unit_id)
    {
        try {
            $unit = Unit::find($unit_id);

            if (!$unit) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Unit not found',
                ], 404);
            }

            $validator = Validator::make($request->all(), [
                'name' => 'required|string|unique:units,name,' . $unit_id
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation error',
                    'errors' => $validator->errors(),
                ], 400);
            }

            $unit->update([
                'name' => $request->name ?? $unit->name
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Unit updated successfully',
                'data' => $unit,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Internal server error',
            ], 500);
        }
    }

    public function destroy(Unit $unit)
    {
        try {
            $unit->delete();

            return response()->json([
                'status' => 'success',
                'message' => 'Unit deleted successfully',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Internal server error',
            ], 500);
        }
    }
}
