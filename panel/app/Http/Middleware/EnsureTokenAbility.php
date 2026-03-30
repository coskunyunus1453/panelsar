<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTokenAbility
{
    /**
     * Sanctum token yeteneği (* = tam yetki).
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string $ability): Response
    {
        $user = $request->user();
        if ($user === null) {
            abort(401);
        }

        if ($user->tokenCan('*')) {
            return $next($request);
        }

        if (! $user->tokenCan($ability)) {
            abort(403, __('auth.ability_denied', ['ability' => $ability]));
        }

        return $next($request);
    }
}
