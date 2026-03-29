@extends('hosted.layout')

@section('content')
    <section class="page-shell">
        <div class="container article-layout">
            <article class="content">
                <p class="article-meta">
                    {{ optional($article->published_at)->format('M d, Y') ?: optional($article->created_at)->format('M d, Y') }}
                </p>
                <h1 class="page-title">{{ $article->title }}</h1>

                @if (!empty($article->meta_description))
                    <p class="muted" style="font-size:1.05rem; margin-bottom:24px;">{{ $article->meta_description }}</p>
                @endif

                {!! $articleContentHtml !!}
            </article>

            <aside class="sidebar-card">
                <p class="section-title" style="margin-top:0;">About {{ $theme['brand_name'] ?? $site->name }}</p>
                <p class="muted">{{ $site->business_description ?: "SEO content generated and published through {$site->name}." }}</p>

                <div style="margin-top:24px; display:grid; gap:10px;">
                    @foreach ($navigation as $item)
                        <a href="{{ $item['href'] }}" style="color:var(--hosted-accent);">
                            {{ $item['label'] }}
                        </a>
                    @endforeach
                </div>
            </aside>
        </div>
    </section>
@endsection
