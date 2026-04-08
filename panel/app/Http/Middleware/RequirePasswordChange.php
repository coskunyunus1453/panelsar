<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequirePasswordChange
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->force_password_change) {
            return $next($request);
        }

        if (
            $request->is('api/auth/me') ||
            $request->is('api/auth/logout') ||
            $request->is('api/user/password')
        ) {
            return $next($request);
        }

        return response()->json([
            'message' => 'İlk giriş sonrası şifrenizi değiştirmeniz gerekiyor.',
            'code' => 'password_change_required',
        ], 423);
    }
}
