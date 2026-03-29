@php echo '<?xml version="1.0" encoding="UTF-8"?>'; @endphp
<rss version="2.0">
    <channel>
        <title>{{ $site->name }}</title>
        <link>{{ $baseUrl === '' ? 'index.html' : $baseUrl }}</link>
        <description>{{ $site->business_description ?: "Latest articles from {$site->name}." }}</description>
        <language>{{ $site->language }}</language>
@foreach ($articles as $article)
        <item>
            <title><![CDATA[{{ $article->title }}]]></title>
            <link>{{ $baseUrl === '' ? 'blog/' . $article->slug . '.html' : $baseUrl . '/blog/' . $article->slug }}</link>
            <guid>{{ $baseUrl === '' ? 'blog/' . $article->slug . '.html' : $baseUrl . '/blog/' . $article->slug }}</guid>
            @if (!empty($article->published_at))
            <pubDate>{{ $article->published_at->toRssString() }}</pubDate>
            @endif
            <description><![CDATA[{{ $article->meta_description ?: \Illuminate\Support\Str::limit(strip_tags((string) $article->content), 180) }}]]></description>
        </item>
@endforeach
    </channel>
</rss>
