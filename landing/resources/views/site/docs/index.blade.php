<x-site.layout
    :title="landing_t('docs.index_page_title', ['brand' => landing_p('brand.name')])"
    :description="$seoDescription"
    :canonical-url="$seoCanonical"
    :schema-json-ld="$seoSchema"
>
    <div class="hv-container">
        <div class="mb-10 max-w-3xl">
            <h1 class="text-3xl font-semibold tracking-tight text-slate-900 sm:text-4xl dark:text-slate-50">{{ landing_t('docs.index_heading') }}</h1>
            <p class="mt-3 text-lg leading-relaxed text-slate-600 dark:text-slate-400">{{ landing_t('docs.index_lead') }}</p>
        </div>

        @if ($roots->isEmpty())
            <div class="rounded-2xl border border-dashed border-slate-300/90 bg-slate-50/80 px-8 py-14 text-center text-base text-slate-500 dark:border-slate-700 dark:bg-slate-900/40 dark:text-slate-500">
                {{ landing_t('docs.index_empty') }}
            </div>
        @else
            <div class="space-y-6">
                @foreach ($roots as $root)
                    <div class="rounded-2xl border border-slate-200/90 bg-white/90 p-6 dark:border-slate-800 dark:bg-slate-900/60">
                        <h2 class="text-xl font-semibold text-slate-900 dark:text-slate-100">
                            <a href="{{ route('docs.show', $root->slug) }}" class="font-semibold text-slate-900 transition-colors hover:text-[rgb(var(--hv-brand-600)/1)] dark:text-slate-100 dark:hover:text-[rgb(var(--hv-brand-400)/1)]">{{ $root->title }}</a>
                        </h2>
                        @if ($root->children->isNotEmpty())
                            <ul class="mt-4 space-y-2 border-t border-slate-200/90 pt-4 text-base dark:border-slate-800/80">
                                @foreach ($root->children as $child)
                                    <li>
                                        <a href="{{ route('docs.show', $child->slug) }}" class="hv-link font-medium">
                                            {{ $child->title }}
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</x-site.layout>
