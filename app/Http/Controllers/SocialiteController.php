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
        return Socialite::driver('google')->redirect();
    }

    public function callback()
    {
        $userFromGoogle = Socialite::driver('google')->stateless()->user();
        $email = $userFromGoogle->getEmail();

        if (!str_ends_with($email, '@gmail.com')) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized',
                'errors' => ['email' => ['Only Gmail account is allowed']],
            ], 401);
        }

        $userFromDb = User::where('email', $email)->first();

        if (!$userFromDb) {
            $userFromDb = new User();
            $userFromDb->google_id = $userFromGoogle->getId();
            $userFromDb->avatar = $userFromGoogle->getAvatar();
            $userFromDb->email = $email;
            $userFromDb->name = $userFromGoogle->getName();
            $userFromDb->role = 'baak';
            $userFromDb->save();
        }

        $token = $userFromDb->createToken('auth_token')->plainTextToken;

        return response()->json([
            'status' => 'success',
            'message' => 'Login successful',
            'access_token' => $token,
            'token_type' => 'Bearer'
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
