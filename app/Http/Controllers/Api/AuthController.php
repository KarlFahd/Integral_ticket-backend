<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::where('username', $request->username)->first();

        if ($user === null || ! Hash::check($request->password, $user->password)) {
            return response()->json(['message' => 'Invalid username or password.'], 401);
        }

        // If 2FA is enabled, ask the frontend to prompt for the authenticator code
        if ($user->two_factor_enabled) {
            return response()->json([
                'requires_2fa' => true,
                'username'     => $user->username,
            ]);
        }

        return response()->json([
            'user' => [
                'id'       => $user->id,
                'name'     => $user->name,
                'username' => $user->username,
                'is_admin' => (bool) $user->is_admin,
                'is_hr'    => (bool) $user->is_hr,
            ],
        ]);
    }
}
