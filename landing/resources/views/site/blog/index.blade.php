<x-site.layout
    :title="landing_t('blog.page_title')"
    :description="landing_t('blog.meta_description')"
    :canonical-url="route('blog.index', absolute: true)"
    :schema-json-ld="$seoSchema"
>
    <div class="hv-container">
        <div class="mb-10 max-w-3xl">
            <h1 class="text-3xl font-semibold tracking-tight text-slate-900 sm:text-4xl dark:text-slate-50">{{ landing_t('blog.heading') }}</h1>
            <p class="mt-3 text-lg leading-relaxed text-slate-600 dark:text-slate-400">{{ landing_t('blog.subtitle') }}</p>
        </div>

        @if ($categories->isNotEmpty())
            <div class="mb-10 flex flex-wrap gap-2">
                <span class="self-center text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-slate-500">{{ landing_t('blog.categories_label') }}</span>
                @foreach ($categories as $cat)
                    <a href="{{ route('blog.category', $cat->slug) }}" class="rounded-full border border-slate-200/90 bg-white/90 px-3 py-1 text-sm font-medium text-slate-700 transition-colors hover:border-[rgb(var(--hv-brand-500)/0.45)] hover:text-[rgb(var(--hv-brand-600)/1)] dark:border-slate-700 dark:bg-slate-900/50 dark:text-slate-200 dark:hover:text-[rgb(var(--hv-brand-400)/1)]">
                        {{ $cat->name }}
                    </a>
                @endforeach
            </div>
        @endif

        @if ($posts->isEmpty())
            <div class="rounded-2xl border border-dashed border-slate-300/90 bg-slate-50/80 px-8 py-14 text-center text-base text-slate-500 dark:border-slate-700 dark:bg-slate-900/40 dark:text-slate-500">
                {{ landing_t('blog.empty') }}
            </div>
        @else
            <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($posts as $post)
                    <a href="{{ route('blog.show', $post->slug) }}" class="hv-blog-card group flex flex-col p-6">
                        <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-500">
                                {{ $post->published_at?->translatedFormat('d M Y') }}
                            </p>
                            @if ($post->category)
                                <span class="text-xs text-slate-400 dark:text-slate-600">·</span>
                                <span class="text-xs font-medium text-[rgb(var(--hv-brand-600)/1)] dark:text-[rgb(var(--hv-brand-400)/1)]">{{ $post->category->name }}</span>
                            @endif
                        </div>
                        <h2 class="mt-3 text-lg font-semibold text-slate-900 transition-colors group-hover:text-[rgb(var(--hv-brand-600)/1)] dark:text-slate-100 dark:group-hover:text-[rgb(var(--hv-brand-400)/1)]">{{ $post->title }}</h2>
                        @if ($post->excerpt)
                            <p class="mt-2 line-clamp-3 text-base leading-relaxed text-slate-600 dark:text-slate-400">{{ $post->excerpt }}</p>
                        @endif
                        <span class="mt-5 text-sm font-semibold hv-text-brand">{{ landing_t('blog.read_more') }}</span>
                    </a>
                @endforeach
            </div>

            <div class="mt-12">
                {{ $posts->links() }}
            </div>
        @endif
    </div>
</x-site.layout>
