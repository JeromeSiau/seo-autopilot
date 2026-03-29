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
            <h3 class="section-title">Latest articles</h3>

            @if ($articles->isEmpty())
                <div class="content">
                    <p>No articles are published yet.</p>
                </div>
            @else
                <div class="cards">
                    @foreach ($articles as $article)
                        <article class="card">
                            <p class="meta">
                                {{ optional($article->published_at)->format('M d, Y') ?: optional($article->created_at)->format('M d, Y') }}
                            </p>
                            <h3 style="margin:10px 0 12px; font-size:1.35rem;">
                                <a href="{{ $baseUrl === '' ? 'blog/' . $article->slug . '.html' : $baseUrl . '/blog/' . $article->slug }}">
                                    {{ $article->title }}
                                </a>
                            </h3>
                            <p class="muted">{{ $article->meta_description ?: \Illuminate\Support\Str::limit(strip_tags((string) $article->content), 160) }}</p>
                        </article>
                    @endforeach
                </div>
            @endif
        </div>
    </section>
@endsection
