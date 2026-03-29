@php echo '<?xml version="1.0" encoding="UTF-8"?>'; @endphp
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
@foreach ($urls as $item)
    <url>
        <loc>{{ $item['loc'] }}</loc>
        <lastmod>{{ optional($item['lastmod'])->toAtomString() }}</lastmod>
    </url>
@endforeach
</urlset>
