<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;

class SocialiteController extends Controller
{
    public function redirect()
    {
        return Socialite::driver('google')
            ->stateless()
            ->with([
                'access_type' => 'offline',
                'prompt' => 'select_account',
            ])
            ->redirect();
    }

    public function callback()
    {
        $googleUser = Socialite::driver('google')->stateless()->user();
        $email = $googleUser->getEmail();

        // 1) Cek whitelist (email + role sudah diinput admin)
        $user = User::where('email', $email)->first();
        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized - Email not registered',
            ], 401);
        }

        if ($user->role === 'Staff') {
            return response()->json([
                'status' => 'error',
                'message' => 'Maaf, Anda tidak memiliki akses untuk masuk ke sistem ini.',
            ], 403);
        }

        // 2) Update data Google (ID, name, avatar)
        if (!$user->google_id) {
            $user->google_id = $googleUser->getId();
            $user->name = $googleUser->getName();
            $user->avatar = $googleUser->getAvatar();
        }

        $user->save();

        // 3) Issue Sanctum token
        $token = $user->createToken('auth_token')->plainTextToken;

        // 4) Return JSON (tersedia juga role untuk frontend)
        return response()->json([
            'status' => 'success',
            'message' => 'Login successful',
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => $user,
        ], 200);
    }

    public function authorize()
    {
        $user = Auth::guard('sanctum')->user();
        return response()->json([
            'status' => 'success',
            'message' => 'Authorized',
            'user' => $user
        ], 200);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json([
            'status' => 'success',
            'message' => 'Logged out successfully'
        ], 200);
    }
}
