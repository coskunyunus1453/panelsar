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
        $token = $user->createToken('panel-token', $this->getAbilities($user), $expiresAt);

        return response()->json([
            'user' => $user->load('roles'),
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
        return response()->json([
            'user' => $request->user()->load(['roles', 'hostingPackage']),
        ]);
    }

    public function refresh(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->currentAccessToken()->delete();
        $expiresAt = now()->addHours(24);
        $token = $user->createToken('panel-token', $this->getAbilities($user), $expiresAt);

        return response()->json([
            'token' => $token->plainTextToken,
            'expires_at' => $expiresAt,
        ]);
    }

    private function getAbilities(User $user): array
    {
        if ($user->isAdmin()) {
            return ['*'];
        }

        if ($user->isReseller()) {
            return array_merge($this->customerPanelAbilities(), [
                'users:manage',
                'packages:manage',
            ]);
        }

        return $this->customerPanelAbilities();
    }

    /**
     * @return list<string>
     */
    private function customerPanelAbilities(): array
    {
        return [
            'access:customer-panel',
            'domains:read', 'domains:create',
            'databases:read', 'databases:create',
            'email:read', 'email:create',
            'ftp:read', 'ftp:create',
            'ssl:read', 'cron:read', 'cron:create',
            'backup:read', 'backup:create',
        ];
    }
}
