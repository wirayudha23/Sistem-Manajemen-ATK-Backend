<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RoleMiddleware
{
    public function handle(Request $request, Closure $next, ...$roles)
    {
        if (Auth::guard('sanctum')->user() === null) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        if ($roles && !in_array(Auth::guard('sanctum')->user()->role, $roles)) {
            return response()->json([
                'message' => 'Forbidden'
            ], 403);
        }

        return $next($request);
    }
}
