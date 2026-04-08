<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserNotCommunityBanned
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->isCommunityBanned()) {
            if ($request->expectsJson()) {
                abort(403, 'Topluluk erişiminiz kısıtlandı.');
            }

            return redirect()
                ->route('community.index')
                ->with('error', 'Toplulukta gönderi yapmanız yönetici tarafından kısıtlandı. Sorularınız için destek ile iletişime geçin.');
        }

        return $next($request);
    }
}
