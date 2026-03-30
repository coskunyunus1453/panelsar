<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => [__('auth.failed')],
            ]);
        }

        if ($user->status !== 'active') {
            return response()->json(['message' => __('auth.suspended')], 403);
        }

        $expiresAt = now()->addHours(24);
        $abilities = $user->sanctumAbilities();
        $token = $user->createToken('panel-token', $abilities, $expiresAt);

        $userPayload = $user->load('roles')->toArray();
        $userPayload['abilities'] = $abilities;

        return response()->json([
            'user' => $userPayload,
            'token' => $token->plainTextToken,
            'expires_at' => $expiresAt,
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => __('auth.logged_out')]);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();
        $userPayload = $user->load(['roles', 'hostingPackage'])->toArray();
        $userPayload['abilities'] = $user->sanctumAbilities();

        return response()->json([
            'user' => $userPayload,
        ]);
    }

    public function refresh(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->currentAccessToken()->delete();
        $expiresAt = now()->addHours(24);
        $abilities = $user->sanctumAbilities();
        $token = $user->createToken('panel-token', $abilities, $expiresAt);

        return response()->json([
            'token' => $token->plainTextToken,
            'expires_at' => $expiresAt,
            'abilities' => $abilities,
        ]);
    }
}
