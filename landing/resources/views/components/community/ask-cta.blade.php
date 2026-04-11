@props([
    'category' => null,
    /** @var 'strip'|'hero'|'compact'|'fab' */
    'layout' => 'strip',
])
@php
    /** @var \App\Models\CommunityCategory|null $category */
    $askQuery = [];
    if ($category) {
        $param = app()->getLocale() === 'en' ? 'category' : 'kategori';
        $askQuery[$param] = $category->slug;
    }
    $askUrl = route('community.ask', $askQuery);
    $redirectPath = route('community.ask', $askQuery, absolute: false);
    $authQuery = ['redirect' => $redirectPath, 'lang' => app()->getLocale()];
    $loginUrl = route('login', $authQuery);
    $registerUrl = route('register', $authQuery);
@endphp

@if ($layout === 'fab')
    <div {{ $attributes->merge(['class' => 'md:hidden']) }}>
        @auth
            <a href="{{ $askUrl }}"
               class="inline-flex h-14 w-14 items-center justify-center rounded-full bg-[rgb(var(--hv-brand-600)/1)] text-2xl font-light leading-none text-white shadow-lg ring-2 ring-white/40 hover:opacity-95"
               title="{{ landing_t('community.fab_title_new_topic') }}"
               aria-label="{{ landing_t('community.fab_aria_new_topic') }}">+</a>
        @else
            <a href="{{ $loginUrl }}"
               class="inline-flex h-14 w-14 items-center justify-center rounded-full bg-[rgb(var(--hv-brand-600)/1)] text-sm font-semibold text-white shadow-lg ring-2 ring-white/40 hover:opacity-95"
               title="{{ landing_t('community.fab_title_login') }}"
               aria-label="{{ landing_t('community.fab_aria_login') }}">?</a>
        @endauth
    </div>
@elseif ($layout === 'hero')
    <div {{ $attributes->merge(['class' => 'flex flex-wrap items-center gap-3']) }}>
        @auth
            <a href="{{ $askUrl }}"
               class="inline-flex items-center justify-center rounded-xl bg-[rgb(var(--hv-brand-600)/1)] px-5 py-3 text-sm font-semibold text-white shadow-sm hover:opacity-95">
                {{ landing_t('community.hero_new_topic') }}
            </a>
        @else
            <a href="{{ $loginUrl }}"
               class="inline-flex items-center justify-center rounded-xl bg-[rgb(var(--hv-brand-600)/1)] px-5 py-3 text-sm font-semibold text-white shadow-sm hover:opacity-95">
                {{ landing_t('community.hero_login') }}
            </a>
            <a href="{{ $registerUrl }}"
               class="inline-flex items-center justify-center rounded-xl border-2 border-[rgb(var(--hv-brand-600)/0.5)] bg-white px-5 py-3 text-sm font-semibold text-[rgb(var(--hv-brand-700)/1)] dark:bg-slate-900 dark:text-slate-100">
                {{ landing_t('community.hero_register_free') }}
            </a>
        @endauth
    </div>
@elseif ($layout === 'compact')
    <div {{ $attributes->merge(['class' => 'flex flex-wrap items-center gap-2']) }}>
        @auth
            <a href="{{ $askUrl }}"
               class="inline-flex items-center justify-center rounded-lg bg-[rgb(var(--hv-brand-600)/1)] px-3 py-1.5 text-xs font-semibold text-white hover:opacity-95 sm:text-sm sm:px-4 sm:py-2">
                {{ landing_t('community.compact_ask') }}
            </a>
        @else
            <a href="{{ $loginUrl }}"
               class="inline-flex items-center justify-center rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-800 dark:border-slate-600 dark:text-slate-200 sm:text-sm">
                {{ landing_t('community.compact_login') }}
            </a>
            <a href="{{ $registerUrl }}"
               class="inline-flex items-center justify-center rounded-lg bg-[rgb(var(--hv-brand-600)/1)] px-3 py-1.5 text-xs font-semibold text-white hover:opacity-95 sm:text-sm">
                {{ landing_t('community.compact_register') }}
            </a>
        @endauth
    </div>
@else
    {{-- strip (default) --}}
    <div {{ $attributes->merge(['class' => 'flex flex-shrink-0 flex-wrap items-center gap-2 sm:gap-3']) }}>
        @auth
            <a href="{{ $askUrl }}"
               class="inline-flex items-center justify-center rounded-xl bg-[rgb(var(--hv-brand-600)/1)] px-4 py-2.5 text-sm font-semibold text-white hover:opacity-95">
                {{ landing_t('community.strip_new_topic') }}
            </a>
        @else
            <a href="{{ $loginUrl }}"
               class="inline-flex items-center justify-center rounded-xl bg-[rgb(var(--hv-brand-600)/1)] px-4 py-2.5 text-sm font-semibold text-white hover:opacity-95">
                {{ landing_t('community.strip_login_ask') }}
            </a>
            <a href="{{ $registerUrl }}"
               class="inline-flex items-center justify-center rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-800 dark:border-slate-600 dark:text-slate-200">
                {{ landing_t('community.strip_register') }}
            </a>
        @endauth
    </div>
@endif
