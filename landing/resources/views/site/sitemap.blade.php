{!! '<'.'?xml version="1.0" encoding="UTF-8"?>' !!}
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
@foreach ($entries as $e)
    <url>
        <loc>{{ $e['loc'] }}</loc>
        @if (! empty($e['lastmod']))
        <lastmod>{{ $e['lastmod']->toAtomString() }}</lastmod>
        @endif
    </url>
@endforeach
</urlset>
