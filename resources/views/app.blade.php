<!DOCTYPE html>
@php
    // Priority: 1) User preference from DB, 2) Cookie, 3) System preference (JS)
    $user = auth()->user();
    $theme = $user?->theme ?? request()->cookie('theme');

    // Resolve 'system' to actual theme using cookie hint, or let JS handle it
    if ($theme === 'system') {
        $theme = null;
    } elseif (!in_array($theme, ['light', 'dark'])) {
        $theme = null;
    }
@endphp
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="{{ $theme }}">
    <head>
        @unless($theme)
        <!-- Resolve system preference if no cookie set -->
        <script>
            (function() {
                const theme = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
                document.documentElement.classList.add(theme);
            })();
        </script>
        @endunless
        <style>
            /* Critical CSS to prevent flash before Tailwind loads */
            html.dark body { background-color: #1a1a1a; }
            html.light body, html:not(.dark):not(.dark) body { background-color: #fafaf8; }
        </style>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title inertia>{{ config('app.name', 'Laravel') }}</title>

        <!-- Favicons -->
        <link rel="icon" type="image/svg+xml" href="/favicon.svg">
        <link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png">
        <link rel="icon" type="image/png" sizes="16x16" href="/favicon-16x16.png">
        <link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">
        <link rel="manifest" href="/site.webmanifest">
        <meta name="theme-color" content="#10b981">

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,200..800&family=DM+Sans:ital,opsz,wght@0,9..40,100..1000;1,9..40,100..1000&display=swap" rel="stylesheet">

        <!-- Scripts -->
        @routes
        @viteReactRefresh
        @vite(['resources/js/app.tsx', "resources/js/Pages/{$page['component']}.tsx"])
        @inertiaHead
    </head>
    <body class="font-sans antialiased bg-surface-50 dark:bg-surface-900">
        @inertia
    </body>
</html>
