<?php

namespace App\Http\Controllers\Site\Auth;

use App\Http\Controllers\Controller;
use App\Support\SafeInternalRedirect;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function create(Request $request): RedirectResponse|View
    {
        if (Auth::check()) {
            return redirect()->intended(route('community.index'));
        }

        return view('site.auth.login', [
            'redirect' => SafeInternalRedirect::path($request->query('redirect')),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'string', 'email:rfc'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt([
            'email' => $credentials['email'],
            'password' => $credentials['password'],
        ], $request->boolean('remember'))) {
            throw ValidationException::withMessages([
                'email' => 'Bu e-posta ve şifre ile eşleşen bir hesap bulunamadı.',
            ]);
        }

        $request->session()->regenerate();

        $to = SafeInternalRedirect::path($request->input('redirect'));
        if ($to) {
            return redirect()->to($to);
        }

        return redirect()->intended(route('community.index'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('landing.home')->with('status', 'Oturumunuz kapatıldı.');
    }
}
