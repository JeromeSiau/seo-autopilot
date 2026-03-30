@extends('hosted.layout')

@section('content')
    <section class="page-shell">
        <div class="container article-layout">
            <article class="content">
                @if (!empty($article->featured_image_url))
                    <img src="{{ $article->featured_image_url }}" alt="{{ $article->title }}" class="article-cover">
                @endif
                <div class="taxonomy-pills" style="margin-bottom:14px;">
                    @if ($article->hostedCategory && !empty($categoryHref))
                        <a href="{{ $categoryHref }}" class="taxonomy-pill">{{ $article->hostedCategory->name }}</a>
                    @endif
                    @if ($article->hostedAuthor && !empty($authorHref))
                        <a href="{{ $authorHref }}" class="taxonomy-pill">{{ $article->hostedAuthor->name }}</a>
                    @endif
                </div>
                <p class="article-meta">
                    {{ optional($article->published_at)->format('M d, Y') ?: optional($article->created_at)->format('M d, Y') }}
                </p>
                <h1 class="page-title">{{ $article->title }}</h1>

                @if (!empty($article->meta_description))
                    <p class="muted" style="font-size:1.05rem; margin-bottom:24px;">{{ $article->meta_description }}</p>
                @endif

                @if ($article->hostedTags->isNotEmpty())
                    <div class="article-tags" style="margin-bottom:24px;">
                        @foreach ($article->hostedTags as $tag)
                            @if (!empty($tagHrefs[$tag->id] ?? null))
                                <a href="{{ $tagHrefs[$tag->id] }}" class="taxonomy-pill">{{ $tag->name }}</a>
                            @endif
                        @endforeach
                    </div>
                @endif

                {!! $articleContentHtml !!}
            </article>

            <aside class="sidebar-card">
                @if ($article->hostedAuthor)
                    <div class="author-summary">
                        @if (!empty($article->hostedAuthor->avatar_url))
                            <img src="{{ $article->hostedAuthor->avatar_url }}" alt="{{ $article->hostedAuthor->name }}" class="author-avatar">
                        @else
                            <span class="brand-mark" aria-hidden="true"></span>
                        @endif
                        <div>
                            <p class="eyebrow">Author</p>
                            @if (!empty($authorHref))
                                <a href="{{ $authorHref }}" style="font-weight:700; display:block; margin-top:4px;">{{ $article->hostedAuthor->name }}</a>
                            @else
                                <strong style="display:block; margin-top:4px;">{{ $article->hostedAuthor->name }}</strong>
                            @endif
                            @if (!empty($article->hostedAuthor->bio))
                                <p class="muted" style="margin-top:8px;">{{ $article->hostedAuthor->bio }}</p>
                            @endif
                        </div>
                    </div>
                @endif

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
