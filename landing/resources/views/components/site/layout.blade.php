@props([
    'title' => null,
    'description' => null,
    'canonicalUrl' => null,
    'ogTitle' => null,
    'ogDescription' => null,
    'ogImage' => null,
    'ogType' => 'website',
    'robotsContent' => null,
    'schemaJsonLd' => null,
])
@php
    $pageTitle = $title ?? landing_p('brand.name');
    $metaDescription = $description;
    $ogPageTitle = $ogTitle ?? $pageTitle;
    $ogPageDescription = $ogDescription ?? $metaDescription;
    $ogPageUrl = $canonicalUrl ?? url()->current();
    $twitterCard = $ogImage ? 'summary_large_image' : 'summary';
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full scroll-smooth {{ $landingThemeClass ?? 'hv-theme-orange' }}" @if(! empty($landingThemeInlineStyle)) style="{{ $landingThemeInlineStyle }}" @endif>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>{{ $pageTitle }}</title>
    @if ($metaDescription)
        <meta name="description" content="{{ $metaDescription }}">
    @endif
    @if ($canonicalUrl)
        <link rel="canonical" href="{{ $canonicalUrl }}">
    @endif
    @if ($robotsContent)
        <meta name="robots" content="{{ $robotsContent }}">
    @endif

    <meta property="og:locale" content="{{ str_replace('_', '-', app()->getLocale()) }}">
    <meta property="og:site_name" content="{{ landing_p('brand.name') }}">
    <meta property="og:title" content="{{ $ogPageTitle }}">
    @if ($ogPageDescription)
        <meta property="og:description" content="{{ $ogPageDescription }}">
    @endif
    <meta property="og:type" content="{{ $ogType }}">
    <meta property="og:url" content="{{ $ogPageUrl }}">
    @if ($ogImage)
        <meta property="og:image" content="{{ $ogImage }}">
    @endif

    <meta name="twitter:card" content="{{ $twitterCard }}">
    <meta name="twitter:title" content="{{ $ogPageTitle }}">
    @if ($ogPageDescription)
        <meta name="twitter:description" content="{{ $ogPageDescription }}">
    @endif
    @if ($ogImage)
        <meta name="twitter:image" content="{{ $ogImage }}">
    @endif

    <x-landing.head-extras />

    @if ($schemaJsonLd)
        <script type="application/ld+json">{!! $schemaJsonLd !!}</script>
    @endif

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <script>
        (function () {
            var t = localStorage.getItem('hv-theme');
            if (t === 'dark') document.documentElement.classList.add('dark');
            else if (t === 'light') document.documentElement.classList.remove('dark');
            else if (window.matchMedia('(prefers-color-scheme: dark)').matches) document.documentElement.classList.add('dark');
        })();
    </script>
    <style>[x-cloak] { display: none !important; }</style>
