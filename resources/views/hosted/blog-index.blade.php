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
                            <p class="meta">
                                {{ optional($article->published_at)->format('M d, Y') ?: optional($article->created_at)->format('M d, Y') }}
                            </p>
                            <h2 style="margin:10px 0 12px; font-size:1.5rem;">
                                <a href="{{ $baseUrl === '' ? $article->slug . '.html' : $baseUrl . '/blog/' . $article->slug }}">
                                    {{ $article->title }}
                                </a>
                            </h2>
                            <p class="muted">{{ $article->meta_description ?: \Illuminate\Support\Str::limit(strip_tags((string) $article->content), 200) }}</p>
                        </article>
                    @endforeach
                </div>
            @endif
        </div>
    </section>
@endsection
