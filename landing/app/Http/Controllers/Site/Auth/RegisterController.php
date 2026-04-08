<?php

namespace App\Http\Controllers\Site\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\SafeInternalRedirect;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class RegisterController extends Controller
{
    public function create(Request $request): RedirectResponse|View
    {
        if (Auth::check()) {
            return redirect()->route('community.index');
        }

        return view('site.auth.register', [
            'redirect' => SafeInternalRedirect::path($request->query('redirect')),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'hv_company' => ['nullable', 'string', 'max:0'],
            'name' => ['required', 'string', 'max:100', 'regex:/^[\p{L}\p{M}\s\-\'.]+$/u'],
            'email' => ['required', 'string', 'lowercase', 'email:rfc', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(10)->mixedCase()->numbers()],
        ], [
            'name.regex' => 'Ad yalnızca harf, boşluk ve tire içerebilir.',
        ]);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
        ]);

        event(new Registered($user));

        Auth::login($user);

        $request->session()->regenerate();

        $to = SafeInternalRedirect::path($request->input('redirect'));
        if ($to) {
            return redirect()->to($to)->with('status', 'Hesabınız oluşturuldu. Topluluğa hoş geldiniz.');
        }

        return redirect()->route('community.index')->with('status', 'Hesabınız oluşturuldu. Topluluğa hoş geldiniz.');
    }
}
