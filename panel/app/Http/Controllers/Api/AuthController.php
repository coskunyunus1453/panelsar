<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TwoFactorBackupCode;
use App\Models\User;
use App\Services\TotpService;
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
            'portal' => 'nullable|in:customer,vendor',
            'otp' => 'nullable|digits:6',
            'backup_code' => ['nullable', 'string', 'regex:/^(\d{5}-\d{5}|\d{10})$/'],
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

        $portal = (string) ($request->input('portal', 'customer'));
        $vendorEnabled = (bool) config('hostvim.vendor_enabled', false);
        if ($portal === 'vendor' && ! $vendorEnabled) {
            return response()->json([
                'message' => 'Vendor panel bu kurulum profilinde aktif degil.',
                'code' => 'vendor_profile_disabled',
            ], 403);
        }
        if ($portal === 'vendor') {
            $allowedHosts = config('hostvim.vendor_portal_hosts', []);
            if (is_array($allowedHosts) && count($allowedHosts) > 0) {
                $host = strtolower((string) $request->getHost());
                $normalized = array_map(static fn ($h) => strtolower((string) $h), $allowedHosts);
                if (! in_array($host, $normalized, true)) {
                    return response()->json([
                        'message' => 'Vendor portal host policy violation.',
                        'code' => 'vendor_host_forbidden',
                    ], 403);
                }
            }
        }
        if ($portal === 'vendor' && ! $user->isVendorOperator()) {
            return response()->json([
                'message' => 'Bu hesap vendor paneline yetkili degil.',
                'code' => 'vendor_access_denied',
            ], 403);
        }
        if ($portal === 'customer' && $user->isVendorAdmin() && ! $user->isAdmin()) {
            return response()->json([
                'message' => 'Bu hesap sadece vendor panelinden giris yapabilir.',
                'code' => 'use_vendor_login',
            ], 403);
        }

        $requiresTwoFactor = (bool) $user->two_factor_enabled;
        $tokenName = 'panel-token';

        if ($requiresTwoFactor) {
            // Secret yoksa 2FA doğrulama yapılamaz; kullanıcıyı kilitlememek için OTP istenmeden token veriyoruz.
            // Admin/vendor uçlarında middleware token adı kontrol ettiği için (panel-token-2fa değilse) yetki yine de bloklanır.
            if (! $user->two_factor_secret) {
                $tokenName = 'panel-token';
            } else {
                $otp = $request->input('otp');
                $backupCode = $request->input('backup_code');

                if (! $otp && ! $backupCode) {
                    return response()->json([
                        'message' => '2FA kodu gerekli.',
                        'code' => 'twofa_required',
                        'two_factor_enabled' => true,
                    ], 423);
                }

                $totp = app(TotpService::class);
                $otpOk = false;
                $backupOk = false;

                if ($otp) {
                    $otpOk = $totp->verifyCode((string) $user->two_factor_secret, (string) $otp, 1, 30, 6);
                }

                if ($backupCode && ! $otpOk) {
                    $normalized = (string) $backupCode;
                    if (! str_contains($normalized, '-')) {
                        $normalized = substr($normalized, 0, 5).'-'.substr($normalized, 5, 5);
                    }

                    $rows = TwoFactorBackupCode::query()
                        ->where('user_id', $user->id)
                        ->whereNull('used_at')
                        ->get();

                    foreach ($rows as $row) {
                        if (Hash::check($normalized, $row->code_hash)) {
                            $row->used_at = now();
                            $row->save();
                            $backupOk = true;
                            break;
                        }
                    }
                }

                if (! $otpOk && ! $backupOk) {
                    return response()->json([
                        'message' => '2FA kodu gecersiz.',
                        'code' => 'twofa_invalid_code',
                    ], 422);
                }

                $tokenName = 'panel-token-2fa';
            }
        }

        $expiresAt = now()->addHours(24);
        $abilities = $user->sanctumAbilities();
        $token = $user->createToken($tokenName, $abilities, $expiresAt);

        $userPayload = $user->load('roles')->toArray();
        $userPayload['abilities'] = $abilities;

        return response()->json([
            'user' => $userPayload,
            'token' => $token->plainTextToken,
            'expires_at' => $expiresAt,
            'enforce_admin_2fa' => (bool) config('hostvim.enforce_admin_2fa', true),
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
            'enforce_admin_2fa' => (bool) config('hostvim.enforce_admin_2fa', true),
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
