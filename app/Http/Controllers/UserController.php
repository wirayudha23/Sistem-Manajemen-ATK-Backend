<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;

class UserController extends Controller
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

            $query = User::query();

            if ($search_column && $search) {
                $query->where($search_column, 'like', '%' . $search . '%');
            } else if ($search) {
                $query
                    ->where('name', 'like', '%' . $search . '%')
                    ->orWhere('email', 'like', '%' . $search . '%')
                    ->orWhere('role', 'like', '%' . $search . '%');
            }

            $users = $query
                ->orderBy($sort_column, $sort_type)
                ->paginate($limit, ['*'], 'page', $page);

            return response()->json([
                'status' => 'success',
                'data' => $users,
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
                'google_id' => 'nullable|string',
                'name' => 'required|string',
                'email' => 'required|email|unique:users,email',
                'initial' => 'required|string|max:3',
                'role' => 'required|string|in:dosen',
                'avatar' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation error',
                    'errors' => $validator->errors(),
                ], 400);
            }

            $user = User::create([
                'google_id' => null,
                'name' => $request->name,
                'email' => $request->email,
                'initial' => $request->initial,
                'role' => $request->role,
                'avatar' => null
            ]);

            return response()->json([
                'status' => 'success',
                'data' => $user,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Internal server error',
            ], 500);
        }
    }

    public function show($user_id)
    {
        try {
            $user = User::find($user_id);

            if (!$user) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'User not found',
                ], 404);
            }

            return response()->json([
                'status' => 'success',
                'data' => $user,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Internal server error',
            ], 500);
        }
    }

    public function update(Request $request, User $user)
    {
        try {
            $currentUser = Auth::guard('sanctum')->user();

            if (!$currentUser) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Unauthorized access',
                ], 401);
            }

            if (!$user) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'User not found',
                ], 404);
            }

            // Validasi berdasarkan role
            $rules = [];
            if ($currentUser->role == 'baak') {
                $rules = [
                    'name' => 'sometimes|string',
                    'initial' => 'sometimes|string|max:3'
                ];
            } elseif ($currentUser->role == 'kepala baak') {
                $rules = ['role' => 'required|string|in:kepala baak,baak,dosen'];
            } else {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Unauthorized access',
                ], 403);
            }

            $validator = Validator::make($request->all(), $rules);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation error',
                    'errors' => $validator->errors(),
                ], 400);
            }

            // Update berdasarkan role yang login
            if ($currentUser->role == 'baak') {
                $user->update([
                    'name' => $request->name ?? $user->name,
                    'initial' => $request->initial ?? $user->initial
                ]);
            } elseif ($currentUser->role == 'kepala baak') {
                $user->update(['role' => $request->role]);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'User updated successfully',
                'data' => $user,
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Internal server error',
                'error' => $e->getMessage(), // Untuk debugging
            ], 500);
        }
    }

    public function destroy(User $user)
    {
        try {
            $user->delete();

            return response()->json([
                'status' => 'success',
                'message' => 'User deleted successfully',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Internal server error',
            ], 500);
        }
    }
}
