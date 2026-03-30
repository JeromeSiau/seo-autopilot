@extends('hosted.layout')

@section('content')
    <section class="page-shell">
        <div class="container">
            <h1 class="page-title">{{ $pageTitle }}</h1>
            <p class="muted" style="margin-bottom:28px;">The latest published content from {{ $theme['brand_name'] ?? $site->name }}.</p>

            @if ($articles->isEmpty())
                <div class="content">
                    <p>No articles are published yet.</p>
                </div>
            @else
                <div class="cards">
                    @foreach ($articles as $article)
                        <article class="card">
                            @if (!empty($article['featured_image_url']))
                                <img src="{{ $article['featured_image_url'] }}" alt="{{ $article['title'] }}" class="card-image">
                            @endif
                            <div class="taxonomy-pills" style="margin-bottom:12px;">
                                @if (!empty($article['category']))
                                    <a href="{{ $article['category']['href'] }}" class="taxonomy-pill">{{ $article['category']['name'] }}</a>
                                @endif
                                @if (!empty($article['author']))
                                    <a href="{{ $article['author']['href'] }}" class="taxonomy-pill">{{ $article['author']['name'] }}</a>
                                @endif
                            </div>
                            <p class="meta">
                                {{ optional($article['published_at'])->format('M d, Y') ?: optional($article['created_at'])->format('M d, Y') }}
                            </p>
                            <h2 style="margin:10px 0 12px; font-size:1.5rem;">
                                <a href="{{ $article['href'] }}">
                                    {{ $article['title'] }}
                                </a>
                            </h2>
                            <p class="muted">{{ $article['meta_description'] ?: \Illuminate\Support\Str::limit(strip_tags((string) $article['content']), 200) }}</p>
                            @if (!empty($article['tags']))
                                <div class="article-tags" style="margin-top:14px;">
                                    @foreach ($article['tags'] as $tag)
                                        <a href="{{ $tag['href'] }}" class="taxonomy-pill">{{ $tag['name'] }}</a>
                                    @endforeach
                                </div>
                            @endif
                        </article>
                    @endforeach
                </div>
            @endif
        </div>
    </section>
@endsection
