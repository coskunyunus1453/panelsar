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

        if (! (bool) $user->two_factor_enabled) {
            return response()->json([
                'message' => 'Vendor islemleri icin admin 2FA zorunludur.',
                'code' => 'admin_2fa_required',
            ], 423);
        }

        // 2FA ile üretilmiş token adı olmadan admin/vendor uçları çalışmaz.
        $token = $user->currentAccessToken();
        if (! $token || $token->name !== 'panel-token-2fa') {
            return response()->json([
                'message' => 'Vendor islemleri icin admin 2FA zorunludur.',
                'code' => 'admin_2fa_required',
            ], 423);
        }

        return $next($request);
    }
}
