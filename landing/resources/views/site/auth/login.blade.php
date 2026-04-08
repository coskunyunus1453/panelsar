<x-site.layout title="Giriş — Topluluk" description="Hesabınızla giriş yapın">
    <div class="hv-container max-w-md py-12">
        <div class="rounded-2xl border border-slate-200/90 bg-white/90 p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900/50">
            <h1 class="text-xl font-semibold text-slate-900 dark:text-slate-50">Giriş yap</h1>
            <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">Toplulukta soru sormak ve yanıtlamak için oturum açın.</p>

            @if (session('status'))
                <div class="mt-4 rounded-xl border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-900 dark:border-emerald-900/40 dark:bg-emerald-950/40 dark:text-emerald-100">
                    {{ session('status') }}
                </div>
            @endif

            <form method="post" action="{{ route('login.store') }}" class="mt-6 space-y-4">
                @csrf
                @if (! empty($redirect ?? null))
                    <input type="hidden" name="redirect" value="{{ $redirect }}" />
                @endif
                <div>
                    <label for="email" class="block text-sm font-medium text-slate-700 dark:text-slate-300">E-posta</label>
                    <input id="email" name="email" type="email" value="{{ old('email') }}" required autocomplete="username"
                           class="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-900" />
                    @error('email')
                        <p class="mt-1 text-sm text-rose-600 dark:text-rose-300">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label for="password" class="block text-sm font-medium text-slate-700 dark:text-slate-300">Şifre</label>
                    <input id="password" name="password" type="password" required autocomplete="current-password"
                           class="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-900" />
                    @error('password')
                        <p class="mt-1 text-sm text-rose-600 dark:text-rose-300">{{ $message }}</p>
                    @enderror
                </div>
                <label class="flex items-center gap-2 text-sm text-slate-600 dark:text-slate-400">
                    <input type="checkbox" name="remember" class="rounded border-slate-400" />
                    Beni hatırla
                </label>
                <button type="submit" class="w-full rounded-xl bg-[rgb(var(--hv-brand-600)/1)] px-4 py-2.5 text-sm font-semibold text-white hover:opacity-95">
                    Giriş yap
                </button>
            </form>

            <div class="mt-6 space-y-2 text-center text-sm text-slate-600 dark:text-slate-400">
                <p><a href="{{ route('password.request') }}" class="font-medium text-[rgb(var(--hv-brand-600)/1)] hover:underline">Şifremi unuttum</a></p>
                <p>Hesabınız yok mu? <a href="{{ route('register', array_filter(['redirect' => $redirect ?? null])) }}" class="font-semibold text-[rgb(var(--hv-brand-600)/1)] hover:underline">Kayıt olun</a></p>
                <p class="pt-2 text-xs text-slate-500">Yönetici misiniz? <a href="{{ route('admin.login') }}" class="font-medium text-slate-700 underline dark:text-slate-300">Panel girişi</a></p>
            </div>
        </div>
    </div>
</x-site.layout>
