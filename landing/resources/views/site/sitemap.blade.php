{!! '<'.'?xml version="1.0" encoding="UTF-8"?>' !!}
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"
        xmlns:xhtml="http://www.w3.org/1999/xhtml">
@foreach ($entries as $e)
    <url>
        <loc>{{ $e['loc'] }}</loc>
        @if (! empty($e['lastmod']))
        <lastmod>{{ $e['lastmod']->toAtomString() }}</lastmod>
        @endif
        @foreach ($e['alternates'] ?? [] as $alt)
        <xhtml:link rel="alternate" hreflang="{{ $alt['hreflang'] }}" href="{{ $alt['href'] }}" />
        @endforeach
    </url>
@endforeach
</urlset>
