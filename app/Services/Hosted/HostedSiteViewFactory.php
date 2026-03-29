<?php

namespace App\Services\Hosted;

use App\Models\Article;
use App\Models\HostedPage;
use App\Models\Site;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\URL;

class HostedSiteViewFactory
{
    public function __construct(
        private readonly HostedContentSanitizer $sanitizer,
    ) {}

    public function home(Site $site, ?string $baseUrl = null): array
    {
        $page = $site->hostedPages->firstWhere('kind', HostedPage::KIND_HOME);
        $articles = $this->recentArticles($site, 6);

        return [
            ...$this->basePayload($site, $baseUrl, '/'),
            'metaTitle' => $page?->meta_title ?: ($site->hosting?->theme_settings['brand_name'] ?? $site->name),
            'metaDescription' => $page?->meta_description ?: $site->business_description,
            'pageTitle' => $page?->title ?: 'Home',
            'heroTitle' => $site->hosting?->theme_settings['hero_title'] ?? $site->name,
            'heroDescription' => $site->hosting?->theme_settings['hero_description'] ?? $site->business_description,
            'pageBodyHtml' => $this->sanitizer->sanitize($page?->body_html),
            'articles' => $articles,
        ];
    }

    public function blogIndex(Site $site, ?string $baseUrl = null): array
    {
        return [
            ...$this->basePayload($site, $baseUrl, '/blog'),
            'metaTitle' => "{$site->name} Blog",
            'metaDescription' => "Latest articles from {$site->name}.",
            'pageTitle' => 'Blog',
            'articles' => $this->recentArticles($site, 100),
        ];
    }

    public function article(Site $site, Article $article, ?string $baseUrl = null): array
    {
        return [
            ...$this->basePayload($site, $baseUrl, "/blog/{$article->slug}"),
            'metaTitle' => $article->meta_title ?: $article->title,
            'metaDescription' => $article->meta_description,
            'pageTitle' => $article->title,
            'article' => $article,
            'articleContentHtml' => $this->sanitizer->sanitize($article->content),
        ];
    }

    public function staticPage(Site $site, HostedPage $page, string $path, ?string $baseUrl = null): array
    {
        return [
            ...$this->basePayload($site, $baseUrl, $path),
            'metaTitle' => $page->meta_title ?: $page->title,
            'metaDescription' => $page->meta_description,
            'pageTitle' => $page->title,
            'page' => $page,
            'pageContentHtml' => $this->sanitizer->sanitize($page->body_html),
        ];
    }

    public function sitemap(Site $site, ?string $baseUrl = null): array
    {
        $baseUrl = $this->baseUrl($site, $baseUrl);
        $urls = collect([
            ['loc' => $baseUrl . '/', 'lastmod' => now()],
            ['loc' => $baseUrl . '/blog', 'lastmod' => now()],
        ]);

        $site->hostedPages->each(function (HostedPage $page) use ($urls, $baseUrl) {
            $path = match ($page->kind) {
                HostedPage::KIND_HOME => '/',
                HostedPage::KIND_ABOUT => '/about',
                HostedPage::KIND_LEGAL => '/legal',
                default => null,
            };

            if ($path) {
                $urls->push([
                    'loc' => $baseUrl . $path,
                    'lastmod' => $page->updated_at ?? now(),
                ]);
            }
        });

        $site->articles()
            ->where('status', Article::STATUS_PUBLISHED)
            ->latest('published_at')
            ->get()
            ->each(fn (Article $article) => $urls->push([
                'loc' => $baseUrl . "/blog/{$article->slug}",
                'lastmod' => $article->updated_at ?? now(),
            ]));

        return [
            'site' => $site,
            'urls' => $urls,
        ];
    }

    public function robots(Site $site, ?string $baseUrl = null): array
    {
        return [
            'site' => $site,
            'sitemapUrl' => $this->baseUrl($site, $baseUrl) . '/sitemap.xml',
        ];
    }

