<?php

namespace App\Http\Controllers\Admin\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function create(): RedirectResponse|View
    {
        if (Auth::check()) {
            if (Auth::user()?->is_admin) {
                return redirect()->route('admin.dashboard');
            }

            return redirect()
                ->route('landing.home')
                ->with('error', 'Yönetici paneli yalnızca yetkili hesaplar içindir.');
        }

        return view('admin.auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $remember = $request->boolean('remember');

        // Yalnızca is_admin kullanıcıları veritabanı sorgusuna dahil edilir (yanlış oturum açılmaz)
        if (! Auth::attempt([
            'email' => $credentials['email'],
            'password' => $credentials['password'],
            'is_admin' => true,
        ], $remember)) {
            throw ValidationException::withMessages([
                'email' => __('Giriş bilgileri geçersiz veya yönetici değilsiniz.'),
            ]);
        }

        $request->session()->regenerate();

        return redirect()->intended(route('admin.dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }
}
