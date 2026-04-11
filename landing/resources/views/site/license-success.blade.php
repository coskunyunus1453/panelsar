@php
    $locale = app()->getLocale();
    $pageTitle = landing_t('license.success_title');
    $canonical = landing_url_with_lang(route('license.success', absolute: true), $locale);
@endphp

<x-site.layout
    :title="$pageTitle"
    :description="landing_t('license.success_meta')"
    :canonical-url="$canonical"
>
    <div class="hv-container max-w-xl py-16">
        <h1 class="text-2xl font-semibold text-slate-900 dark:text-slate-50">{{ $pageTitle }}</h1>
        <p class="mt-4 text-slate-600 dark:text-slate-400">
            {{ landing_t('license.success_lead') }}
        </p>
        <p class="mt-3 text-sm text-slate-500 dark:text-slate-500">
            {{ landing_t('license.order_ref_label') }}:
            <code class="rounded bg-slate-100 px-1 font-mono text-xs dark:bg-slate-800">{{ request('ref', '—') }}</code>
        </p>
        <p class="mt-8">
            <a href="{{ route('site.pricing') }}" class="hv-link-quiet font-semibold">{{ landing_t('license.back_pricing') }}</a>
        </p>
    </div>
</x-site.layout>