    public function feed(Site $site, ?string $baseUrl = null): array
    {
        return [
            'site' => $site,
            'baseUrl' => $this->baseUrl($site, $baseUrl),
            'articles' => $this->recentArticles($site, 25),
        ];
    }

    public function css(Site $site): string
    {
        $theme = $this->theme($site);
        $accent = $theme['accent_color'] ?? '#0f766e';
        $surface = $theme['surface_color'] ?? '#f8fafc';
        $text = $theme['text_color'] ?? '#0f172a';
        $headingFont = $theme['heading_font'] ?? 'Georgia, serif';
        $bodyFont = $theme['body_font'] ?? 'system-ui, sans-serif';
        $template = $site->hosting?->template_key ?? 'editorial';

        $heroGrid = match ($template) {
            'magazine' => 'grid-template-columns: minmax(0,1.1fr) minmax(0,0.9fr); align-items: end;',
            'minimal' => 'grid-template-columns: 1fr;',
            default => 'grid-template-columns: minmax(0,1fr);',
        };

        $cardRadius = match ($template) {
            'magazine' => '26px',
            'minimal' => '10px',
            default => '18px',
        };

        return <<<CSS
:root {
    --hosted-accent: {$accent};
    --hosted-surface: {$surface};
    --hosted-text: {$text};
    --hosted-heading-font: {$headingFont};
    --hosted-body-font: {$bodyFont};
    --hosted-card-radius: {$cardRadius};
}
* { box-sizing: border-box; }
body {
    margin: 0;
    background: linear-gradient(180deg, #ffffff 0%, var(--hosted-surface) 100%);
    color: var(--hosted-text);
    font-family: var(--hosted-body-font);
    line-height: 1.65;
}
a { color: inherit; text-decoration: none; }
img { max-width: 100%; display: block; }
.container { width: min(1120px, calc(100% - 32px)); margin: 0 auto; }
.site-header { padding: 28px 0; border-bottom: 1px solid rgba(15, 23, 42, 0.08); }
.site-header nav { display: flex; gap: 20px; flex-wrap: wrap; }
.brand { display: flex; align-items: center; gap: 14px; justify-content: space-between; }
.brand-mark { width: 14px; height: 14px; border-radius: 999px; background: var(--hosted-accent); box-shadow: 0 0 0 8px color-mix(in srgb, var(--hosted-accent) 18%, transparent); }
.brand h1, .hero h2, .content h1, .content h2, .content h3, .page-title { font-family: var(--hosted-heading-font); }
.hero { padding: 48px 0 20px; }
.hero-grid { display: grid; gap: 24px; {$heroGrid} }
.hero-panel, .card, .content, .sidebar-card {
    background: rgba(255,255,255,0.78);
    border: 1px solid rgba(15, 23, 42, 0.08);
    border-radius: var(--hosted-card-radius);
    box-shadow: 0 14px 40px rgba(15, 23, 42, 0.06);
}
.hero-panel { padding: 28px; }
.pill {
    display: inline-flex;
    padding: 8px 14px;
    border-radius: 999px;
    background: color-mix(in srgb, var(--hosted-accent) 14%, white);
    color: var(--hosted-accent);
    font-size: 13px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.08em;
}
.hero h2 { font-size: clamp(2.2rem, 6vw, 4.4rem); line-height: 0.98; margin: 18px 0 16px; }
.hero p { font-size: 1.05rem; max-width: 68ch; }
.section-title { margin: 32px 0 18px; font-size: 1.3rem; font-weight: 700; }
.cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 20px; }
.card { padding: 22px; }
.card .meta, .article-meta, .muted { color: rgba(15, 23, 42, 0.62); font-size: 0.92rem; }
.page-shell { padding: 34px 0 80px; }
.page-title { font-size: clamp(2rem, 4vw, 3.5rem); margin: 0 0 12px; }
.content { padding: 32px; }
.content p, .content li, .content blockquote { font-size: 1.02rem; }
.content blockquote { border-left: 3px solid var(--hosted-accent); margin: 24px 0; padding-left: 18px; }
.content a { color: var(--hosted-accent); text-decoration: underline; }
.article-layout { display: grid; grid-template-columns: minmax(0, 1fr) 280px; gap: 24px; }
.sidebar-card { padding: 20px; }
.site-footer { padding: 34px 0 60px; color: rgba(15, 23, 42, 0.64); }
@media (max-width: 900px) {
    .article-layout { grid-template-columns: 1fr; }
}
CSS;
    }

