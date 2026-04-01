<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecureHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'geolocation=(), microphone=(), camera=(), payment=()');
        $response->headers->set('Cross-Origin-Opener-Policy', 'same-origin');
        $response->headers->set('Cross-Origin-Resource-Policy', 'same-origin');

        if (! $response->headers->has('Content-Security-Policy')) {
            $response->headers->set(
                'Content-Security-Policy',
                "default-src 'self'; ".
                "script-src 'self' 'unsafe-eval' https://fonts.googleapis.com; ".
                "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; ".
                "font-src 'self' https://fonts.gstatic.com data:; ".
                "img-src 'self' data: https:; ".
                "connect-src 'self' ws: wss: https:; ".
                "frame-ancestors 'self'; base-uri 'self'; form-action 'self'; object-src 'none'"
            );
        }

        if ($request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }
}
