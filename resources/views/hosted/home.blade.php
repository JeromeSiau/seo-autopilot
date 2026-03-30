@extends('hosted.layout')

@section('content')
    <section class="hero">
        <div class="container hero-grid">
            <div class="hero-panel">
                <span class="pill">Hosted blog</span>
                <h2>{{ $heroTitle }}</h2>
                @if (!empty($heroDescription))
                    <p>{{ $heroDescription }}</p>
                @endif
            </div>

            <div class="hero-panel">
                <p class="section-title" style="margin-top:0;">About this publication</p>
                @if (!empty($pageBodyHtml))
                    <div class="content" style="padding:0; background:none; border:none; box-shadow:none;">
                        {!! $pageBodyHtml !!}
                    </div>
                @else
                    <p>{{ $site->business_description ?: "Fresh articles and practical insights from {$site->name}." }}</p>
                @endif
            </div>
        </div>
    </section>

    <section class="page-shell">
        <div class="container">
            @include('hosted.partials.sections', ['sections' => $sections ?? []])

            <h3 class="section-title">Latest articles</h3>

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
                            <h3 style="margin:10px 0 12px; font-size:1.35rem;">
                                <a href="{{ $article['href'] }}">
                                    {{ $article['title'] }}
                                </a>
                            </h3>
                            <p class="muted">{{ $article['meta_description'] ?: \Illuminate\Support\Str::limit(strip_tags((string) $article['content']), 160) }}</p>
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
