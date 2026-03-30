@php echo '<?xml version="1.0" encoding="UTF-8"?>'; @endphp
<rss version="2.0">
    <channel>
        <title>{{ $site->name }}</title>
        <link>{{ $baseUrl === '' ? 'index.html' : $baseUrl }}</link>
        <description>{{ $site->business_description ?: "Latest articles from {$site->name}." }}</description>
        <language>{{ $site->language }}</language>
@foreach ($items as $item)
        <item>
            <title><![CDATA[{{ $item['title'] }}]]></title>
            <link>{{ $item['link'] }}</link>
            <guid>{{ $item['guid'] }}</guid>
            @if (!empty($item['published_at']))
            <pubDate>{{ $item['published_at']->toRssString() }}</pubDate>
            @endif
            <description><![CDATA[{{ $item['description'] }}]]></description>
        </item>
@endforeach
    </channel>
</rss>
