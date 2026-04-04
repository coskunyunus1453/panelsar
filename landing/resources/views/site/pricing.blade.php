@php
    $pageTitle = landing_t('pricing_page.title');
    $meta = $intro?->meta_description ?? landing_t('pricing_page.meta_default');
@endphp

<x-site.layout :title="$pageTitle" :description="$meta">
    <div class="hv-container">
        <div class="mb-10 max-w-3xl">
            <div class="hv-section-eyebrow mb-4">{{ landing_t('pricing_page.badge') }}</div>
            <h1 class="text-3xl font-semibold tracking-tight text-slate-900 sm:text-4xl dark:text-slate-50">
                {{ $intro?->title ?? landing_t('pricing_page.fallback_intro') }}
            </h1>
            @if ($intro)
                <div class="markdown-body mt-6">
                    {!! \App\Support\SafeMarkdown::toHtml($intro->content) !!}
                </div>
            @else
                <p class="mt-3 text-lg leading-relaxed text-slate-600 dark:text-slate-400">
                    {{ landing_t('pricing_page.fallback_lead') }}
                </p>
            @endif
        </div>

        @if ($plans->isEmpty())
            <div class="rounded-2xl border border-dashed border-slate-300/90 bg-slate-50/80 px-8 py-12 text-center text-base text-slate-500 dark:border-slate-700 dark:bg-slate-900/40 dark:text-slate-500">
                {{ landing_t('pricing_page.empty') }}
            </div>
        @else
            <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($plans as $plan)
                    <div class="{{ $plan->is_featured ? 'hv-card-pro' : 'relative flex flex-col rounded-2xl border border-slate-200/90 bg-white/90 p-6 dark:border-slate-800 dark:bg-slate-900/60' }}">
                        @if ($plan->is_featured)
                            <span class="hv-card-pro-badge">
                                {{ landing_t('pricing_page.featured') }}
                            </span>
                        @endif
                        <h2 class="pr-16 text-lg font-semibold text-slate-900 dark:text-slate-100">{{ $plan->name }}</h2>
                        @if ($plan->subtitle)
                            <p class="mt-1 text-base text-slate-600 dark:text-slate-400">{{ $plan->subtitle }}</p>
                        @endif
                        <div class="mt-4 flex items-baseline gap-1">
                            <span class="text-3xl font-semibold {{ $plan->is_featured ? 'hv-text-brand' : 'text-slate-900 dark:text-slate-100' }}">{{ $plan->price_label }}</span>
                            @if ($plan->price_note)
                                <span class="text-sm text-slate-500 dark:text-slate-500">{{ $plan->price_note }}</span>
                            @endif
                        </div>
                        @if (is_array($plan->features) && count($plan->features) > 0)
                            <ul class="mt-5 flex-1 space-y-2 text-base text-slate-700 dark:text-slate-300">
                                @foreach ($plan->features as $line)
                                    <li class="flex gap-2">
                                        <span class="mt-0.5 text-[rgb(var(--hv-brand-500)/0.9)]">•</span>
                                        <span>{{ $line }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif

        <p class="mt-12 text-center text-sm text-slate-500 dark:text-slate-500">
            <a href="{{ route('landing.home') }}" class="hv-link-quiet font-semibold">{{ landing_t('pricing_page.back_home') }}</a>
        </p>
    </div>
</x-site.layout>
