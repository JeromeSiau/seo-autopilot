<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', $site->language ?? 'en') }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $metaTitle ?? ($pageTitle ?? $site->name) }}</title>
    @if (!empty($metaDescription))
        <meta name="description" content="{{ $metaDescription }}">
    @endif
    @if (!empty($metaRobots))
        <meta name="robots" content="{{ $metaRobots }}">
    @endif
    @if (!empty($currentUrl))
        <link rel="canonical" href="{{ $currentUrl }}">
        <meta property="og:url" content="{{ $currentUrl }}">
        <meta name="twitter:url" content="{{ $currentUrl }}">
    @endif
    <meta property="og:type" content="{{ $ogType ?? 'website' }}">
    <meta property="og:title" content="{{ $socialTitle ?? ($metaTitle ?? ($pageTitle ?? $site->name)) }}">
    <meta name="twitter:card" content="summary">
    <meta name="twitter:title" content="{{ $socialTitle ?? ($metaTitle ?? ($pageTitle ?? $site->name)) }}">
    @if (!empty($metaDescription) || !empty($socialDescription))
        <meta property="og:description" content="{{ $socialDescription ?? $metaDescription }}">
        <meta name="twitter:description" content="{{ $socialDescription ?? $metaDescription }}">
    @endif
    <meta property="og:site_name" content="{{ $theme['brand_name'] ?? $site->name }}">
    @if (!empty($socialImageUrl))
        <meta property="og:image" content="{{ $socialImageUrl }}">
        <meta name="twitter:image" content="{{ $socialImageUrl }}">
    @endif
    @if (!empty($structuredData))
        @foreach ($structuredData as $schema)
            <script type="application/ld+json">{!! json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
        @endforeach
    @endif
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
        <a href="{{ $homeHref ?? '/' }}" style="display:flex; align-items:center; gap:14px;">
            @if (!empty($brandLogoUrl))
                <img src="{{ $brandLogoUrl }}" alt="{{ $brandName }} logo" class="brand-logo">
            @else
                <span class="brand-mark" aria-hidden="true"></span>
            @endif
            <div>
                <strong style="display:block; font-size:1rem;">{{ $brandName }}</strong>
                <span class="muted">Hosted by SEO Autopilot</span>
            </div>
        </a>

        <nav aria-label="Primary">
            @foreach ($navigation as $item)
                <a
                    href="{{ $item['href'] }}"
                    @if (!empty($item['openInNewTab']))
                        target="_blank" rel="noopener noreferrer"
                    @endif
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

        <div style="display:flex; gap:16px; flex-wrap:wrap; align-items:flex-start;">
            @if (!empty($footerNavigation))
                @foreach ($footerNavigation as $item)
                    <a
                        href="{{ $item['href'] }}"
                        @if (!empty($item['openInNewTab']))
                            target="_blank" rel="noopener noreferrer"
                        @endif
                    >
                        {{ $item['label'] }}
                    </a>
                @endforeach
            @endif

            @if ($socialLinks->isNotEmpty())
                @foreach ($socialLinks as $network => $url)
                    <a href="{{ $url }}" target="_blank" rel="noopener noreferrer">{{ ucfirst((string) $network) }}</a>
                @endforeach
            @endif
        </div>
    </div>
</footer>
</body>
</html>
