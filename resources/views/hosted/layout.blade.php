<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', $site->language ?? 'en') }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $metaTitle ?? ($pageTitle ?? $site->name) }}</title>
    @if (!empty($metaDescription))
        <meta name="description" content="{{ $metaDescription }}">
    @endif
    @if (!empty($currentUrl))
        <link rel="canonical" href="{{ $currentUrl }}">
        <meta property="og:url" content="{{ $currentUrl }}">
    @endif
    <meta property="og:type" content="website">
    <meta property="og:title" content="{{ $metaTitle ?? ($pageTitle ?? $site->name) }}">
    @if (!empty($metaDescription))
        <meta property="og:description" content="{{ $metaDescription }}">
    @endif
    <meta property="og:site_name" content="{{ $theme['brand_name'] ?? $site->name }}">
    <style>{!! $css !!}</style>
</head>
<body>
@php
    $brandName = $theme['brand_name'] ?? $site->name;
    $footerText = $theme['footer_text'] ?? "Published by {$site->name}";
    $socialLinks = collect($theme['social_links'] ?? [])->filter(fn ($value) => filled($value));
@endphp

<header class="site-header">
    <div class="container brand">
        <a href="{{ $navigation[0]['href'] }}" style="display:flex; align-items:center; gap:14px;">
            <span class="brand-mark" aria-hidden="true"></span>
            <div>
                <strong style="display:block; font-size:1rem;">{{ $brandName }}</strong>
                <span class="muted">Hosted by SEO Autopilot</span>
            </div>
        </a>

        <nav aria-label="Primary">
            @foreach ($navigation as $item)
                <a
                    href="{{ $item['href'] }}"
                    @if (($item['path'] ?? null) === ($currentPath ?? null))
                        style="color:var(--hosted-accent); font-weight:700;"
                    @endif
                >
                    {{ $item['label'] }}
                </a>
            @endforeach
        </nav>
    </div>
</header>

<main>
    @yield('content')
</main>

<footer class="site-footer">
    <div class="container" style="display:flex; justify-content:space-between; gap:16px; flex-wrap:wrap;">
        <div>
            <strong style="display:block; margin-bottom:6px;">{{ $brandName }}</strong>
            <span>{{ $footerText }}</span>
        </div>

        @if ($socialLinks->isNotEmpty())
            <div style="display:flex; gap:16px; flex-wrap:wrap;">
                @foreach ($socialLinks as $network => $url)
                    <a href="{{ $url }}" target="_blank" rel="noopener noreferrer">{{ ucfirst((string) $network) }}</a>
                @endforeach
            </div>
        @endif
    </div>
</footer>
</body>
</html>