</head>
<body class="hv-body min-h-full flex flex-col text-base">

    @if (($landingThemeClass ?? '') === 'hv-theme-neon')
        <div class="hv-neon-backdrop pointer-events-none fixed inset-0 -z-10 overflow-hidden" aria-hidden="true">
            <div class="hv-neon-backdrop-grid absolute inset-0 opacity-[0.45] dark:opacity-[0.35]"></div>
            <div class="hv-neon-backdrop-orb hv-neon-backdrop-orb-a absolute -left-32 top-0 h-[28rem] w-[28rem] rounded-full blur-3xl"></div>
            <div class="hv-neon-backdrop-orb hv-neon-backdrop-orb-b absolute -right-24 top-1/3 h-[22rem] w-[22rem] rounded-full blur-3xl"></div>
        </div>
    @else
        <div class="pointer-events-none fixed inset-0 -z-10 overflow-hidden {{ $landingGraphicMotifClass ?? '' }}">
            <div class="hv-bg-blob absolute -top-28 left-1/2 h-[20rem] w-[36rem] -translate-x-1/2 rounded-full blur-3xl"></div>
        </div>
    @endif

    @if (($landingThemeClass ?? '') === 'hv-theme-neon')
        <x-landing.neon-header />
    @else
    <header class="relative z-20 border-b border-slate-200/90 bg-white/85 backdrop-blur-xl dark:border-slate-800/80 dark:bg-slate-950/80">
        <div class="hv-container">
            <div class="flex h-[4.25rem] items-center justify-between gap-4">
                <a href="{{ route('landing.home') }}" class="flex items-center gap-3">
                    <x-landing.brand-logo />
                    <div class="flex flex-col leading-tight">
                        <span class="text-base font-semibold tracking-tight text-slate-900 dark:text-slate-100">{{ landing_p('brand.name') }}</span>
                        <span class="text-xs font-medium text-slate-500 dark:text-slate-400">{{ landing_p('brand.subtitle') }}</span>
                    </div>
                </a>

                <nav class="hidden items-center gap-7 text-base font-medium md:flex">
                    <x-landing.nav-menu :items="$landingHeaderNav" link-class="hv-muted-nav font-medium" />
                </nav>

                <div class="hidden items-center gap-3 md:flex">
                    @if (! empty($landingEnabledLocales) && count($landingEnabledLocales) > 1)
                        <div class="relative">
                            <label for="hv-lang-site" class="sr-only">Dil</label>
                            <select id="hv-lang-site"
                                    class="rounded-full border border-slate-300/90 bg-white/90 py-1.5 pl-3 pr-8 text-xs font-semibold text-slate-700 dark:border-slate-600 dark:bg-slate-900/80 dark:text-slate-200"
                                    onchange="(function(v){if(!v)return;var p=new window.URLSearchParams(window.location.search);p.set('lang',v);var q=p.toString();window.location=window.location.pathname+(q?'?'+q:'')+window.location.hash;})(this.value)">
                                @foreach ($landingEnabledLocales as $code)
                                    <option value="{{ $code }}" @selected(($landingLocale ?? app()->getLocale()) === $code)>
                                        {{ landing_locale_tag($code) }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    @endif

                    <div class="hidden items-center gap-2 sm:flex">
                        @auth
                            <span class="max-w-[10rem] truncate text-xs font-medium text-slate-600 dark:text-slate-300" title="{{ auth()->user()->email }}">{{ auth()->user()->name }}</span>
                            <form method="post" action="{{ route('logout') }}" class="inline">
                                @csrf
                                <button type="submit" class="rounded-full border border-slate-300/90 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-100 dark:border-slate-600 dark:text-slate-200 dark:hover:bg-slate-800">
                                    {{ landing_t('auth.header_sign_out') }}
                                </button>
                            </form>
                        @else
                            <a href="{{ route('login', ['lang' => $landingLocale ?? app()->getLocale()]) }}" class="rounded-full px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-100 dark:text-slate-200 dark:hover:bg-slate-800">
                                {{ landing_t('auth.header_sign_in') }}
                            </a>
                            <a href="{{ route('register', ['lang' => $landingLocale ?? app()->getLocale()]) }}" class="rounded-full bg-[rgb(var(--hv-brand-600)/1)] px-3 py-1.5 text-xs font-semibold text-white hover:opacity-95">
                                {{ landing_t('auth.header_sign_up') }}
                            </a>
                        @endauth
                    </div>

                    <x-theme-toggle class="hidden sm:inline-flex" />
                </div>

                <button type="button"
                        x-data
                        @click="$dispatch('hv-toggle-drawer')"
                        class="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-slate-300/90 bg-white/90 text-slate-800 md:hidden dark:border-slate-700 dark:bg-slate-900/70 dark:text-slate-100">
                    <span class="sr-only">{{ landing_p('nav.menu') }}</span>
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                        <path d="M4 6h16M4 12h16M4 18h16" stroke-width="1.5" stroke-linecap="round"/>
                    </svg>
                </button>
            </div>

            <div class="flex items-center justify-end gap-2 pb-3 md:hidden">
                @if (! empty($landingEnabledLocales) && count($landingEnabledLocales) > 1)
                    <select class="rounded-full border border-slate-300/90 bg-white/90 py-1 pl-2 pr-7 text-xs font-semibold dark:border-slate-600 dark:bg-slate-900/80"
                            onchange="(function(v){if(!v)return;var p=new window.URLSearchParams(window.location.search);p.set('lang',v);var q=p.toString();window.location=window.location.pathname+(q?'?'+q:'')+window.location.hash;})(this.value)">
                        @foreach ($landingEnabledLocales as $code)
                            <option value="{{ $code }}" @selected(($landingLocale ?? app()->getLocale()) === $code)>
                                {{ $landingLocaleLabels[$code] ?? $code }}
                            </option>
                        @endforeach
                    </select>
                @endif
                <x-theme-toggle class="inline-flex" />
            </div>
        </div>
    </header>
    @endif

    @if (session('error'))
        <div class="relative z-25 border-b border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800 dark:border-rose-900/50 dark:bg-rose-950/50 dark:text-rose-200">
            <div class="hv-container">{{ session('error') }}</div>
        </div>
    @endif

    @if (session('status'))
        <div class="relative z-25 border-b border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900 dark:border-emerald-900/40 dark:bg-emerald-950/40 dark:text-emerald-100">
            <div class="hv-container">{{ session('status') }}</div>
        </div>
    @endif

    <main class="relative z-10 flex-1 py-10 sm:py-12">
        {{ $slot }}
    </main>

    @if (($landingThemeClass ?? '') === 'hv-theme-neon')
        <x-landing.neon-footer />
    @else
    <footer class="relative z-10 mt-auto border-t border-slate-200/90 bg-white/90 py-8 dark:border-slate-800/70 dark:bg-slate-950/90">
        <div class="hv-container flex flex-col gap-4 text-sm sm:flex-row sm:items-start sm:justify-between">
            <div class="flex min-w-0 flex-1 flex-col gap-2">
                <div class="flex flex-wrap items-center gap-2 text-slate-600 dark:text-slate-400">
                    <span>© {{ date('Y') }} {{ landing_p('brand.name') }}</span>
                    @if ($hvFooterNoteSite = \App\Services\LandingAppearance::footerExtraNote())
                        <span class="text-slate-500 dark:text-slate-500">{{ $hvFooterNoteSite }}</span>
                    @endif
                </div>
                <x-landing.footer-extras />
            </div>
            <div class="flex flex-wrap gap-6 font-medium">
                <x-landing.nav-menu :items="$landingFooterNav" link-class="hv-muted-nav" />
            </div>
        </div>
    </footer>
    @endif

    @if (($landingThemeClass ?? '') === 'hv-theme-neon')
        <x-landing.neon-drawer />
    @else
    <aside x-data="{ open: false }"
           x-on:hv-toggle-drawer.window="open = !open"
           x-on:resize.window="if (window.innerWidth >= 768) open = false"
           class="fixed inset-0 z-30 md:hidden"
           x-cloak
           x-show="open"
           x-transition.opacity.duration.200>
        <div class="absolute inset-0 bg-slate-900/40 backdrop-blur-sm dark:bg-slate-950/70" @click="open = false"></div>
        <div class="absolute inset-y-0 right-0 w-full max-w-xs border-l border-slate-200/90 bg-white/98 shadow-2xl dark:border-slate-800/90 dark:bg-slate-950/98"
             x-show="open"
             x-transition.duration.220.origin-right>
            <div class="flex h-14 items-center justify-between border-b border-slate-200/90 px-4 dark:border-slate-800/80">
                <span class="text-base font-semibold text-slate-900 dark:text-slate-100">{{ landing_p('brand.name') }}</span>
                <button type="button" @click="open = false" class="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-slate-300/90 text-slate-700 dark:border-slate-700 dark:text-slate-200">
                    <span class="sr-only">{{ landing_p('nav.close') }}</span>
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                        <path d="M6 6l12 12M18 6L6 18" stroke-width="1.5" stroke-linecap="round"/>
                    </svg>
                </button>
            </div>
            <nav class="space-y-1 px-4 py-4 text-base">
                @foreach ($landingHeaderNav as $drawerItem)
                    <a
                        href="{{ $drawerItem->resolvedHref() }}"
                        class="hv-drawer-link block rounded-xl px-3 py-2.5 font-medium text-slate-700 dark:text-slate-200 dark:hover:bg-slate-900/90"
                        @if ($drawerItem->open_in_new_tab) target="_blank" rel="noopener noreferrer" @endif
                    >{{ $drawerItem->displayLabel() }}</a>
                @endforeach
                <div class="mt-4 border-t border-slate-200 pt-4 dark:border-slate-700">
                    @auth
                        <p class="px-3 text-xs text-slate-500">{{ auth()->user()->name }}</p>
                        <form method="post" action="{{ route('logout') }}" class="mt-2 px-3">
                            @csrf
                            <button type="submit" class="w-full rounded-xl border border-slate-300 py-2 text-sm font-semibold text-slate-800 dark:border-slate-600 dark:text-slate-200">{{ landing_t('auth.header_sign_out') }}</button>
                        </form>
                    @else
                        <a href="{{ route('login', ['lang' => $landingLocale ?? app()->getLocale()]) }}" class="block rounded-xl px-3 py-2.5 font-medium text-slate-700 dark:text-slate-200">{{ landing_t('auth.header_sign_in') }}</a>
                        <a href="{{ route('register', ['lang' => $landingLocale ?? app()->getLocale()]) }}" class="mt-1 block rounded-xl bg-[rgb(var(--hv-brand-600)/1)] px-3 py-2.5 text-center text-sm font-semibold text-white">{{ landing_t('auth.header_sign_up') }}</a>
                    @endauth
                </div>
            </nav>
        </div>
    </aside>
    @endif
</body>
</html>
