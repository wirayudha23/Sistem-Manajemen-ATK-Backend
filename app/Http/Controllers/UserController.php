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
                'message' => 'Users fetched successfully',
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
                'phone_number' => [
                    Rule::requiredIf($request->position === 'Rumah Tangga'),
                    'nullable',
                    'string',
                    'min:11',
                    'max:12',
                    'unique:users,phone_number',
                    'regex:/^08\d{9,10}$/'
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

            if (($data['position'] ?? null) !== 'Rumah Tangga') {
                $data['phone_number'] = null;
            }


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
                'message' => 'User created successfully',
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
                    'data' => $user,
                ], 404);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'User fetched successfully',
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
                    'data' => $user,
                ], 404);
            }

            // Only Kabag and BAAK have update rights
            if ($currentUser->role === 'Kabag') {
                // Prevent self-promotion
                if ($currentUser->id === $user->id) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Kabag cannot change their own role',
                    ], 403);
                }

                // Only users with role BAAK can be promoted
                if ($user->role !== 'BAAK') {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Only users with role BAAK can be promoted to Kabag',
                    ], 400);
                }

                // Ensure new role is Kabag
                $newRole = $request->input('role');
                if ($newRole !== 'Kabag') {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Role must be Kabag',
                    ], 400);
                }

                // Perform atomic promotion and demotion
                DB::transaction(function () use ($user, $currentUser) {
                    $user->update(['role' => 'Kabag']);
                    $currentUser->update(['role' => 'Staff']);
                });

                return response()->json([
                    'status' => 'success',
                    'message' => 'User promoted to Kabag and current Kabag demoted to Staff',
                    'data' => $user,
                ], 200);

            } elseif ($currentUser->role === 'BAAK') {
                // Validation rules aligned with store()
                $rules = [
                    'name' => [
                        'sometimes',
                        'string',
                        Rule::unique('users', 'name')
                            ->ignore($user->id)
                            ->where(function ($query) use ($request) {
                                $query->whereRaw('LOWER(name) = ?', [strtolower($request->name)]);
                            }),
                    ],
                    'email' => [
                        'sometimes',
                        'email',
                        Rule::unique('users', 'email')->ignore($user->id),
                    ],
                    'nip' => [
                        'sometimes',
                        'digits:6',
                        'integer',
                        Rule::unique('users', 'nip')->ignore($user->id),
                    ],
                    'initial' => [
                        'sometimes',
                        'alpha',
                        'size:3',
                        Rule::unique('users', 'initial')->ignore($user->id),
                    ],
                    'position' => ['sometimes', 'string', 'in:Dosen,Tendik,Rumah Tangga'],
                    'role' => ['sometimes', 'string'],
                    'study_program_id' => ['sometimes', 'nullable', Rule::exists('study_programs', 'id')],
                    'avatar' => ['sometimes', 'image', 'mimes:jpeg,png,jpg,gif,svg', 'max:2048'],
                    'phone_number' => [
                        Rule::requiredIf($request->input('position', $user->position) === 'Rumah Tangga'),
                        'nullable',
                        'string',
                        'min:11',
                        'max:12',
                        "regex:/^08\\d{9,10}$/",
                        Rule::unique('users', 'phone_number')->ignore($user->id),
                    ],
                ];

                $validator = Validator::make($request->all(), $rules);

                // Conditional checks: keep existing after() logic
                $validator->after(function ($validator) use ($request, $user) {
                    $pos = $request->input('position', $user->position);
                    $role = $request->input('role', $user->role);

                    if ($request->hasAny(['position', 'role', 'study_program_id'])) {
                        if ($pos === 'Dosen') {
                            if ($role !== 'Staff') {
                                $validator->errors()->add('role', 'For Dosen position, role must be Staff.');
                            }
                            if (!$request->filled('study_program_id')) {
                                $validator->errors()->add('study_program_id', 'Study program is required for Dosen.');
                            }
                        } elseif ($pos === 'Tendik') {
                            $allowed = ['BAAK', 'Staff'];
                            if (!in_array($role, $allowed)) {
                                $validator->errors()->add('role', 'For Tendik position, role must be BAAK or Staff.');
                            }
                        } elseif ($pos === 'Rumah Tangga') {
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
                    ], 422);
                }

                // Prepare update data
                $data = $validator->validated();

                if (isset($data['position']) && $data['position'] !== 'Dosen') {
                    $data['study_program_id'] = null;
                }

                if (isset($data['position']) && $data['position'] !== 'Rumah Tangga') {
                    $data['phone_number'] = null;
                }

                if ($request->hasFile('avatar')) {
                    $data['avatar'] = $request->file('avatar')->store('avatars', 'public');
                }

                // Perform update
                $user->update($data);

                return response()->json([
                    'status' => 'success',
                    'message' => 'User updated successfully',
                    'data' => $user,
                ], 200);

            } else {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Unauthorized access',
                ], 401);
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
