@php
    $hvFavicon = \App\Services\LandingAppearance::faviconUrl();
    $hvFaviconType = \App\Services\LandingAppearance::faviconMimeType();
    $hvGa = \App\Services\LandingAppearance::analyticsMeasurementId();
@endphp
@if ($hvFavicon && $hvFaviconType)
    <link rel="icon" href="{{ $hvFavicon }}" type="{{ $hvFaviconType }}">
@endif
@if ($hvGa)
    <script async src="https://www.googletagmanager.com/gtag/js?id={{ $hvGa }}"></script>
    <script>
        window.dataLayer = window.dataLayer || [];
        function gtag(){dataLayer.push(arguments);}
        gtag('js', new Date());
        gtag('config', @json($hvGa));
    </script>
@endif
