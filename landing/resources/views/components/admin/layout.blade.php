@props([
    'title' => 'Hostvim Yönetim',
])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title }}</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <script>
        (function () {
            var t = localStorage.getItem('hv-theme');
            if (t === 'dark') document.documentElement.classList.add('dark');
            else if (t === 'light') document.documentElement.classList.remove('dark');
            else if (window.matchMedia('(prefers-color-scheme: dark)').matches) document.documentElement.classList.add('dark');
        })();
    </script>
    <style>
        [x-cloak] { display: none !important; }
        body {
            @apply antialiased;
        }
    </style>
</head>
<body class="min-h-screen bg-slate-50 text-slate-800 dark:bg-slate-950 dark:text-slate-200">
    <div class="pointer-events-none fixed inset-0 overflow-hidden">
        <div class="pointer-events-none absolute -top-32 left-1/2 h-72 w-[36rem] -translate-x-1/2 rounded-full bg-orange-400/15 blur-3xl dark:bg-orange-500/10"></div>
    </div>

    <div class="relative z-10 flex min-h-screen">
        <aside class="hidden w-64 flex-col border-r border-slate-200/90 bg-white/95 backdrop-blur-xl dark:border-slate-800/80 dark:bg-slate-950/95 lg:flex">
            <div class="flex h-16 items-center gap-2 border-b border-slate-200/90 px-4 dark:border-slate-800/80">
                <div class="flex h-9 w-9 items-center justify-center rounded-2xl bg-orange-500/15 ring-1 ring-orange-500/35 dark:bg-orange-500/10 dark:ring-orange-400/40">
                    <span class="text-lg font-bold text-orange-600 dark:text-orange-400">H</span>
                </div>
                <div class="leading-tight">
                    <div class="text-sm font-semibold text-slate-900 dark:text-slate-100">Hostvim</div>
                    <div class="text-[11px] text-slate-500">Yönetim</div>
                </div>
            </div>
            <nav class="flex-1 space-y-1 p-3 text-sm">
                <a href="{{ route('admin.dashboard') }}" class="flex items-center gap-2 rounded-xl px-3 py-2 {{ request()->routeIs('admin.dashboard') ? 'bg-orange-500/15 font-medium text-orange-800 ring-1 ring-orange-500/30 dark:text-orange-200' : 'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-900/80' }}">
                    <span class="h-1.5 w-1.5 rounded-full bg-orange-500"></span>
                    Özet
                </a>
                <div class="px-3 pt-2 text-[10px] font-semibold uppercase tracking-wider text-slate-400">Hostvim SaaS</div>
                <a href="{{ route('admin.saas.dashboard') }}" class="flex items-center gap-2 rounded-xl px-3 py-2 {{ request()->routeIs('admin.saas.dashboard') ? 'bg-orange-500/15 font-medium text-orange-800 ring-1 ring-orange-500/30 dark:text-orange-200' : 'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-900/80' }}">
                    Lisans özeti
                </a>
                <a href="{{ route('admin.saas.customers.index') }}" class="flex items-center gap-2 rounded-xl px-3 py-2 {{ request()->routeIs('admin.saas.customers.*') ? 'bg-orange-500/15 font-medium text-orange-800 ring-1 ring-orange-500/30 dark:text-orange-200' : 'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-900/80' }}">
                    Müşteriler
                </a>
                <a href="{{ route('admin.saas.licenses.index') }}" class="flex items-center gap-2 rounded-xl px-3 py-2 {{ request()->routeIs('admin.saas.licenses.*') ? 'bg-orange-500/15 font-medium text-orange-800 ring-1 ring-orange-500/30 dark:text-orange-200' : 'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-900/80' }}">
                    Lisanslar
                </a>
                <a href="{{ route('admin.saas.products.index') }}" class="flex items-center gap-2 rounded-xl px-3 py-2 {{ request()->routeIs('admin.saas.products.*') ? 'bg-orange-500/15 font-medium text-orange-800 ring-1 ring-orange-500/30 dark:text-orange-200' : 'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-900/80' }}">
                    Ürünler (tier)
                </a>
                <a href="{{ route('admin.saas.modules.index') }}" class="flex items-center gap-2 rounded-xl px-3 py-2 {{ request()->routeIs('admin.saas.modules.*') ? 'bg-orange-500/15 font-medium text-orange-800 ring-1 ring-orange-500/30 dark:text-orange-200' : 'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-900/80' }}">
                    Modüller
                </a>
                <a href="{{ route('admin.billing-settings.edit') }}" class="flex items-center gap-2 rounded-xl px-3 py-2 {{ request()->routeIs('admin.billing-settings.*') ? 'bg-orange-500/15 font-medium text-orange-800 ring-1 ring-orange-500/30 dark:text-orange-200' : 'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-900/80' }}">
                    Ödeme yöntemleri
                </a>
                <a href="{{ route('admin.site-settings.edit') }}" class="flex items-center gap-2 rounded-xl px-3 py-2 {{ request()->routeIs('admin.site-settings.*') ? 'bg-orange-500/15 font-medium text-orange-800 ring-1 ring-orange-500/30 dark:text-orange-200' : 'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-900/80' }}">
                    Site ayarları
                </a>
                <a href="{{ route('admin.system.logs.index') }}" class="flex items-center gap-2 rounded-xl px-3 py-2 {{ request()->routeIs('admin.system.logs.*') ? 'bg-orange-500/15 font-medium text-orange-800 ring-1 ring-orange-500/30 dark:text-orange-200' : 'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-900/80' }}">
                    Sistem logları
                </a>
                <a href="{{ route('admin.locale-settings.edit') }}" class="flex items-center gap-2 rounded-xl px-3 py-2 {{ request()->routeIs('admin.locale-settings.*') ? 'bg-orange-500/15 font-medium text-orange-800 ring-1 ring-orange-500/30 dark:text-orange-200' : 'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-900/80' }}">
                    Dil ayarları
                </a>
                <a href="{{ route('admin.translations.index') }}" class="flex items-center gap-2 rounded-xl px-3 py-2 {{ request()->routeIs('admin.translations.*') ? 'bg-orange-500/15 font-medium text-orange-800 ring-1 ring-orange-500/30 dark:text-orange-200' : 'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-900/80' }}">
                    Çeviriler
                </a>
                <a href="{{ route('admin.theme-settings.edit') }}" class="flex items-center gap-2 rounded-xl px-3 py-2 {{ request()->routeIs('admin.theme-settings.*') ? 'bg-orange-500/15 font-medium text-orange-800 ring-1 ring-orange-500/30 dark:text-orange-200' : 'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-900/80' }}">
                    Tema &amp; görünüm
                </a>
                <a href="{{ route('admin.public-home-content.edit') }}" class="flex items-center gap-2 rounded-xl px-3 py-2 {{ request()->routeIs('admin.public-home-content.*') ? 'bg-orange-500/15 font-medium text-orange-800 ring-1 ring-orange-500/30 dark:text-orange-200' : 'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-900/80' }}">
                    Ön yüz ana sayfa
                </a>
                <a href="{{ route('admin.nav-menu.index') }}" class="flex items-center gap-2 rounded-xl px-3 py-2 {{ request()->routeIs('admin.nav-menu.*') ? 'bg-orange-500/15 font-medium text-orange-800 ring-1 ring-orange-500/30 dark:text-orange-200' : 'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-900/80' }}">
                    Menüler (üst / alt)
                </a>
                <a href="{{ route('admin.site-pages.index') }}" class="flex items-center gap-2 rounded-xl px-3 py-2 {{ request()->routeIs('admin.site-pages.*') ? 'bg-orange-500/15 font-medium text-orange-800 ring-1 ring-orange-500/30 dark:text-orange-200' : 'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-900/80' }}">
                    Site sayfaları
                </a>
                <a href="{{ route('admin.blog-posts.index') }}" class="flex items-center gap-2 rounded-xl px-3 py-2 {{ request()->routeIs('admin.blog-posts.*') ? 'bg-orange-500/15 font-medium text-orange-800 ring-1 ring-orange-500/30 dark:text-orange-200' : 'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-900/80' }}">
                    Blog
                </a>
                <a href="{{ route('admin.blog-categories.index') }}" class="flex items-center gap-2 rounded-xl px-3 py-2 {{ request()->routeIs('admin.blog-categories.*') ? 'bg-orange-500/15 font-medium text-orange-800 ring-1 ring-orange-500/30 dark:text-orange-200' : 'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-900/80' }}">
                    Blog kategorileri
                </a>
                <div class="px-3 pt-2 text-[10px] font-semibold uppercase tracking-wider text-slate-400">Topluluk (forum)</div>
                <a href="{{ route('admin.community.settings.edit') }}" class="flex items-center gap-2 rounded-xl px-3 py-2 {{ request()->routeIs('admin.community.settings.*') ? 'bg-orange-500/15 font-medium text-orange-800 ring-1 ring-orange-500/30 dark:text-orange-200' : 'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-900/80' }}">
                    Topluluk SEO
                </a>
                <a href="{{ route('admin.community.moderation.index') }}" class="flex items-center gap-2 rounded-xl px-3 py-2 {{ request()->routeIs('admin.community.moderation.*') ? 'bg-orange-500/15 font-medium text-orange-800 ring-1 ring-orange-500/30 dark:text-orange-200' : 'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-900/80' }}">
                    Moderasyon kuyruğu
                </a>
                <a href="{{ route('admin.community.categories.index') }}" class="flex items-center gap-2 rounded-xl px-3 py-2 {{ request()->routeIs('admin.community.categories.*') ? 'bg-orange-500/15 font-medium text-orange-800 ring-1 ring-orange-500/30 dark:text-orange-200' : 'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-900/80' }}">
                    Topluluk kategorileri
                </a>
                <a href="{{ route('admin.community.members.index') }}" class="flex items-center gap-2 rounded-xl px-3 py-2 {{ request()->routeIs('admin.community.members.*') ? 'bg-orange-500/15 font-medium text-orange-800 ring-1 ring-orange-500/30 dark:text-orange-200' : 'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-900/80' }}">
                    Topluluk üyeleri
                </a>
                <a href="{{ route('admin.community.topics.index') }}" class="flex items-center gap-2 rounded-xl px-3 py-2 {{ request()->routeIs('admin.community.topics.*') ? 'bg-orange-500/15 font-medium text-orange-800 ring-1 ring-orange-500/30 dark:text-orange-200' : 'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-900/80' }}">
                    Topluluk konuları
                </a>
                <a href="{{ route('admin.doc-pages.index') }}" class="flex items-center gap-2 rounded-xl px-3 py-2 {{ request()->routeIs('admin.doc-pages.*') ? 'bg-orange-500/15 font-medium text-orange-800 ring-1 ring-orange-500/30 dark:text-orange-200' : 'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-900/80' }}">
                    Dokümanlar
                </a>
                <a href="{{ route('admin.plans.index') }}" class="flex items-center gap-2 rounded-xl px-3 py-2 {{ request()->routeIs('admin.plans.*') ? 'bg-orange-500/15 font-medium text-orange-800 ring-1 ring-orange-500/30 dark:text-orange-200' : 'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-900/80' }}">
                    Planlar
                </a>
            </nav>
            <div class="border-t border-slate-200/90 p-3 text-xs text-slate-500 dark:border-slate-800/80">
                {{ auth()->user()->email ?? '' }}
            </div>
        </aside>

        <div class="flex min-h-screen flex-1 flex-col">
            <header class="flex h-16 items-center justify-between gap-4 border-b border-slate-200/90 bg-white/90 px-4 backdrop-blur-xl dark:border-slate-800/80 dark:bg-slate-950/80 lg:px-8">
                <div class="flex items-center gap-3">
                    <button type="button"
                            class="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-slate-300/90 bg-white text-slate-800 lg:hidden dark:border-slate-700 dark:bg-slate-900/60 dark:text-slate-200"
                            @click="$dispatch('hv-admin-toggle-drawer')">
                        <span class="sr-only">Menü</span>
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                            <path d="M4 6h16M4 12h16M4 18h16" stroke-width="1.5" stroke-linecap="round"/>
                        </svg>
                    </button>
                    <div class="text-sm font-semibold text-slate-900 dark:text-slate-100">{{ $title }}</div>
                </div>
                <div class="flex items-center gap-2">
                    <x-theme-toggle class="!inline-flex" />
                    <a href="{{ route('landing.home') }}" class="hidden text-xs text-slate-500 hover:text-orange-600 sm:inline dark:text-slate-400 dark:hover:text-orange-300">Siteyi gör</a>
                    <form method="POST" action="{{ route('admin.logout') }}">
                        @csrf
                        <button type="submit" class="inline-flex items-center rounded-full border border-slate-300/90 bg-white px-3 py-1.5 text-xs font-medium text-slate-800 hover:border-orange-300 dark:border-slate-700 dark:bg-slate-900/70 dark:text-slate-200 dark:hover:border-slate-500">
                            Çıkış
                        </button>
                    </form>
                </div>
            </header>

            <main class="flex-1 px-5 py-8 lg:px-12 lg:py-10">
                @if (session('status'))
                    <div class="mb-4 rounded-xl border border-orange-500/40 bg-orange-500/10 px-4 py-3 text-sm text-orange-900 dark:text-orange-100">
                        {{ session('status') }}
                    </div>
                @endif
                @if (session('error'))
                    <div class="mb-4 rounded-xl border border-rose-500/40 bg-rose-500/10 px-4 py-3 text-sm text-rose-800 dark:text-rose-200">
                        {{ session('error') }}
                    </div>
                @endif

                {{ $slot }}
            </main>
        </div>
    </div>

    <aside x-data="{ open: false }"
           x-on:hv-admin-toggle-drawer.window="open = !open"
           x-on:resize.window="if (window.innerWidth >= 1024) open = false"
           class="fixed inset-0 z-40 lg:hidden"
           x-cloak
           x-show="open"
           x-transition.opacity.duration.200>
        <div class="absolute inset-0 bg-slate-900/40 backdrop-blur-sm dark:bg-slate-950/70" @click="open = false"></div>
        <div class="absolute inset-y-0 left-0 w-full max-w-xs border-r border-slate-200/90 bg-white shadow-2xl dark:border-slate-800/80 dark:bg-slate-950/98"
             x-show="open"
             x-transition.duration.220.origin-left>
            <div class="flex h-16 items-center justify-between border-b border-slate-200/90 px-4 dark:border-slate-800/80">
                <div class="flex items-center gap-2">
                    <div class="flex h-8 w-8 items-center justify-center rounded-2xl bg-orange-500/15 ring-1 ring-orange-500/35 dark:bg-orange-500/10 dark:ring-orange-400/40">
                        <span class="text-base font-bold text-orange-600 dark:text-orange-400">H</span>
                    </div>
                    <span class="text-sm font-medium text-slate-900 dark:text-slate-100">Hostvim</span>
                </div>
                <button type="button" @click="open = false" class="inline-flex h-8 w-8 items-center justify-center rounded-xl border border-slate-300/90 text-slate-700 dark:border-slate-700 dark:text-slate-200">
                    <span class="sr-only">Kapat</span>
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                        <path d="M6 6l12 12M18 6L6 18" stroke-width="1.5" stroke-linecap="round"/>
                    </svg>
                </button>
            </div>
            <nav class="space-y-1 p-3 text-sm">
                <a href="{{ route('admin.dashboard') }}" class="block rounded-xl px-3 py-2 text-slate-700 hover:bg-orange-50 dark:text-slate-200 dark:hover:bg-slate-900/80">Özet</a>
                <div class="px-3 pt-2 text-[10px] font-semibold uppercase text-slate-400">SaaS</div>
                <a href="{{ route('admin.saas.dashboard') }}" class="block rounded-xl px-3 py-2 text-slate-700 hover:bg-orange-50 dark:text-slate-200 dark:hover:bg-slate-900/80">Lisans özeti</a>
                <a href="{{ route('admin.saas.customers.index') }}" class="block rounded-xl px-3 py-2 text-slate-700 hover:bg-orange-50 dark:text-slate-200 dark:hover:bg-slate-900/80">Müşteriler</a>
                <a href="{{ route('admin.saas.licenses.index') }}" class="block rounded-xl px-3 py-2 text-slate-700 hover:bg-orange-50 dark:text-slate-200 dark:hover:bg-slate-900/80">Lisanslar</a>
                <a href="{{ route('admin.saas.products.index') }}" class="block rounded-xl px-3 py-2 text-slate-700 hover:bg-orange-50 dark:text-slate-200 dark:hover:bg-slate-900/80">Ürünler</a>
                <a href="{{ route('admin.saas.modules.index') }}" class="block rounded-xl px-3 py-2 text-slate-700 hover:bg-orange-50 dark:text-slate-200 dark:hover:bg-slate-900/80">Modüller</a>
                <a href="{{ route('admin.billing-settings.edit') }}" class="block rounded-xl px-3 py-2 text-slate-700 hover:bg-orange-50 dark:text-slate-200 dark:hover:bg-slate-900/80">Ödeme yöntemleri</a>
                <a href="{{ route('admin.site-settings.edit') }}" class="block rounded-xl px-3 py-2 text-slate-700 hover:bg-orange-50 dark:text-slate-200 dark:hover:bg-slate-900/80">Site ayarları</a>
                <a href="{{ route('admin.system.logs.index') }}" class="block rounded-xl px-3 py-2 text-slate-700 hover:bg-orange-50 dark:text-slate-200 dark:hover:bg-slate-900/80">Sistem logları</a>
                <a href="{{ route('admin.locale-settings.edit') }}" class="block rounded-xl px-3 py-2 text-slate-700 hover:bg-orange-50 dark:text-slate-200 dark:hover:bg-slate-900/80">Dil ayarları</a>
                <a href="{{ route('admin.translations.index') }}" class="block rounded-xl px-3 py-2 text-slate-700 hover:bg-orange-50 dark:text-slate-200 dark:hover:bg-slate-900/80">Çeviriler</a>
                <a href="{{ route('admin.theme-settings.edit') }}" class="block rounded-xl px-3 py-2 text-slate-700 hover:bg-orange-50 dark:text-slate-200 dark:hover:bg-slate-900/80">Tema &amp; görünüm</a>
                <a href="{{ route('admin.public-home-content.edit') }}" class="block rounded-xl px-3 py-2 text-slate-700 hover:bg-orange-50 dark:text-slate-200 dark:hover:bg-slate-900/80">Ön yüz ana sayfa</a>
                <a href="{{ route('admin.nav-menu.index') }}" class="block rounded-xl px-3 py-2 text-slate-700 hover:bg-orange-50 dark:text-slate-200 dark:hover:bg-slate-900/80">Menüler (üst / alt)</a>
                <a href="{{ route('admin.site-pages.index') }}" class="block rounded-xl px-3 py-2 text-slate-700 hover:bg-orange-50 dark:text-slate-200 dark:hover:bg-slate-900/80">Site sayfaları</a>
                <a href="{{ route('admin.blog-posts.index') }}" class="block rounded-xl px-3 py-2 text-slate-700 hover:bg-orange-50 dark:text-slate-200 dark:hover:bg-slate-900/80">Blog</a>
                <a href="{{ route('admin.blog-categories.index') }}" class="block rounded-xl px-3 py-2 text-slate-700 hover:bg-orange-50 dark:text-slate-200 dark:hover:bg-slate-900/80">Blog kategorileri</a>
                <div class="px-3 pt-2 text-[10px] font-semibold uppercase text-slate-400">Topluluk</div>
                <a href="{{ route('admin.community.settings.edit') }}" class="block rounded-xl px-3 py-2 text-slate-700 hover:bg-orange-50 dark:text-slate-200 dark:hover:bg-slate-900/80">Topluluk SEO</a>
                <a href="{{ route('admin.community.moderation.index') }}" class="block rounded-xl px-3 py-2 text-slate-700 hover:bg-orange-50 dark:text-slate-200 dark:hover:bg-slate-900/80">Moderasyon kuyruğu</a>
                <a href="{{ route('admin.community.categories.index') }}" class="block rounded-xl px-3 py-2 text-slate-700 hover:bg-orange-50 dark:text-slate-200 dark:hover:bg-slate-900/80">Topluluk kategorileri</a>
                <a href="{{ route('admin.community.members.index') }}" class="block rounded-xl px-3 py-2 text-slate-700 hover:bg-orange-50 dark:text-slate-200 dark:hover:bg-slate-900/80">Topluluk üyeleri</a>
                <a href="{{ route('admin.community.topics.index') }}" class="block rounded-xl px-3 py-2 text-slate-700 hover:bg-orange-50 dark:text-slate-200 dark:hover:bg-slate-900/80">Topluluk konuları</a>
                <a href="{{ route('admin.doc-pages.index') }}" class="block rounded-xl px-3 py-2 text-slate-700 hover:bg-orange-50 dark:text-slate-200 dark:hover:bg-slate-900/80">Dokümanlar</a>
                <a href="{{ route('admin.plans.index') }}" class="block rounded-xl px-3 py-2 text-slate-700 hover:bg-orange-50 dark:text-slate-200 dark:hover:bg-slate-900/80">Planlar</a>
            </nav>
        </div>
    </aside>
</body>
</html>
