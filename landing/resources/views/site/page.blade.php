<x-site.layout
    :title="$page->effectiveMetaTitle() . ' · ' . landing_p('brand.name')"
    :description="$seoDescription"
    :canonical-url="$seoCanonical"
    :og-title="$page->effectiveMetaTitle()"
    :og-description="$seoDescription"
    :og-image="$seoOgImage"
    :robots-content="$seoRobots ?: null"
    :schema-json-ld="$seoSchema"
>
    <div class="hv-container max-w-3xl">
        <nav class="mb-8 flex flex-wrap items-center gap-x-2 gap-y-1 text-sm text-slate-500 dark:text-slate-500">
            <a href="{{ route('landing.home') }}" class="hv-muted-nav">{{ landing_t('nav.home') }}</a>
            <span class="text-slate-400">/</span>
            <span class="text-slate-600 dark:text-slate-400">{{ $page->title }}</span>
        </nav>

        <article class="rounded-2xl border border-slate-200/90 bg-white/95 p-6 sm:p-10 dark:border-slate-800/80 dark:bg-slate-900/65">
            <h1 class="text-3xl font-semibold tracking-tight text-slate-900 dark:text-slate-50">{{ $page->title }}</h1>
            <div class="markdown-body mt-6">
                {!! \App\Support\SafeRichContent::toHtml($page->content) !!}
            </div>
        </article>
    </div>
</x-site.layout>
