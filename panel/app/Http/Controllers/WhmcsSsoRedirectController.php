<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * WHMCS SSO: tarayıcı dostu URL → SPA (?sso=) yönlendirmesi.
 */
class WhmcsSsoRedirectController extends Controller
{
    public function redirect(Request $request): RedirectResponse|\Illuminate\Http\Response
    {
        $t = (string) $request->query('t', '');
        if ($t === '' || ! Str::isUuid($t)) {
            abort(404, 'Geçersiz bağlantı.');
        }
        if (! Cache::has('whmcs_sso:'.$t)) {
            abort(410, 'Bağlantının süresi doldu veya zaten kullanıldı.');
        }

        $base = (string) config('hostvim.whmcs_integration.sso_redirect_base', '');
        if ($base === '') {
            $base = rtrim((string) config('app.url'), '/').'/admin';
        }

        $sep = str_contains($base, '?') ? '&' : '?';

        return redirect()->away($base.$sep.'sso='.rawurlencode($t));
    }
}
