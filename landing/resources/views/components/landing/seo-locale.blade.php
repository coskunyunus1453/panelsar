@php
    $hvLocales = $landingEnabledLocales ?? [];
    $hvDefault = $landingDefaultLocale ?? config('app.locale', 'en');
@endphp
@if (count($hvLocales) > 1)
    @foreach ($hvLocales as $hvCode)
        <link rel="alternate" hreflang="{{ str_replace('_', '-', $hvCode) }}" href="{{ landing_localized_url($hvCode) }}">
    @endforeach
    <link rel="alternate" hreflang="x-default" href="{{ landing_localized_url($hvDefault) }}">
    @foreach ($hvLocales as $hvCode)
        @if ($hvCode !== app()->getLocale())
            <meta property="og:locale:alternate" content="{{ landing_og_locale_tag($hvCode) }}">
        @endif
    @endforeach
@endif
