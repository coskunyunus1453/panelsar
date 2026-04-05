<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireTwoFactorForAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! (bool) config('hostvim.enforce_admin_2fa', true)) {
            return $next($request);
        }

        $user = $request->user();
        if (! $user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        if (! ($user->isAdmin() || $user->isVendorOperator())) {
            return $next($request);
        }

        // 2FA henüz kurulmadı: panel-token ile ayarlar / ilk kurulum mümkün olsun (423 kilitlenmesi olmasın).
        if (! (bool) $user->two_factor_enabled) {
            return $next($request);
        }

        // 2FA açıkken yalnızca OTP ile verilen token (girişte doğrulama sonrası) kabul edilir.
        $token = $user->currentAccessToken();
        if (! $token || $token->name !== 'panel-token-2fa') {
            return response()->json([
                'message' => '2FA acik: bu islemler icin cikis yapip giris sirasinda dogrulama kodunu girin.',
                'code' => 'admin_2fa_required',
            ], 423);
        }

        return $next($request);
    }
}
