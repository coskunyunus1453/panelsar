@php
    use Illuminate\Support\Str;
    $robots = $robotsContent ?? (($site->enable_indexing ?? true) ? 'index, follow' : 'noindex, nofollow');
    $listingFormAction = isset($activeTag)
        ? route('community.tag', $activeTag)
        : (isset($activeCategory) ? route('community.category', $activeCategory) : route('community.index'));
@endphp
<x-site.layout
    :title="$seoTitle"
    :description="$seoDescription"
    :canonical-url="$canonicalUrl"
    :robots-content="$robots"
    :schema-json-ld="$schemaJsonLd ?? null"
>
    <div class="hv-container py-10">
        <div class="mb-6 flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div class="max-w-2xl">
                <h1 class="text-3xl font-semibold tracking-tight text-slate-900 dark:text-slate-50">{{ $site->displaySiteTitle() }}</h1>
                <p class="mt-2 text-slate-600 dark:text-slate-400">{{ landing_t('community.index_subtitle') }}</p>
            </div>
            <x-community.ask-cta layout="hero" :category="$activeCategory ?? null" class="lg:pt-1" />
        </div>

        <section class="mb-8 rounded-2xl border border-[rgb(var(--hv-brand-500)/0.35)] bg-gradient-to-br from-[rgb(var(--hv-brand-500)/0.12)] to-transparent p-5 sm:p-6 dark:border-[rgb(var(--hv-brand-500)/0.25)] dark:from-[rgb(var(--hv-brand-600)/0.15)]">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div class="min-w-0">
                    <h2 class="text-lg font-semibold text-slate-900 dark:text-slate-50">{{ landing_t('community.cta_have_question_title') }}</h2>
                    <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">{{ landing_t('community.cta_have_question_lead') }}</p>
                </div>
                <x-community.ask-cta layout="strip" :category="$activeCategory ?? null" />
            </div>
        </section>

        <div class="sticky top-[4.25rem] z-10 -mx-4 mb-6 border-y border-slate-200/80 bg-white/90 px-4 py-3 backdrop-blur-md dark:border-slate-800/80 dark:bg-slate-950/85 sm:-mx-0 sm:rounded-xl sm:border sm:px-4">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">{{ landing_t('community.quick_actions') }}</p>
                <x-community.ask-cta layout="compact" :category="$activeCategory ?? null" />
            </div>
        </div>

        <form method="get" action="{{ $listingFormAction }}" class="mb-6 flex flex-wrap gap-2">
            <input type="search" name="q" value="{{ request('q') }}" placeholder="{{ landing_t('community.search_placeholder') }}" class="min-w-[200px] flex-1 rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-900" />
            <select name="sort" class="rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-900">
                <option value="latest" @selected(request('sort','latest')==='latest')>{{ landing_t('community.sort_latest') }}</option>
                <option value="popular" @selected(request('sort')==='popular')>{{ landing_t('community.sort_popular') }}</option>
                <option value="unanswered" @selected(request('sort')==='unanswered')>{{ landing_t('community.sort_unanswered') }}</option>
            </select>
            <button type="submit" class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white dark:bg-slate-100 dark:text-slate-900">{{ landing_t('community.search') }}</button>
        </form>

        <div class="mb-8 flex flex-wrap gap-2">
            <a href="{{ route('community.index') }}" class="rounded-full px-3 py-1 text-sm font-medium {{ (! isset($activeCategory) && ! isset($activeTag)) ? 'bg-[rgb(var(--hv-brand-500)/0.2)] text-[rgb(var(--hv-brand-700)/1)]' : 'border border-slate-200 dark:border-slate-700' }}">{{ landing_t('community.filter_all') }}</a>
            @foreach ($categories as $cat)
                <a href="{{ route('community.category', $cat->slug) }}" class="rounded-full px-3 py-1 text-sm font-medium {{ isset($activeCategory) && $activeCategory->id === $cat->id ? 'bg-[rgb(var(--hv-brand-500)/0.2)] text-[rgb(var(--hv-brand-700)/1)]' : 'border border-slate-200 dark:border-slate-700' }}">
                    {{ $cat->displayName() }}
                </a>
            @endforeach
        </div>

        @isset($activeTag)
            <div class="mb-6 rounded-xl border border-slate-200 bg-slate-50/90 px-4 py-3 text-sm dark:border-slate-700 dark:bg-slate-900/50">
                <span class="font-semibold text-slate-800 dark:text-slate-100">{{ landing_t('community.tag_label') }}</span>
                <a href="{{ route('community.tag', $activeTag) }}" class="ml-2 font-mono text-[rgb(var(--hv-brand-600)/1)] hover:underline">#{{ $activeTag->name }}</a>
                <a href="{{ route('community.index') }}" class="ml-3 text-slate-500 underline hover:text-slate-800 dark:hover:text-slate-200">{{ landing_t('community.clear_tag') }}</a>
            </div>
        @endisset

        @if (session('status'))
            <div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900 dark:border-emerald-900/40 dark:bg-emerald-950/40 dark:text-emerald-100">{{ session('status') }}</div>
        @endif

        <div class="space-y-3">
            @forelse ($topics as $topic)
                <a href="{{ route('community.topic', $topic->slug) }}" class="block rounded-2xl border border-slate-200/90 bg-white/90 p-5 shadow-sm transition hover:shadow-md dark:border-slate-800 dark:bg-slate-900/50">
                    <div class="flex flex-wrap items-start justify-between gap-2">
                        <div>
                            @if ($topic->is_pinned)
                                <span class="mr-2 text-xs font-bold text-amber-700 dark:text-amber-400">{{ landing_t('community.badge_pinned') }}</span>
                            @endif
                            @if ($topic->is_solved)
                                <span class="text-xs font-bold text-emerald-700 dark:text-emerald-400">{{ landing_t('community.badge_solved') }}</span>
                            @endif
                            <h2 class="mt-1 text-lg font-semibold text-slate-900 dark:text-slate-100">{{ $topic->title }}</h2>
                            <p class="mt-1 text-sm text-slate-600 dark:text-slate-400 line-clamp-2">{{ $topic->excerpt }}</p>
                            @if ($topic->relationLoaded('tags') && $topic->tags->isNotEmpty())
                                <div class="mt-2 flex flex-wrap gap-1.5">
                                    @foreach ($topic->tags->take(4) as $tg)
                                        <a href="{{ route('community.tag', $tg) }}" class="rounded-md bg-slate-100 px-1.5 py-0.5 text-[11px] font-medium text-slate-600 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700">#{{ Str::limit($tg->name, 24) }}</a>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                        <span class="text-xs text-slate-500">{{ landing_t('community.views_count', ['count' => $topic->view_count]) }}</span>
                    </div>
                </a>
            @empty
                <div class="rounded-2xl border border-dashed border-slate-300 px-6 py-12 text-center dark:border-slate-700">
                    <p class="text-slate-500 dark:text-slate-400">{{ landing_t('community.empty_topics') }}</p>
                    <div class="mt-6 flex justify-center">
                        <x-community.ask-cta layout="strip" :category="$activeCategory ?? null" />
                    </div>
                </div>
            @endforelse
        </div>

        <div class="mt-8">
            {{ $topics->links() }}
        </div>

        <div class="pointer-events-none fixed bottom-5 right-5 z-40 flex flex-col items-end gap-3 md:hidden">
            <div class="pointer-events-auto">
                <x-community.ask-cta layout="fab" :category="$activeCategory ?? null" />
            </div>
        </div>
    </div>
</x-site.layout>
