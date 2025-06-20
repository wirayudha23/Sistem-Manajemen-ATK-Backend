<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\StudyProgram;
use Illuminate\Support\Facades\Validator;

class StudyProgramController extends Controller
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

            $query = StudyProgram::query();

            if ($search_column && $search) {
                $query->where($search_column, 'like', '%' . $search . '%');
            } else if ($search) {
                $query
                    ->where('name', 'like', '%' . $search . '%');
            }

            $studyPrograms = $query
                ->orderBy($sort_column, $sort_type)
                ->paginate($limit, ['*'], 'page', $page);

            return response()->json([
                'status' => 'success',
                'message' => 'Study programs fetched successfully',
                'data' => $studyPrograms,
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
                'name' => 'required|string|unique:study_programs,name',
            ], [
                'name.required' => 'Nama program studi wajib diisi.',
                'name.string' => 'Nama program studi harus berupa tesk',
                'name.unique' => 'Nama program studi sudah ada.',
            ]);

            $validator->after(function ($validator) use ($request) {
                if (StudyProgram::whereRaw('LOWER(name) = ?', [strtolower($request->name)])->exists()) {
                    $validator->errors()->add('name', 'The name has already been taken.');
                }
            });

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation error',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $studyProgram = StudyProgram::create([
                'name' => $request->name,
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Program studi berhasil ditambahkan',
                'data' => $studyProgram,
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
            $studyProgram = StudyProgram::findOrFail($id);

            return response()->json([
                'status' => 'success',
                'message' => 'Study program fetched successfully',
                'data' => $studyProgram,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Internal server error',
            ], 500);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|unique:study_programs,name,' . $id,
            ], [
                'name.required' => 'Nama program studi wajib diisi.',
                'name.string' => 'Nama program studi harus berupa teks',
                'name.unique' => 'Nama program studi sudah ada.',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation error',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $studyProgram = StudyProgram::findOrFail($id);
            $studyProgram->update([
                'name' => $request->name,
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Data program studi berhasil diperbarui',
                'data' => $studyProgram,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Internal server error',
            ], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $studyProgram = StudyProgram::findOrFail($id);
            $studyProgram->delete();

            return response()->json([
                'status' => 'success',
                'message' => 'Data program studi berhasil dihapus',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Internal server error',
            ], 500);
        }
    }
}
