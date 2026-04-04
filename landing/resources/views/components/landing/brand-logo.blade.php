@props([
    'variant' => 'classic',
])

@php
    $logoUrl = \App\Services\LandingAppearance::siteLogoUrl();
    $name = landing_p('brand.name');
    $initial = mb_substr($name, 0, 1, 'UTF-8') ?: '?';
@endphp

@if ($variant === 'classic')
    @if ($logoUrl)
        <div {{ $attributes->merge(['class' => 'flex shrink-0 items-center']) }}>
            <img
                src="{{ $logoUrl }}"
                alt="{{ $name }}"
                class="hv-brand-logo-mark block"
                style="{{ \App\Services\LandingAppearance::siteLogoImgStyle('header') }}"
                loading="eager"
                decoding="async"
            />
        </div>
    @else
        <div {{ $attributes->merge(['class' => 'hv-brand-logo-box']) }}>
            <span class="text-lg font-bold hv-text-brand">{{ $initial }}</span>
        </div>
    @endif
@elseif ($variant === 'neon')
    @if ($logoUrl)
        <span {{ $attributes->merge(['class' => 'flex min-w-0 shrink-0 items-center']) }}>
            <img
                src="{{ $logoUrl }}"
                alt="{{ $name }}"
                class="hv-brand-logo-mark block"
                style="{{ \App\Services\LandingAppearance::siteLogoImgStyle('header') }}"
                loading="eager"
                decoding="async"
            />
        </span>
    @else
        <span {{ $attributes->merge(['class' => 'hv-neon-logo flex h-9 w-9 shrink-0 items-center justify-center rounded-xl text-sm font-bold sm:h-10 sm:w-10']) }}>
            {{ $initial }}
        </span>
    @endif
@elseif ($variant === 'neon-footer')
    @if ($logoUrl)
        <span {{ $attributes->merge(['class' => 'flex shrink-0 items-center']) }}>
            <img
                src="{{ $logoUrl }}"
                alt="{{ $name }}"
                class="hv-brand-logo-mark block"
                style="{{ \App\Services\LandingAppearance::siteLogoImgStyle('footer') }}"
                loading="lazy"
                decoding="async"
            />
        </span>
    @else
        <span {{ $attributes->merge(['class' => 'hv-neon-logo flex h-8 w-8 items-center justify-center rounded-lg text-xs font-bold']) }}>
            {{ $initial }}
        </span>
    @endif
@endif
