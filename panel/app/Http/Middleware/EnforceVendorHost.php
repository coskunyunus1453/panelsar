<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnforceVendorHost
{
    public function handle(Request $request, Closure $next): Response
    {
        $allowed = config('hostvim.vendor_portal_hosts', []);
        if (! is_array($allowed)) {
            $allowed = [];
        }
        // Tek sunucu / IP kurulumda .env’de liste yoksa APP_URL hostunu kabul et (503 yerine çalışır).
        if (count($allowed) === 0) {
            $appHost = parse_url((string) config('app.url'), PHP_URL_HOST);
            if (is_string($appHost) && $appHost !== '') {
                $allowed = [strtolower($appHost)];
            }
        }
        if (count($allowed) === 0) {
            if (app()->environment('production')) {
                return response()->json([
                    'message' => 'Vendor portal host allowlist is not configured. Set VENDOR_PORTAL_HOSTS or a valid APP_URL host.',
                    'code' => 'vendor_host_allowlist_missing',
                ], 503);
            }

            return $next($request);
        }

        $host = strtolower((string) $request->getHost());
        $allowedHosts = array_map(static fn ($h) => strtolower((string) $h), $allowed);
        if (! in_array($host, $allowedHosts, true)) {
            return response()->json([
                'message' => 'Vendor portal host policy violation.',
                'code' => 'vendor_host_forbidden',
            ], 403);
        }

        return $next($request);
    }
}
