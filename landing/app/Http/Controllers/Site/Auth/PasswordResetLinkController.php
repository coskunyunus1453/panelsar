<?php

namespace App\Http\Controllers\Site\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\View\View;

class PasswordResetLinkController extends Controller
{
    public function create(): View
    {
        return view('site.auth.forgot-password');
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'email:rfc'],
        ]);

        $status = Password::sendResetLink(
            $request->only('email')
        );

        if ($status === Password::RESET_LINK_SENT) {
            return back()->with('status', 'Şifre sıfırlama bağlantısı e-posta adresinize gönderildi.');
        }

        return back()->withErrors([
            'email' => $status === Password::INVALID_USER
                ? 'Bu e-posta ile kayıtlı hesap bulunamadı.'
                : 'İşlem tamamlanamadı. Lütfen bir süre sonra tekrar deneyin.',
        ]);
    }
}