    public function articleUrl(Site $site, Article $article, ?string $baseUrl = null): string
    {
        if ($baseUrl !== null && $baseUrl === '') {
            return "blog/{$article->slug}.html";
        }

        return $this->baseUrl($site, $baseUrl) . "/blog/{$article->slug}";
    }

    private function recentArticles(Site $site, int $limit): Collection
    {
        return $site->articles()
            ->where('status', Article::STATUS_PUBLISHED)
            ->latest('published_at')
            ->limit($limit)
            ->get();
    }

    private function basePayload(Site $site, ?string $baseUrl, string $path): array
    {
        $baseUrl = $this->baseUrl($site, $baseUrl);

        return [
            'site' => $site,
            'theme' => $this->theme($site),
            'baseUrl' => $baseUrl,
            'currentPath' => $path,
            'currentUrl' => $baseUrl === '' ? null : $baseUrl . $path,
            'css' => $this->css($site),
            'navigation' => [
                ['label' => 'Home', 'path' => '/', 'href' => $this->href('/', $path, $baseUrl)],
                ['label' => 'Blog', 'path' => '/blog', 'href' => $this->href('/blog', $path, $baseUrl)],
                ['label' => 'About', 'path' => '/about', 'href' => $this->href('/about', $path, $baseUrl)],
                ['label' => 'Legal', 'path' => '/legal', 'href' => $this->href('/legal', $path, $baseUrl)],
            ],
        ];
    }

    private function theme(Site $site): array
    {
        return array_replace([
            'brand_name' => $site->name,
            'footer_text' => "Published by {$site->name}",
            'social_links' => [],
        ], $site->hosting?->theme_settings ?? []);
    }

    private function baseUrl(Site $site, ?string $baseUrl = null): string
    {
        if ($baseUrl !== null) {
            return rtrim($baseUrl, '/');
        }

        $domain = $site->hosting?->canonical_domain
            ?? $site->hosting?->custom_domain
            ?? $site->hosting?->staging_domain
            ?? $site->domain;

        return 'https://' . trim($domain, '/');
    }

    private function href(string $targetPath, string $currentPath, string $baseUrl): string
    {
        if ($baseUrl !== '') {
            return $baseUrl . ($targetPath === '/' ? '/' : $targetPath);
        }

        return $this->relativeExportHref($currentPath, $targetPath);
    }

    private function relativeExportHref(string $currentPath, string $targetPath): string
    {
        $currentDirectory = dirname($this->exportFileForPath($currentPath));
        $currentSegments = $currentDirectory === '.' ? [] : array_values(array_filter(explode('/', $currentDirectory)));
        $targetSegments = array_values(array_filter(explode('/', $this->exportFileForPath($targetPath))));

        while (!empty($currentSegments) && !empty($targetSegments) && $currentSegments[0] === $targetSegments[0]) {
            array_shift($currentSegments);
            array_shift($targetSegments);
        }

        $prefix = str_repeat('../', count($currentSegments));

        return $prefix . implode('/', $targetSegments);
    }

    private function exportFileForPath(string $path): string
    {
        return match (true) {
            $path === '/' => 'index.html',
            $path === '/blog' => 'blog/index.html',
            $path === '/about' => 'about/index.html',
            $path === '/legal' => 'legal/index.html',
            str_starts_with($path, '/blog/') => 'blog/' . basename($path) . '.html',
            default => ltrim($path, '/'),
        };
    }
}
