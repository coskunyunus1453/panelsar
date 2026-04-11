<x-site.layout
    :title="$post->effectiveMetaTitle() . ' · ' . landing_t('blog.heading') . ' · ' . landing_p('brand.name')"
    :description="$seoDescription"
    :canonical-url="$seoCanonical"
    :og-title="$post->effectiveMetaTitle()"
    :og-description="$seoDescription"
    :og-image="$seoOgImage"
    og-type="article"
    :robots-content="$seoRobots ?: null"
    :schema-json-ld="$seoSchema"
>
    <div class="hv-container max-w-3xl">
        <nav class="mb-8 flex flex-wrap items-center gap-x-2 gap-y-1 text-sm text-slate-500 dark:text-slate-500">
            <a href="{{ route('landing.home') }}" class="hv-muted-nav">{{ landing_t('nav.home') }}</a>
            <span class="text-slate-400">/</span>
            <a href="{{ route('blog.index') }}" class="hv-muted-nav">{{ landing_t('blog.heading') }}</a>
            @if ($post->category)
                <span class="text-slate-400">/</span>
                <a href="{{ route('blog.category', $post->category->slug) }}" class="hv-muted-nav">{{ $post->category->name }}</a>
            @endif
            <span class="text-slate-400">/</span>
            <span class="line-clamp-1 text-slate-600 dark:text-slate-400">{{ $post->title }}</span>
        </nav>

        <article class="rounded-2xl border border-slate-200/90 bg-white/95 p-6 sm:p-10 dark:border-slate-800/80 dark:bg-slate-900/65">
            <p class="text-sm font-medium text-slate-500 dark:text-slate-500">{{ $post->published_at?->translatedFormat('d F Y') }}</p>
            <h1 class="mt-3 text-3xl font-semibold tracking-tight text-slate-900 dark:text-slate-50">{{ $post->title }}</h1>
            @if ($post->excerpt)
                <p class="mt-4 text-lg text-slate-600 dark:text-slate-400">{{ $post->excerpt }}</p>
            @endif
            <div class="markdown-body mt-8 border-t border-slate-200/90 pt-8 dark:border-slate-800/80">
                {!! \App\Support\SafeRichContent::toHtml($post->content) !!}
            </div>
        </article>

        <p class="mt-10 text-center text-sm font-medium">
            <a href="{{ route('blog.index') }}" class="hv-link-quiet">← {{ landing_t('blog.all_posts') }}</a>
        </p>
    </div>
</x-site.layout>
