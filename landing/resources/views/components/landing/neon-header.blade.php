<header class="hv-neon-header relative z-20 border-b border-slate-200/80 bg-white/70 backdrop-blur-2xl dark:border-slate-800/60 dark:bg-slate-950/75">
    <div class="hv-container">
        <div class="flex h-14 items-center justify-between gap-3 sm:h-[3.75rem]">
            <a href="{{ route('landing.home') }}" class="group flex min-w-0 items-center gap-2.5 sm:gap-3">
                <x-landing.brand-logo variant="neon" />
                <div class="min-w-0 leading-tight">
                    <span class="block truncate text-sm font-semibold tracking-tight text-slate-900 dark:text-slate-50 sm:text-base">{{ landing_p('brand.name') }}</span>
                    <span class="hidden text-[11px] font-medium text-slate-500 dark:text-slate-400 sm:block">{{ landing_p('brand.subtitle') }}</span>
                </div>
            </a>

            <nav class="hidden items-center gap-1 text-sm font-medium md:flex">
                @foreach ($landingHeaderNav as $navItem)
                    <a href="{{ $navItem->resolvedHref() }}"
                       class="hv-neon-nav-link rounded-lg px-3 py-2"
                       @if ($navItem->open_in_new_tab) target="_blank" rel="noopener noreferrer" @endif
                    >{{ $navItem->displayLabel() }}</a>
                @endforeach
            </nav>

            <div class="flex shrink-0 items-center gap-2">
                @if (! empty($landingEnabledLocales) && count($landingEnabledLocales) > 1)
                    <select class="hv-neon-select hidden rounded-lg py-1.5 pl-2 pr-7 text-xs font-medium sm:block"
                            aria-label="Dil"
                            onchange="(function(v){if(!v)return;var p=new window.URLSearchParams(window.location.search);p.set('lang',v);var q=p.toString();window.location=window.location.pathname+(q?'?'+q:'')+window.location.hash;})(this.value)">
                        @foreach ($landingEnabledLocales as $code)
                            <option value="{{ $code }}" @selected(($landingLocale ?? app()->getLocale()) === $code)>
                                {{ landing_locale_tag($code) }}
                            </option>
                        @endforeach
                    </select>
                @endif
                <div class="hidden items-center gap-1.5 sm:flex">
                    @auth
                        <a href="{{ route('community.profile.edit') }}" class="shrink-0 rounded-full ring-1 ring-slate-200/90 hover:opacity-90 dark:ring-slate-600" title="{{ landing_t('community.header_profile_title') }}">
                            <img src="{{ community_user_avatar_url(auth()->user(), 64) }}" alt="" class="h-8 w-8 rounded-full object-cover" width="32" height="32" loading="lazy" decoding="async" />
                        </a>
                        <span class="max-w-[8rem] truncate text-xs font-medium text-slate-600 dark:text-slate-300" title="{{ auth()->user()->email }}">{{ auth()->user()->name }}</span>
                        <form method="post" action="{{ route('logout') }}" class="inline">
                            @csrf
                            <button type="submit" class="rounded-lg border border-slate-300/80 px-2.5 py-1.5 text-xs font-semibold text-slate-700 dark:border-slate-600 dark:text-slate-200">{{ landing_t('auth.header_sign_out') }}</button>
                        </form>
                    @else
                        <a href="{{ route('login', ['lang' => $landingLocale ?? app()->getLocale()]) }}" class="rounded-lg px-2.5 py-1.5 text-xs font-semibold text-slate-700 dark:text-slate-200">{{ landing_t('auth.header_sign_in') }}</a>
                        <a href="{{ route('register', ['lang' => $landingLocale ?? app()->getLocale()]) }}" class="rounded-lg bg-slate-900 px-2.5 py-1.5 text-xs font-semibold text-white dark:bg-slate-100 dark:text-slate-900">{{ landing_t('auth.header_sign_up') }}</a>
                    @endauth
                </div>
                <x-theme-toggle class="hidden sm:inline-flex" />
                <a href="{{ url('/panel') }}" class="hv-neon-cta hidden items-center gap-1.5 rounded-lg px-3 py-2 text-xs font-semibold sm:inline-flex sm:text-sm">
                    <span>{{ landing_p('nav.panel') }}</span>
                    <span class="opacity-80">→</span>
                </a>
                <button type="button"
                        x-data
                        @click="$dispatch('hv-toggle-drawer')"
                        class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-slate-300/80 text-slate-700 md:hidden dark:border-slate-600 dark:text-slate-200">
                    <span class="sr-only">{{ landing_p('nav.menu') }}</span>
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                        <path d="M4 6h16M4 12h16M4 18h16" stroke-width="1.5" stroke-linecap="round"/>
                    </svg>
                </button>
            </div>
        </div>
        <div class="flex items-center justify-end gap-2 pb-2 sm:hidden">
            @if (! empty($landingEnabledLocales) && count($landingEnabledLocales) > 1)
                <select class="hv-neon-select rounded-lg py-1 pl-2 pr-6 text-xs font-medium"
                        onchange="(function(v){if(!v)return;var p=new window.URLSearchParams(window.location.search);p.set('lang',v);var q=p.toString();window.location=window.location.pathname+(q?'?'+q:'')+window.location.hash;})(this.value)">
                    @foreach ($landingEnabledLocales as $code)
                        <option value="{{ $code }}" @selected(($landingLocale ?? app()->getLocale()) === $code)>
                            {{ landing_locale_tag($code) }}
                        </option>
                    @endforeach
                </select>
            @endif
            <x-theme-toggle class="inline-flex" />
        </div>
    </div>
</header>
