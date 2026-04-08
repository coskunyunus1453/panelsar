<x-site.layout title="Şifre sıfırlama" description="E-posta ile sıfırlama bağlantısı alın">
    <div class="hv-container max-w-md py-12">
        <div class="rounded-2xl border border-slate-200/90 bg-white/90 p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900/50">
            <h1 class="text-xl font-semibold text-slate-900 dark:text-slate-50">Şifremi unuttum</h1>
            <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">Kayıtlı e-posta adresinize sıfırlama bağlantısı gönderilir.</p>

            @if (session('status'))
                <div class="mt-4 rounded-xl border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-900 dark:border-emerald-900/40 dark:bg-emerald-950/40 dark:text-emerald-100">
                    {{ session('status') }}
                </div>
            @endif

            <form method="post" action="{{ route('password.email') }}" class="mt-6 space-y-4">
                @csrf
                <div>
                    <label for="email" class="block text-sm font-medium text-slate-700 dark:text-slate-300">E-posta</label>
                    <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="username"
                           class="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-900" />
                    @error('email')
                        <p class="mt-1 text-sm text-rose-600 dark:text-rose-300">{{ $message }}</p>
                    @enderror
                </div>
                <button type="submit" class="w-full rounded-xl bg-[rgb(var(--hv-brand-600)/1)] px-4 py-2.5 text-sm font-semibold text-white hover:opacity-95">
                    Bağlantı gönder
                </button>
            </form>

            <p class="mt-6 text-center text-sm">
                <a href="{{ route('login') }}" class="font-medium text-[rgb(var(--hv-brand-600)/1)] hover:underline">← Girişe dön</a>
            </p>
        </div>
    </div>
</x-site.layout>
