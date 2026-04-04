<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Hostvim Yönetim Girişi</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script>
        (function () {
            var t = localStorage.getItem('hv-theme');
            if (t === 'dark') document.documentElement.classList.add('dark');
            else if (t === 'light') document.documentElement.classList.remove('dark');
            else if (window.matchMedia('(prefers-color-scheme: dark)').matches) document.documentElement.classList.add('dark');
        })();
    </script>
</head>
<body class="min-h-full bg-slate-50 text-slate-800 antialiased dark:bg-slate-950 dark:text-slate-200">
    <div class="pointer-events-none fixed inset-0 overflow-hidden">
        <div class="pointer-events-none absolute -top-32 left-1/2 h-72 w-[36rem] -translate-x-1/2 rounded-full bg-orange-200/50 blur-3xl dark:bg-orange-500/10"></div>
    </div>

    <div class="relative z-10 mx-auto flex min-h-screen max-w-md flex-col justify-center px-5">
        <div class="mb-6 flex items-center justify-between gap-3">
            <div class="flex items-center gap-3">
                <div class="flex h-10 w-10 items-center justify-center rounded-2xl bg-orange-500/15 ring-1 ring-orange-500/35 dark:bg-orange-500/10 dark:ring-orange-400/40">
                    <span class="text-lg font-bold text-orange-600 dark:text-orange-400">H</span>
                </div>
                <div>
                    <div class="text-sm font-semibold text-slate-900 dark:text-slate-100">Hostvim</div>
                    <div class="text-xs text-slate-500 dark:text-slate-500">Yönetim paneli</div>
                </div>
            </div>
            <x-theme-toggle />
        </div>

        <div class="rounded-2xl border border-slate-200/90 bg-white/95 p-6 shadow-xl backdrop-blur dark:border-slate-800 dark:bg-slate-900/85">
            <h1 class="text-lg font-semibold text-slate-900 dark:text-slate-50">Giriş yap</h1>
            <p class="mt-1 text-xs text-slate-600 dark:text-slate-400">Sadece yetkili yönetici hesapları erişebilir.</p>

            @if (session('error'))
                <div class="mt-4 rounded-xl border border-rose-500/40 bg-rose-500/10 px-3 py-2 text-sm text-rose-800 dark:text-rose-200">
                    {{ session('error') }}
                </div>
            @endif

            <form method="POST" action="{{ route('admin.login.store') }}" class="mt-6 space-y-4">
                @csrf
                <div>
                    <label for="email" class="block text-xs font-medium text-slate-700 dark:text-slate-300">E-posta</label>
                    <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="username"
                           class="mt-1 w-full rounded-xl border border-slate-300/90 bg-white px-3 py-2 text-sm text-slate-900 outline-none ring-0 focus:border-orange-500 focus:ring-1 focus:ring-orange-500/40 dark:border-slate-700/80 dark:bg-slate-900/60 dark:text-slate-100 dark:focus:border-orange-500/60" />
                    @error('email')
                        <p class="mt-1 text-xs text-rose-600 dark:text-rose-300">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label for="password" class="block text-xs font-medium text-slate-700 dark:text-slate-300">Şifre</label>
                    <input id="password" name="password" type="password" required autocomplete="current-password"
                           class="mt-1 w-full rounded-xl border border-slate-300/90 bg-white px-3 py-2 text-sm text-slate-900 outline-none focus:border-orange-500 focus:ring-1 focus:ring-orange-500/40 dark:border-slate-700/80 dark:bg-slate-900/60 dark:text-slate-100 dark:focus:border-orange-500/60" />
                    @error('password')
                        <p class="mt-1 text-xs text-rose-600 dark:text-rose-300">{{ $message }}</p>
                    @enderror
                </div>
                <label class="flex items-center gap-2 text-xs text-slate-600 dark:text-slate-400">
                    <input type="checkbox" name="remember" class="rounded border-slate-400 bg-white text-orange-600 focus:ring-orange-500/40 dark:border-slate-600 dark:bg-slate-900" />
                    Beni hatırla
                </label>
                <button type="submit" class="w-full rounded-full bg-orange-500 px-4 py-2.5 text-sm font-semibold text-white shadow-lg shadow-orange-500/25 hover:bg-orange-600 dark:shadow-orange-900/30">
                    Giriş yap
                </button>
            </form>
        </div>

        <p class="mt-6 text-center text-xs text-slate-500 dark:text-slate-500">
            <a href="{{ route('landing.home') }}" class="font-medium text-orange-600 hover:text-orange-500 dark:text-orange-400 dark:hover:text-orange-300">← Siteye dön</a>
        </p>
    </div>
</body>
</html>
