<?php

namespace App\Http\Controllers;

use App\Exports\UserTemplate;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Log;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;
use App\Exports\UserExport;
use Maatwebsite\Excel\Facades\Excel;

class UserController extends Controller
{

    public function index(Request $request)
    {
        try {
            // Pagination default: 10 items per page
            $limit = 10;
            $page = $request->get('page', 1);

            // Sorting
            $sort_column = 'name';
            $sort_type = 'asc';

            // Search & filter parameters
            $search = $request->get('search', '');
            $search_column = $request->get('search_column', '');
            $role = $request->get('role', null);  // tambahkan parameter role
            $position = $request->get('position', null); // tambahkan parameter posisi

            $query = User::query();

            // Filter berdasarkan role jika ada
            if ($role) {
                $query->where('role', $role);
            }

            // Filter berdasarkan posisi jika ada
            if ($position) {
                $query->where('position', $position);
            }

            // Apply search filter
            if ($search_column && $search) {
                $query->where($search_column, 'like', "%{$search}%");
            } elseif ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('role', 'like', "%{$search}%");
                });
            }

            // Paginate hasil yang sudah difilter dan di-sort
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

            $messages = [
                'name.required' => 'Nama wajib diisi.',
                'name.string' => 'Nama harus berupa teks.',
                // 'name.unique' => 'Nama sudah digunakan.',

                'email.required' => 'Email wajib diisi.',
                'email.email' => 'Format email tidak valid.',
                'email.unique' => 'Email sudah terdaftar.',

                'nip.required' => 'NIP wajib diisi.',
                'nip.digits' => 'NIP harus terdiri dari :digits digit.',
                'nip.integer' => 'NIP harus berupa angka.',
                'nip.unique' => 'NIP sudah terdaftar.',

                'position.required' => 'Posisi wajib dipilih.',
                'position.in' => 'Posisi yang dipilih tidak valid.',

                'initial.required' => 'Inisial wajib diisi.',
                'initial.alpha' => 'Inisial hanya boleh huruf.',
                'initial.size' => 'Inisial harus tepat 3 huruf.',
                'initial.unique' => 'Inisial sudah digunakan.',

                'role.required' => 'Role wajib dipilih.',
                'role.in' => 'Role yang dipilih tidak sesuai dengan posisi.',

                'phone_number.required_if' => 'No. handphone wajib diisi untuk posisi Rumah Tangga.',
                'phone_number.string' => 'No. handphone harus berupa angka.',
                'phone_number.min' => 'No. handphone minimal :min karakter.',
                'phone_number.max' => 'No. handphone maksimal :max karakter.',
                'phone_number.unique' => 'No. handphone sudah terdaftar.',
                'phone_number.regex' => 'No. handphone harus diawali 08 dan berisi 11–12 angka.',

                'study_program_id.required_if' => 'Program studi wajib dipilih untuk posisi Dosen.',
                'study_program_id.exists' => 'Program studi yang dipilih tidak ditemukan.',

                'avatar.image' => 'Avatar harus berupa file gambar.',
                'avatar.mimes' => 'Avatar hanya boleh berformat: jpeg, png, jpg, gif, svg.',
                'avatar.max' => 'Ukuran avatar maksimal :max kilobyte.',
            ];


            $validator = Validator::make(
                $request->all(),
                $rules,
                $messages
            );

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation error',
                    'errors' => $validator->errors(),
                ], 422);
            }

            // Retrieve validated data
            $data = $validator->validated();

            if (($data['position'] ?? null) !== 'Staff') {
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
                'message' => 'Pengguna berhasil ditambahkan',
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

            // Jika role saat ini adalah Kabag
            if ($currentUser->role === 'Kabag') {
                // Hanya boleh mengedit pengguna dengan role BAAK
                if ($user->role !== 'BAAK') {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Kabag hanya dapat mengedit pengguna dengan role BAAK.',
                    ], 403);
                }

                // Reuse validasi BAAK, kecuali field position
                $rules = [
                    'name' => ['sometimes', 'string'],
                    'email' => ['sometimes', 'email', Rule::unique('users', 'email')->ignore($user->id)],
                    'nip' => ['sometimes', 'digits:6', 'integer', Rule::unique('users', 'nip')->ignore($user->id)],
                    'initial' => ['sometimes', 'alpha', 'size:3', Rule::unique('users', 'initial')->ignore($user->id)],
                    'role' => ['sometimes', Rule::in(['Kabag'])],
                    'study_program_id' => ['sometimes', 'nullable', Rule::exists('study_programs', 'id')],
                    'avatar' => ['sometimes', 'image', 'mimes:jpeg,png,jpg,gif,svg', 'max:2048'],
                    'phone_number' => [
                        Rule::requiredIf($request->input('position', $user->position) === 'RUmah Tangga'),
                        'nullable',
                        'string',
                        'min:11',
                        'max:12',
                        "regex:/^08\\d{9,10}$/",
                        Rule::unique('users', 'phone_number')->ignore($user->id),
                    ],
                ];

                $messages = [
                    'name.string' => 'Nama harus berupa teks.',
                    'email.email' => 'Format email tidak valid.',
                    'email.unique' => 'Email sudah terdaftar.',
                    'nip.digits' => 'NIP harus terdiri dari :digits digit.',
                    'nip.integer' => 'NIP harus berupa angka.',
                    'nip.unique' => 'NIP sudah terdaftar.',
                    'initial.alpha' => 'Inisial hanya boleh huruf.',
                    'initial.size' => 'Inisial harus tepat :size huruf.',
                    'initial.unique' => 'Inisial sudah digunakan.',
                    'phone_number.required_if' => 'No. handphone wajib diisi untuk posisi Rumah Tangga.',
                    'phone_number.min' => 'No. handphone minimal :min karakter.',
                    'phone_number.max' => 'No. handphone maksimal :max karakter.',
                    'phone_number.regex' => 'No. handphone harus diawali 08 dan panjang 11–12 angka.',
                    'phone_number.unique' => 'No. handphone sudah terdaftar.',
                    'study_program_id.exists' => 'Program studi tidak ditemukan.',
                    'avatar.image' => 'File harus berupa gambar.',
                    'avatar.mimes' => 'Format gambar hanya boleh: jpeg, png, jpg, gif, svg.',
                    'avatar.max' => 'Ukuran gambar maksimal :max kilobyte.',
                ];

                $validator = Validator::make($request->all(), $rules, $messages);

                if ($validator->fails()) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Validation error',
                        'errors' => $validator->errors(),
                    ], 422);
                }

                $data = $validator->validated();

                // Untuk Kabag, posisi tidak boleh diubah, jadi tidak reset study_program_id atau phone_number berdasarkan posisi baru
                // Tetapi jika posisi lama bukan Dosen atau Rumah Tangga, tetap jaga data lama
                // (tidak ada perubahan untuk study_program_id/phone_number di sini)

                if ($request->hasFile('avatar')) {
                    $data['avatar'] = $request->file('avatar')->store('avatars', 'public');
                }

                // Update dalam transaksi atomik
                DB::transaction(function () use ($data, $user, $currentUser) {
                    // Update target user
                    $user->update($data);

                    // Jika Kabag mengubah role menjadi 'Kabag' (promosi ulang BAAK → Kabag), demote Kabag saat ini
                    if (isset($data['role']) && $data['role'] === 'Kabag') {
                        $currentUser->update(['role' => 'Staff']);
                    }
                });

                return response()->json([
                    'status' => 'success',
                    'message' => 'Data pengguna berhasil diperbarui oleh Kabag',
                    'data' => $user->fresh(),
                ], 200);
            }

            // Jika role saat ini adalah BAAK
            elseif ($currentUser->role === 'BAAK') {
                // Sama persis seperti sebelumnya, termasuk after() closure untuk validasi posisi
                $rules = [
                    'name' => ['sometimes', 'string'],
                    'email' => ['sometimes', 'email', Rule::unique('users', 'email')->ignore($user->id)],
                    'nip' => ['sometimes', 'digits:6', 'integer', Rule::unique('users', 'nip')->ignore($user->id)],
                    'initial' => ['sometimes', 'alpha', 'size:3', Rule::unique('users', 'initial')->ignore($user->id)],
                    'position' => ['sometimes', 'string', 'in:Dosen,Tendik,Rumah Tangga'],
                    'role' => ['sometimes', 'string'],
                    'study_program_id' => ['sometimes', 'nullable', Rule::exists('study_programs', 'id')],
                    'avatar' => ['sometimes', 'image', 'mimes:jpeg,png,jpg,gif,svg', 'max:2048'],
                    'phone_number' => [
                        Rule::requiredIf($request->input('position', $user->position) === 'Staff'),
                        'nullable',
                        'string',
                        'min:11',
                        'max:12',
                        "regex:/^08\\d{9,10}$/",
                        Rule::unique('users', 'phone_number')->ignore($user->id),
                    ],
                ];

                $messages = [
                    'name.string' => 'Nama harus berupa teks.',
                    'email.email' => 'Format email tidak valid.',
                    'email.unique' => 'Email sudah terdaftar.',
                    'nip.digits' => 'NIP harus terdiri dari :digits digit.',
                    'nip.integer' => 'NIP harus berupa angka.',
                    'nip.unique' => 'NIP sudah terdaftar.',
                    'initial.alpha' => 'Inisial hanya boleh huruf.',
                    'initial.size' => 'Inisial harus tepat :size huruf.',
                    'initial.unique' => 'Inisial sudah digunakan.',
                    'position.in' => 'Posisi yang dipilih tidak valid.',
                    'phone_number.required_if' => 'No. handphone wajib diisi untuk posisi Rumah Tangga.',
                    'phone_number.min' => 'No. handphone minimal :min karakter.',
                    'phone_number.max' => 'No. handphone maksimal :max karakter.',
                    'phone_number.regex' => 'No. handphone harus diawali 08 dan panjang 11–12 angka.',
                    'phone_number.unique' => 'No. handphone sudah terdaftar.',
                    'study_program_id.exists' => 'Program studi tidak ditemukan.',
                    'avatar.image' => 'File harus berupa gambar.',
                    'avatar.mimes' => 'Format gambar hanya boleh: jpeg, png, jpg, gif, svg.',
                    'avatar.max' => 'Ukuran gambar maksimal :max kilobyte.',
                ];

                $validator = Validator::make($request->all(), $rules, $messages);

                $validator->after(function ($validator) use ($request, $user) {
                    $pos = $request->input('position', $user->position);
                    $role = $request->input('role', $user->role);

                    if ($request->hasAny(['position', 'role', 'study_program_id'])) {
                        if ($pos === 'Dosen') {
                            if ($role !== 'Staff') {
                                $validator->errors()->add('role', 'untuk posisi Dosen, role harus Staff.');
                            }
                            if (!$request->filled('study_program_id')) {
                                $validator->errors()->add('study_program_id', 'Program studi wajib diisi untuk posisi Dosen.');
                            }
                        } elseif ($pos === 'Tendik') {
                            $allowed = ['BAAK', 'Staff'];
                            if (!in_array($role, $allowed)) {
                                $validator->errors()->add('role', 'untuk posisi Tendik, role harus salah satu dari: ' . implode(', ', $allowed) . '.');
                            }
                        } elseif ($pos === 'Rumah Tangga') {
                            if ($role !== 'Staff') {
                                $validator->errors()->add('role', 'untuk posisi Rumah Tangga, role harus Staff.');
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

                $user->update($data);

                return response()->json([
                    'status' => 'success',
                    'message' => 'Data pengguna berhasil diperbarui',
                    'data' => $user,
                ], 200);
            }

            // Unauthorized jika bukan Kabag atau BAAK
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized access',
            ], 401);

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
            if ($user->role === 'Kabag') {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Pengguna dengan role Kabag tidak dapat dihapus',
                ], 403);
            }

            $user->delete();

            return response()->json([
                'status' => 'success',
                'message' => 'Data pengguna berhasil dihapus',
            ], 200);
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Internal server error' . $e->getMessage(),
            ], 500);
        }
    }

    public function publicIndex()
    {
        return User::select('id', 'name', 'email', 'initial', 'position', 'role')->get();
    }

    public function template()
    {
        return Excel::download(new UserTemplate, 'template_user.xlsx');
    }
}
