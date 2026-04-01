<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnforceVendorHost
{
    public function handle(Request $request, Closure $next): Response
    {
        $allowed = config('panelsar.vendor_portal_hosts', []);
        if (! is_array($allowed) || count($allowed) === 0) {
            if (app()->environment('production')) {
                return response()->json([
                    'message' => 'Vendor portal host allowlist is not configured.',
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
