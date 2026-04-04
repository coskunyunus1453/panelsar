<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsAdmin
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->is_admin) {
            if ($request->expectsJson()) {
                abort(403);
            }

            return redirect()
                ->route('admin.login')
                ->with('error', 'Bu alana erişim için yönetici yetkisi gerekir.');
        }

        return $next($request);
    }
}
