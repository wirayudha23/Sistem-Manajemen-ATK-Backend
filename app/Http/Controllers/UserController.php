<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Log;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;

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
            // Define allowed roles per position
            $roleOptions = [
                'Dosen' => ['Staff'],
                'Tendik' => ['BAAK', 'Staff'],
                'Rumah Tangga' => ['Staff'],
            ];

            // Validation rules
            $rules = [
                'google_id' => 'nullable|string',
                'name' => [
                    'required',
                    'string',
                    Rule::unique('users', 'name')->where(function ($query) use ($request) {
                        $query->whereRaw('LOWER(name) = ?', [strtolower($request->name)]);
                    }),
                ],
                'email' => 'required|email|unique:users,email',
                'nip' => 'required|digits:6|integer|unique:users,nip',
                'position' => ['required', 'string', 'in:Dosen,Tendik,Rumah Tangga'],
                'initial' => 'required|alpha|size:3|unique:users,initial',
                'role' => [
                    'required',
                    'string',
                    Rule::in($roleOptions[$request->position] ?? []),
                ],
                'study_program_id' => [
                    Rule::requiredIf($request->position === 'Dosen'),
                    'nullable',
                    'exists:study_programs,id',
                ],
                'avatar' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            ];

            $validator = Validator::make($request->all(), $rules);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation error',
                    'errors' => $validator->errors(),
                ], 422);
            }

            // Retrieve validated data
            $data = $validator->validated();

            // If not Dosen, clear study_program_id
            if (($data['position'] ?? null) !== 'Dosen') {
                $data['study_program_id'] = null;
            }

            // Handle avatar upload
            if ($request->hasFile('avatar')) {
                $data['avatar'] = $request->file('avatar')->store('avatars', 'public');
            }

            // Persist in transaction
            DB::beginTransaction();
            $user = User::create($data);
            DB::commit();

            return response()->json([
                'status' => 'success',
                'data' => $user,
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error creating user: ' . $e->getMessage(), ['exception' => $e]);

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

            // Update actions based on current user's role
            if ($currentUser->role === 'Kabag') {
                // Kabag can only update another BAAK's role to Kabag
                if ($currentUser->id === $user->id) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Kabag cannot change their own role',
                    ], 403);
                }

                if ($user->role !== 'BAAK') {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Only users with role BAAK can be promoted',
                    ], 400);
                }

                $newRole = $request->input('role');
                if ($newRole !== 'Kabag') {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Role must be Kabag',
                    ], 400);
                }

                // Promote target and demote current

                // Use transaction to ensure atomicity
                \DB::transaction(function () use ($user, $currentUser) {
                    // Promote target to Kabag
                    $user->update(['role' => 'Kabag']);

                    // Demote current Kabag to Staff
                    $currentUser->update(['role' => 'Staff']);
                });

                return response()->json([
                    'status' => 'success',
                    'message' => 'User role updated and current Kabag demoted',
                    'data' => $user,
                ], 200);

            } elseif ($currentUser->role === 'BAAK') {
                // BAAK can update profile fields and role
                // Build validation rules (similar to store)
                $rules = [
                    'name' => ['sometimes', 'string', Rule::unique('users', 'name')->ignore($user->id)],
                    'email' => ['sometimes', 'email', Rule::unique('users', 'email')->ignore($user->id)],
                    'nip' => ['sometimes', 'digits:6', 'integer', Rule::unique('users', 'nip')->ignore($user->id)],
                    'position' => ['sometimes', 'string', 'in:Dosen,Tendik,Rumah Tangga'],
                    'initial' => ['sometimes', 'string', 'size:3', 'alpha', Rule::unique('users', 'initial')->ignore($user->id)],
                    'avatar' => ['sometimes', 'image', 'mimes:jpeg,png,jpg,gif,svg', 'max:2048'],
                    'study_program_id' => ['nullable', Rule::exists('study_programs', 'id')],
                    'role' => ['sometimes', 'string'],
                ];

                $validator = Validator::make($request->all(), $rules);

                // Conditional validation for position-role-prodi
                $validator->after(function ($validator) use ($request) {
                    $pos = $request->input('position', $request->user()->position);
                    $role = $request->input('role', $request->user()->role);

                    if ($request->has('position') || $request->has('role') || $request->has('study_program_id')) {
                        // Dosen
                        if ($pos === 'Dosen') {
                            if ($role !== 'Staff') {
                                $validator->errors()->add('role', 'For Dosen position, role must be Staff.');
                            }
                            if (!$request->filled('study_program_id')) {
                                $validator->errors()->add('study_program_id', 'Study program is required for Dosen.');
                            }
                        }
                        // Tendik
                        elseif ($pos === 'Tendik') {
                            if ($role !== ['BAAK', 'Staff']) {
                                $validator->errors()->add('role', 'For Tendik position, role must be BAAK.');
                            }
                        }
                        // Rumah Tangga
                        elseif ($pos === 'Rumah Tangga') {
                            if ($role !== 'Staff') {
                                $validator->errors()->add('role', 'For Rumah Tangga position, role must be Staff.');
                            }
                        }
                    }
                });

                if ($validator->fails()) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Validation error',
                        'errors' => $validator->errors(),
                    ], 400);
                }

                // Determine updated values
                $position = $request->input('position', $user->position);
                $studyProgramId = $position === 'Dosen'
                    ? $request->input('study_program_id', $user->study_program_id)
                    : null;

                // Perform update
                $user->update([
                    'name' => $request->input('name', $user->name),
                    'email' => $request->input('email', $user->email),
                    'nip' => $request->input('nip', $user->nip),
                    'position' => $position,
                    'study_program_id' => $studyProgramId,
                    'initial' => $request->input('initial', $user->initial),
                    'role' => $request->input('role', $user->role),
                    'avatar' => $request->input('avatar', $user->avatar),
                ]);

                return response()->json([
                    'status' => 'success',
                    'message' => 'User updated successfully',
                    'data' => $user,
                ], 200);

            } else {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Unauthorized access',
                ], 403);
            }

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Internal server error',
                'error' => $e->getMessage(),
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
            Log::error($e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Internal server error' . $e->getMessage(),
            ], 500);
        }
    }
}
