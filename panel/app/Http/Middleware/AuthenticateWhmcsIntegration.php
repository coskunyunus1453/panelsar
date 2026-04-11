<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateWhmcsIntegration
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = (string) config('hostvim.whmcs_integration.secret', '');
        if ($expected === '') {
            abort(response()->json([
                'message' => 'WHMCS entegrasyonu yapılandırılmadı (HOSTVIM_WHMCS_SECRET).',
            ], 503));
        }

        $auth = (string) $request->header('Authorization', '');
        $token = '';
        if (str_starts_with($auth, 'Bearer ')) {
            $token = trim(substr($auth, 7));
        } elseif ($auth !== '') {
            $token = trim($auth);
        }

        if ($token === '') {
            $token = trim((string) $request->header('X-Hostvim-Integration', ''));
        }

        if ($token === '' || ! hash_equals($expected, $token)) {
            abort(response()->json([
                'message' => 'Geçersiz entegrasyon kimlik doğrulaması.',
            ], 401));
        }

        return $next($request);
    }
}
