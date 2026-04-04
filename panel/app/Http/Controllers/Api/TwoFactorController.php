<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TwoFactorBackupCode;
use App\Models\User;
use App\Services\TotpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class TwoFactorController extends Controller
{
    private function getIssuer(User $user): string
    {
        return (string) config('app.name', 'Hostvim');
    }

    private function makeBackupCode(): string
    {
        // Kullanıcıya gösterilecek format: 5 haneli-5 haneli (örn: 48291-73905)
        $raw = (string) random_int(0, 9999999999);
        $raw = str_pad($raw, 10, '0', STR_PAD_LEFT);

        return substr($raw, 0, 5).'-'.substr($raw, 5, 5);
    }

    public function status(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'two_factor_enabled' => (bool) $user->two_factor_enabled,
        ]);
    }

    public function setup(Request $request, TotpService $totp): JsonResponse
    {
        $user = $request->user();

        $secret = $totp->generateSecret(20);
        $user->two_factor_secret = $secret;
        $user->two_factor_enabled = false;
        $user->save();

        TwoFactorBackupCode::query()
            ->where('user_id', $user->id)
            ->delete();

        $issuer = $this->getIssuer($user);
        $label = rawurlencode($issuer.':'.$user->email);

        // TOTP URI: otpauth://totp/<label>?secret=...&issuer=...&algorithm=SHA1&digits=6&period=30
        $otpauth = sprintf(
            'otpauth://totp/%s?secret=%s&issuer=%s&algorithm=SHA1&digits=6&period=30',
            $label,
            $secret,
            rawurlencode($issuer)
        );

        return response()->json([
            'two_factor_enabled' => false,
            'otpauth_url' => $otpauth,
            // Frontend, kullanıcının kopyalayabilmesi için secret’i gösterebilir.
            'secret' => $secret,
        ]);
    }

    public function verify(Request $request, TotpService $totp): JsonResponse
    {
        $user = $request->user();

        $payload = $request->validate([
            'otp' => ['required', 'string', 'regex:/^\d{6}$/'],
        ]);

        if (! $user->two_factor_secret) {
            return response()->json([
                'message' => 'TOTP secret bulunamadi. Lutfen yeniden kurun.',
                'code' => 'two_factor_secret_missing',
            ], 409);
        }

        $otp = (string) $payload['otp'];
        $ok = $totp->verifyCode((string) $user->two_factor_secret, $otp, 1, 30, 6);
        if (! $ok) {
            return response()->json([
                'message' => '2FA kodu gecersiz.',
                'code' => 'two_factor_invalid_code',
            ], 422);
        }

        $user->two_factor_enabled = true;
        $user->save();

        TwoFactorBackupCode::query()
            ->where('user_id', $user->id)
            ->delete();

        $codes = [];
        $rows = [];
        for ($i = 0; $i < 8; $i++) {
            $code = $this->makeBackupCode();
            $codes[] = $code;
            $rows[] = [
                'user_id' => $user->id,
                'code_hash' => Hash::make($code),
                'used_at' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        TwoFactorBackupCode::query()->insert($rows);

        return response()->json([
            'two_factor_enabled' => true,
            'backup_codes' => $codes,
        ]);
    }

    public function regenerateBackupCodes(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! (bool) $user->two_factor_enabled) {
            return response()->json([
                'message' => '2FA aktif degil. Once kurulum yapin.',
                'code' => 'two_factor_not_enabled',
            ], 409);
        }

        TwoFactorBackupCode::query()
            ->where('user_id', $user->id)
            ->delete();

        $codes = [];
        $rows = [];
        for ($i = 0; $i < 8; $i++) {
            $code = $this->makeBackupCode();
            $codes[] = $code;
            $rows[] = [
                'user_id' => $user->id,
                'code_hash' => Hash::make($code),
                'used_at' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        TwoFactorBackupCode::query()->insert($rows);

        return response()->json([
            'backup_codes' => $codes,
        ]);
    }
}
