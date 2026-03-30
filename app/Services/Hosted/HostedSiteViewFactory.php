<?php

namespace App\Services\Hosted;

use App\Models\Article;
use App\Models\HostedAuthor;
use App\Models\HostedAsset;
use App\Models\HostedCategory;
use App\Models\HostedNavigationItem;
use App\Models\HostedPage;
use App\Models\HostedTag;
use App\Models\Site;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class HostedSiteViewFactory
{
    public function __construct(
        private readonly HostedContentSanitizer $sanitizer,
    ) {}

    public function home(Site $site, ?string $baseUrl = null): array
    {
        $page = $site->hostedPages->firstWhere('kind', HostedPage::KIND_HOME);
        $articles = $this->recentArticles($site, 6);
        $pageTitle = $page?->title ?: 'Home';
        $socialImageUrl = $this->themeImageUrl($site, $baseUrl, '/');

        return [
            ...$this->basePayload($site, $baseUrl, '/'),
            'metaTitle' => $page?->meta_title ?: ($site->hosting?->theme_settings['brand_name'] ?? $site->name),
            'metaDescription' => $page?->meta_description ?: $site->business_description,
            'currentUrl' => $this->pageCanonicalUrl($site, $page, $baseUrl, '/'),
            'metaRobots' => $this->pageMetaRobots($site, $page),
            'pageTitle' => $pageTitle,
            'heroTitle' => $site->hosting?->theme_settings['hero_title'] ?? $site->name,
            'heroDescription' => $site->hosting?->theme_settings['hero_description'] ?? $site->business_description,
            'pageBodyHtml' => $this->sanitizer->sanitize($page?->body_html),
            'sections' => $this->pageSections($page, $site, '/', $baseUrl),
            'articles' => $this->articleCards($site, $articles, '/', $baseUrl),
            'socialTitle' => $page?->social_title ?: ($page?->meta_title ?: ($site->hosting?->theme_settings['brand_name'] ?? $site->name)),
            'socialDescription' => $page?->social_description ?: ($page?->meta_description ?: $site->business_description),
            'socialImageUrl' => $this->pageSocialImageUrl($site, $page, $baseUrl, '/') ?: $socialImageUrl,
            'structuredData' => $this->pageSchemaEnabled($page)
                ? $this->mergeStructuredData(
                    $site,
                    $baseUrl,
                    '/',
                    $pageTitle,
                    $this->pageBreadcrumbsEnabled($page),
                    [
                        $this->websiteSchema($site, $baseUrl),
                        $this->webPageSchema($site, $baseUrl, '/', $pageTitle, $page?->meta_description ?: $site->business_description),
                    ],
                )
                : [],
        ];
    }

    public function blogIndex(Site $site, ?string $baseUrl = null): array
    {
        $pageTitle = 'Blog';
        $socialImageUrl = $this->themeImageUrl($site, $baseUrl, '/blog');

        return [
            ...$this->basePayload($site, $baseUrl, '/blog'),
            'metaTitle' => "{$site->name} Blog",
            'metaDescription' => "Latest articles from {$site->name}.",
            'pageTitle' => $pageTitle,
            'articles' => $this->articleCards($site, $this->recentArticles($site, 100), '/blog', $baseUrl),
            'socialImageUrl' => $socialImageUrl,
            'structuredData' => $this->mergeStructuredData(
                $site,
                $baseUrl,
                '/blog',
                $pageTitle,
                true,
                [
                    $this->websiteSchema($site, $baseUrl),
                    $this->webPageSchema($site, $baseUrl, '/blog', $pageTitle, "Latest articles from {$site->name}."),
                ],
            ),
        ];
    }

    public function article(Site $site, Article $article, ?string $baseUrl = null): array
    {
        $pageTitle = $article->title;
        $description = $article->meta_description ?: null;
        $socialImageUrl = $this->articleImageUrl($article, $site, $baseUrl, "/blog/{$article->slug}")
            ?: $this->themeImageUrl($site, $baseUrl, "/blog/{$article->slug}");

        return [
            ...$this->basePayload($site, $baseUrl, "/blog/{$article->slug}"),
            'metaTitle' => $article->meta_title ?: $article->title,
            'metaDescription' => $description,
            'pageTitle' => $pageTitle,
            'article' => $article,
            'articleContentHtml' => $this->sanitizer->sanitize($article->content),
            'authorHref' => $article->hostedAuthor?->is_active ? $this->linkForPath($site, $baseUrl, "/blog/{$article->slug}", $article->hostedAuthor->archivePath()) : null,
            'categoryHref' => $article->hostedCategory?->is_active ? $this->linkForPath($site, $baseUrl, "/blog/{$article->slug}", $article->hostedCategory->archivePath()) : null,
            'tagHrefs' => $article->hostedTags
                ->where('is_active', true)
                ->mapWithKeys(fn (HostedTag $tag) => [$tag->id => $this->linkForPath($site, $baseUrl, "/blog/{$article->slug}", $tag->archivePath())])
                ->all(),
            'ogType' => 'article',
            'socialImageUrl' => $socialImageUrl,
            'structuredData' => $this->mergeStructuredData(
                $site,
                $baseUrl,
                "/blog/{$article->slug}",
                $pageTitle,
                true,
                [
                    $this->websiteSchema($site, $baseUrl),
                    $this->webPageSchema($site, $baseUrl, "/blog/{$article->slug}", $pageTitle, $description),
                    $this->articleSchema($site, $article, $baseUrl),
                ],
            ),
        ];
    }

    public function authorArchive(Site $site, HostedAuthor $author, Collection $articles, ?string $baseUrl = null): array
    {
        $path = $author->archivePath();
        $pageTitle = $author->name;
        $description = $author->bio ?: "Articles written by {$author->name}.";

        return [
            ...$this->basePayload($site, $baseUrl, $path),
            'metaTitle' => "{$author->name} | {$site->name}",
            'metaDescription' => Str::limit($description, 160),
            'pageTitle' => $pageTitle,
            'archiveLabel' => 'Author',
            'archiveDescription' => $description,
            'archiveEntity' => $author,
            'articles' => $this->articleCards($site, $articles, $path, $baseUrl),
            'socialImageUrl' => $this->resolveAssetUrl($author->avatar_url, $site, $baseUrl, $path) ?: $this->themeImageUrl($site, $baseUrl, $path),
            'structuredData' => $this->mergeStructuredData(
                $site,
                $baseUrl,
                $path,
                $pageTitle,
                true,
                [
                    $this->websiteSchema($site, $baseUrl),
                    $this->webPageSchema($site, $baseUrl, $path, $pageTitle, $description),
                ],
            ),
        ];
    }

    public function categoryArchive(Site $site, HostedCategory $category, Collection $articles, ?string $baseUrl = null): array
    {
        $path = $category->archivePath();
        $pageTitle = $category->name;
        $description = $category->description ?: "Articles filed under {$category->name}.";

        return [
            ...$this->basePayload($site, $baseUrl, $path),
            'metaTitle' => "{$category->name} articles | {$site->name}",
            'metaDescription' => Str::limit($description, 160),
            'pageTitle' => $pageTitle,
            'archiveLabel' => 'Category',
            'archiveDescription' => $description,
            'archiveEntity' => $category,
            'articles' => $this->articleCards($site, $articles, $path, $baseUrl),
            'socialImageUrl' => $this->themeImageUrl($site, $baseUrl, $path),
            'structuredData' => $this->mergeStructuredData(
                $site,
                $baseUrl,
                $path,
                $pageTitle,
                true,
                [
                    $this->websiteSchema($site, $baseUrl),
                    $this->webPageSchema($site, $baseUrl, $path, $pageTitle, $description),
                ],
            ),
        ];
    }

    public function tagArchive(Site $site, HostedTag $tag, Collection $articles, ?string $baseUrl = null): array
    {
        $path = $tag->archivePath();
        $pageTitle = $tag->name;
        $description = "Articles tagged {$tag->name}.";

        return [
            ...$this->basePayload($site, $baseUrl, $path),
            'metaTitle' => "{$tag->name} tag | {$site->name}",
            'metaDescription' => Str::limit($description, 160),
            'pageTitle' => $pageTitle,
            'archiveLabel' => 'Tag',
            'archiveDescription' => $description,
            'archiveEntity' => $tag,
            'articles' => $this->articleCards($site, $articles, $path, $baseUrl),
            'socialImageUrl' => $this->themeImageUrl($site, $baseUrl, $path),
            'structuredData' => $this->mergeStructuredData(
                $site,
                $baseUrl,
                $path,
                $pageTitle,
                true,
                [
                    $this->websiteSchema($site, $baseUrl),
                    $this->webPageSchema($site, $baseUrl, $path, $pageTitle, $description),
                ],
            ),
        ];
    }

    public function staticPage(Site $site, HostedPage $page, string $path, ?string $baseUrl = null): array
    {
        $pageTitle = $page->title;
        $socialImageUrl = $this->themeImageUrl($site, $baseUrl, $path);

        return [
            ...$this->basePayload($site, $baseUrl, $path),
            'metaTitle' => $page->meta_title ?: $page->title,
            'metaDescription' => $page->meta_description,
            'currentUrl' => $this->pageCanonicalUrl($site, $page, $baseUrl, $path),
            'metaRobots' => $this->pageMetaRobots($site, $page),
            'pageTitle' => $pageTitle,
            'page' => $page,
            'pageContentHtml' => $this->sanitizer->sanitize($page->body_html),
            'sections' => $this->pageSections($page, $site, $path, $baseUrl),
            'socialTitle' => $page->social_title ?: ($page->meta_title ?: $page->title),
            'socialDescription' => $page->social_description ?: $page->meta_description,
            'socialImageUrl' => $this->pageSocialImageUrl($site, $page, $baseUrl, $path) ?: $socialImageUrl,
            'structuredData' => $this->pageSchemaEnabled($page)
                ? $this->mergeStructuredData(
                    $site,
                    $baseUrl,
                    $path,
                    $pageTitle,
                    $this->pageBreadcrumbsEnabled($page),
                    [
                        $this->websiteSchema($site, $baseUrl),
                        $this->webPageSchema($site, $baseUrl, $path, $pageTitle, $page->meta_description),
                    ],
                )
                : [],
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
            if ($page->is_published && $page->kind !== HostedPage::KIND_HOME && ($page->show_in_sitemap ?? true)) {
                $urls->push([
                    'loc' => $baseUrl . $page->path(),
                    'lastmod' => $page->updated_at ?? now(),
                ]);
            }
        });

        $site->articles()
            ->where('status', Article::STATUS_PUBLISHED)
            ->with(['hostedAuthor', 'hostedCategory', 'hostedTags'])
            ->latest('published_at')
            ->get()
            ->each(function (Article $article) use ($urls, $baseUrl): void {
                $urls->push([
                    'loc' => $baseUrl . "/blog/{$article->slug}",
                    'lastmod' => $article->updated_at ?? now(),
                ]);

                if ($article->hostedAuthor?->is_active) {
                    $urls->push([
                        'loc' => $baseUrl . $article->hostedAuthor->archivePath(),
                        'lastmod' => $article->hostedAuthor->updated_at ?? $article->updated_at ?? now(),
                    ]);
                }

                if ($article->hostedCategory?->is_active) {
                    $urls->push([
                        'loc' => $baseUrl . $article->hostedCategory->archivePath(),
                        'lastmod' => $article->hostedCategory->updated_at ?? $article->updated_at ?? now(),
                    ]);
                }

                $article->hostedTags
                    ->where('is_active', true)
                    ->each(fn (HostedTag $tag) => $urls->push([
                        'loc' => $baseUrl . $tag->archivePath(),
                        'lastmod' => $tag->updated_at ?? $article->updated_at ?? now(),
                    ]));
            });

        return [
            'site' => $site,
            'urls' => $urls->unique('loc')->values(),
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
        $resolvedBaseUrl = $this->baseUrl($site, $baseUrl);
        $pageItems = $site->hostedPages
            ->filter(fn (HostedPage $page) => $page->is_published && ($page->show_in_feed ?? false))
            ->map(function (HostedPage $page) use ($resolvedBaseUrl) {
                $link = $resolvedBaseUrl === ''
                    ? ltrim($page->exportPath() ?? 'index.html', '/')
                    : $resolvedBaseUrl . $page->path();

                return [
                    'title' => $page->meta_title ?: $page->title,
                    'link' => $link,
                    'guid' => $link,
                    'published_at' => $page->updated_at ?? $page->created_at,
                    'description' => $page->meta_description ?: Str::limit(strip_tags((string) $page->body_html), 180),
                ];
            });
        $articleItems = $this->recentArticles($site, 25)->map(function (Article $article) use ($resolvedBaseUrl) {
            $link = $resolvedBaseUrl === ''
                ? 'blog/' . $article->slug . '.html'
                : $resolvedBaseUrl . '/blog/' . $article->slug;

            return [
                'title' => $article->title,
                'link' => $link,
                'guid' => $link,
                'published_at' => $article->published_at ?? $article->created_at,
                'description' => $article->meta_description ?: Str::limit(strip_tags((string) $article->content), 180),
            ];
        });

        return [
            'site' => $site,
            'baseUrl' => $resolvedBaseUrl,
            'items' => $articleItems
                ->concat($pageItems)
                ->sortByDesc(fn (array $item) => optional($item['published_at'])->getTimestamp() ?? 0)
                ->values(),
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
.brand-logo { width: 42px; height: 42px; border-radius: 14px; object-fit: cover; border: 1px solid rgba(15, 23, 42, 0.08); }
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
.card-image, .article-cover {
    width: 100%;
    border-radius: calc(var(--hosted-card-radius) - 6px);
    object-fit: cover;
    aspect-ratio: 16 / 9;
    margin-bottom: 16px;
    background: color-mix(in srgb, var(--hosted-surface) 82%, white);
}
.card .meta, .article-meta, .muted { color: rgba(15, 23, 42, 0.62); font-size: 0.92rem; }
.page-shell { padding: 34px 0 80px; }
.page-title { font-size: clamp(2rem, 4vw, 3.5rem); margin: 0 0 12px; }
.content { padding: 32px; }
.content p, .content li, .content blockquote { font-size: 1.02rem; }
.content blockquote { border-left: 3px solid var(--hosted-accent); margin: 24px 0; padding-left: 18px; }
.content a { color: var(--hosted-accent); text-decoration: underline; }
.article-layout { display: grid; grid-template-columns: minmax(0, 1fr) 280px; gap: 24px; }
.sidebar-card { padding: 20px; }
.eyebrow { display: inline-flex; align-items: center; gap: 8px; font-size: 0.78rem; font-weight: 700; letter-spacing: 0.08em; text-transform: uppercase; color: rgba(15, 23, 42, 0.54); }
.taxonomy-pills, .article-tags { display: flex; flex-wrap: wrap; gap: 10px; }
.taxonomy-pill {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 8px 12px;
    border-radius: 999px;
    background: color-mix(in srgb, var(--hosted-accent) 12%, white);
    color: var(--hosted-accent);
    font-size: 0.82rem;
    font-weight: 600;
}
.author-summary { display: grid; gap: 14px; grid-template-columns: auto 1fr; align-items: center; margin-bottom: 18px; }
.author-avatar {
    width: 58px;
    height: 58px;
    border-radius: 18px;
    object-fit: cover;
    border: 1px solid rgba(15, 23, 42, 0.08);
    background: color-mix(in srgb, var(--hosted-surface) 82%, white);
}
.site-footer { padding: 34px 0 60px; color: rgba(15, 23, 42, 0.64); }
.hosted-section-stack { display: grid; gap: 24px; margin-top: 28px; }
.section-shell {
    background: rgba(255,255,255,0.78);
    border: 1px solid rgba(15, 23, 42, 0.08);
    border-radius: var(--hosted-card-radius);
    box-shadow: 0 14px 40px rgba(15, 23, 42, 0.06);
    padding: 28px;
}
.section-shell h2, .section-shell h3 { font-family: var(--hosted-heading-font); margin-top: 0; }
.callout-section {
    background: color-mix(in srgb, var(--hosted-accent) 8%, white);
    border-color: color-mix(in srgb, var(--hosted-accent) 18%, rgba(15, 23, 42, 0.08));
}
.hero-section {
    background:
        radial-gradient(circle at top right, color-mix(in srgb, var(--hosted-accent) 14%, white) 0%, rgba(255,255,255,0.88) 42%, rgba(255,255,255,0.8) 100%);
}
.feature-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; margin-top: 18px; }
.feature-card, .faq-item {
    background: rgba(255,255,255,0.76);
    border: 1px solid rgba(15, 23, 42, 0.08);
    border-radius: calc(var(--hosted-card-radius) - 6px);
    padding: 18px;
}
.faq-list { display: grid; gap: 14px; margin-top: 18px; }
.section-cta {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    margin-top: 18px;
    padding: 12px 18px;
    border-radius: 999px;
    background: var(--hosted-accent);
    color: white;
    font-weight: 700;
}
.section-cta-secondary {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    margin-top: 18px;
    padding: 12px 18px;
    border-radius: 999px;
    border: 1px solid rgba(15, 23, 42, 0.12);
    color: var(--hosted-text);
    font-weight: 700;
    background: rgba(255,255,255,0.72);
}
.testimonial-card { background: color-mix(in srgb, var(--hosted-accent) 5%, white); }
.stat-card h3 { color: var(--hosted-accent); }
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
            ->with(['hostedAuthor', 'hostedCategory', 'hostedTags'])
            ->where('status', Article::STATUS_PUBLISHED)
            ->latest('published_at')
            ->limit($limit)
            ->get();
    }

    private function articleCards(Site $site, Collection $articles, string $currentPath, ?string $baseUrl = null): Collection
    {
        return $articles->map(function (Article $article) use ($site, $currentPath, $baseUrl): array {
            return [
                'id' => $article->id,
                'title' => $article->title,
                'slug' => $article->slug,
                'meta_description' => $article->meta_description,
                'content' => $article->content,
                'featured_image_url' => $this->articleImageUrl($article, $site, $baseUrl, $currentPath),
                'published_at' => $article->published_at,
                'created_at' => $article->created_at,
                'href' => $this->linkForPath($site, $baseUrl, $currentPath, "/blog/{$article->slug}"),
                'author' => $article->hostedAuthor?->is_active ? [
                    'name' => $article->hostedAuthor->name,
                    'href' => $this->linkForPath($site, $baseUrl, $currentPath, $article->hostedAuthor->archivePath()),
                ] : null,
                'category' => $article->hostedCategory?->is_active ? [
                    'name' => $article->hostedCategory->name,
                    'href' => $this->linkForPath($site, $baseUrl, $currentPath, $article->hostedCategory->archivePath()),
                ] : null,
                'tags' => $article->hostedTags
                    ->where('is_active', true)
                    ->map(fn (HostedTag $tag) => [
                        'id' => $tag->id,
                        'name' => $tag->name,
                        'href' => $this->linkForPath($site, $baseUrl, $currentPath, $tag->archivePath()),
                    ])
                    ->values()
                    ->all(),
            ];
        });
    }

    private function basePayload(Site $site, ?string $baseUrl, string $path): array
    {
        $baseUrl = $this->baseUrl($site, $baseUrl);
        $currentHost = app()->bound('request') && request()->route()
            ? request()->getHost()
            : null;
        $navigation = $this->navigationItems($site, HostedNavigationItem::PLACEMENT_HEADER, $path, $baseUrl);
        $footerNavigation = $this->navigationItems($site, HostedNavigationItem::PLACEMENT_FOOTER, $path, $baseUrl);

        return [
            'site' => $site,
            'theme' => $this->theme($site),
            'baseUrl' => $baseUrl,
            'currentPath' => $path,
            'currentUrl' => $baseUrl === '' ? null : $baseUrl . $path,
            'metaRobots' => $currentHost && $currentHost === $site->hosting?->staging_domain ? 'noindex, nofollow' : null,
            'ogType' => 'website',
            'socialImageUrl' => $this->themeImageUrl($site, $baseUrl, $path),
            'css' => $this->css($site),
            'brandLogoUrl' => $this->themeLogoUrl($site, $baseUrl, $path),
            'homeHref' => $this->href('/', $path, $baseUrl),
            'navigation' => $navigation->values()->all(),
            'footerNavigation' => $footerNavigation->values()->all(),
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

    private function linkForPath(Site $site, ?string $baseUrl, string $currentPath, string $targetPath): string
    {
        return $this->href($targetPath, $currentPath, $this->baseUrl($site, $baseUrl));
    }

    private function navigationItems(Site $site, string $placement, string $currentPath, string $baseUrl): Collection
    {
        $manualItems = $site->hostedNavigationItems
            ->where('placement', $placement)
            ->where('is_active', true)
            ->sortBy([
                ['sort_order', 'asc'],
                ['id', 'asc'],
            ])
            ->values();

        if ($manualItems->isNotEmpty()) {
            return $manualItems->map(fn (HostedNavigationItem $item) => $this->presentNavigationItem($item, $currentPath, $baseUrl));
        }

        if ($placement === HostedNavigationItem::PLACEMENT_FOOTER) {
            return collect();
        }

        return $this->fallbackNavigationItems($site, $currentPath, $baseUrl);
    }

    private function fallbackNavigationItems(Site $site, string $currentPath, string $baseUrl): Collection
    {
        $navigation = collect([
            [
                'label' => 'Home',
                'path' => '/',
                'href' => $this->href('/', $currentPath, $baseUrl),
                'isExternal' => false,
                'openInNewTab' => false,
            ],
            [
                'label' => 'Blog',
                'path' => '/blog',
                'href' => $this->href('/blog', $currentPath, $baseUrl),
                'isExternal' => false,
                'openInNewTab' => false,
            ],
        ]);

        $site->hostedPages
            ->filter(fn (HostedPage $page) => $page->is_published && $page->show_in_navigation && $page->kind !== HostedPage::KIND_HOME)
            ->sortBy([
                ['sort_order', 'asc'],
                ['id', 'asc'],
            ])
            ->each(function (HostedPage $page) use ($navigation, $currentPath, $baseUrl): void {
                $pagePath = $page->path();

                $navigation->push([
                    'label' => $page->navigationLabel(),
                    'path' => $pagePath,
                    'href' => $this->href($pagePath, $currentPath, $baseUrl),
                    'isExternal' => false,
                    'openInNewTab' => false,
                ]);
            });

        return $navigation;
    }

    private function pageSections(?HostedPage $page, Site $site, string $currentPath, ?string $baseUrl): array
    {
        return collect($page?->sections ?? [])
            ->map(function ($section) use ($site, $currentPath, $baseUrl) {
                if (!is_array($section) || !isset($section['type'])) {
                    return null;
                }

                if (($section['type'] ?? null) === HostedPage::SECTION_RICH_TEXT) {
                    $section['body_html'] = $this->sanitizer->sanitize((string) ($section['body_html'] ?? ''));
                }

                if (($section['type'] ?? null) === HostedPage::SECTION_CALLOUT) {
                    $originalHref = (string) ($section['cta_href'] ?? '');
                    $section['cta_is_external'] = filled($originalHref) && !str_starts_with($originalHref, '/');

                    if (filled($originalHref) && str_starts_with($originalHref, '/')) {
                        $section['cta_href'] = $this->linkForPath($site, $baseUrl, $currentPath, $originalHref);
                    }
                }

                if (in_array(($section['type'] ?? null), [HostedPage::SECTION_HERO, HostedPage::SECTION_CTA_BANNER], true)) {
                    foreach (['cta', 'secondary_cta'] as $prefix) {
                        $hrefKey = $prefix . '_href';
                        $externalKey = $prefix . '_is_external';
                        $originalHref = (string) ($section[$hrefKey] ?? '');

                        $section[$externalKey] = filled($originalHref) && !str_starts_with($originalHref, '/');

                        if (filled($originalHref) && str_starts_with($originalHref, '/')) {
                            $section[$hrefKey] = $this->linkForPath($site, $baseUrl, $currentPath, $originalHref);
                        }
                    }
                }

                if (($section['type'] ?? null) === HostedPage::SECTION_PRICING_GRID) {
                    $section['items'] = collect($section['items'] ?? [])
                        ->map(function ($item) use ($site, $baseUrl, $currentPath) {
                            if (!is_array($item)) {
                                return null;
                            }

                            $originalHref = (string) ($item['href'] ?? '');
                            $item['is_external'] = filled($originalHref) && !str_starts_with($originalHref, '/');

                            if (filled($originalHref) && str_starts_with($originalHref, '/')) {
                                $item['href'] = $this->linkForPath($site, $baseUrl, $currentPath, $originalHref);
                            }

                            return $item;
                        })
                        ->filter()
                        ->values()
                        ->all();
                }

                return $section;
            })
            ->filter()
            ->values()
            ->all();
    }

    private function presentNavigationItem(HostedNavigationItem $item, string $currentPath, string $baseUrl): array
    {
        $target = $item->target();
        $isExternal = $item->type === HostedNavigationItem::TYPE_URL;
        $path = $isExternal ? null : $target;

        return [
            'label' => $item->label,
            'path' => $path,
            'href' => $isExternal ? $target : $this->href($target, $currentPath, $baseUrl),
            'isExternal' => $isExternal,
            'openInNewTab' => $item->open_in_new_tab,
        ];
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
            default => trim($path, '/') . '/index.html',
        };
    }

    private function mergeStructuredData(Site $site, ?string $baseUrl, string $path, string $pageTitle, bool $breadcrumbsEnabled, array $items): array
    {
        $breadcrumb = $breadcrumbsEnabled
            ? $this->breadcrumbSchema($site, $baseUrl, $path, $pageTitle)
            : null;

        return array_values(array_filter([
            ...$items,
            $breadcrumb,
        ]));
    }

    private function websiteSchema(Site $site, ?string $baseUrl): ?array
    {
        $url = $this->baseUrl($site, $baseUrl);

        if ($url === '') {
            return null;
        }

        return [
            '@context' => 'https://schema.org',
            '@type' => 'WebSite',
            'name' => $site->hosting?->theme_settings['brand_name'] ?? $site->name,
            'url' => $url . '/',
        ];
    }

    private function webPageSchema(Site $site, ?string $baseUrl, string $path, string $pageTitle, ?string $description): ?array
    {
        $url = $this->baseUrl($site, $baseUrl);

        if ($url === '') {
            return null;
        }

        return array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'WebPage',
            'name' => $pageTitle,
            'url' => $url . $path,
            'description' => $description,
        ]);
    }

    private function breadcrumbSchema(Site $site, ?string $baseUrl, string $path, string $pageTitle): ?array
    {
        $url = $this->baseUrl($site, $baseUrl);

        if ($url === '') {
            return null;
        }

        $items = [
            [
                '@type' => 'ListItem',
                'position' => 1,
                'name' => 'Home',
                'item' => $url . '/',
            ],
        ];

        if ($path === '/') {
            return [
                '@context' => 'https://schema.org',
                '@type' => 'BreadcrumbList',
                'itemListElement' => $items,
            ];
        }

        $position = 2;

        if (str_starts_with($path, '/blog')) {
            $items[] = [
                '@type' => 'ListItem',
                'position' => $position,
                'name' => 'Blog',
                'item' => $url . '/blog',
            ];
            $position++;
        }

        if ($path !== '/blog') {
            $items[] = [
                '@type' => 'ListItem',
                'position' => $position,
                'name' => $pageTitle,
                'item' => $url . $path,
            ];
        }

        return [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => $items,
        ];
    }

    private function articleSchema(Site $site, Article $article, ?string $baseUrl): ?array
    {
        $url = $this->baseUrl($site, $baseUrl);

        if ($url === '') {
            return null;
        }

        $description = $article->meta_description ?: \Illuminate\Support\Str::limit(strip_tags((string) $article->content), 180);
        $brandName = $site->hosting?->theme_settings['brand_name'] ?? $site->name;
        $imageUrl = $this->articleImageUrl($article, $site, $baseUrl, "/blog/{$article->slug}");
        $author = $article->hostedAuthor?->is_active
            ? [
                '@type' => 'Person',
                'name' => $article->hostedAuthor->name,
            ]
            : [
                '@type' => 'Organization',
                'name' => $brandName,
            ];

        return array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'BlogPosting',
            'headline' => $article->title,
            'description' => $description,
            'url' => $url . "/blog/{$article->slug}",
            'image' => $imageUrl ? [$imageUrl] : null,
            'datePublished' => optional($article->published_at ?? $article->created_at)?->toAtomString(),
            'dateModified' => optional($article->updated_at)?->toAtomString(),
            'wordCount' => $article->word_count ?: null,
            'author' => $author,
            'publisher' => [
                '@type' => 'Organization',
                'name' => $brandName,
            ],
            'articleSection' => $article->hostedCategory?->name,
            'keywords' => $article->hostedTags->pluck('name')->implode(', ') ?: null,
        ]);
    }

    private function themeLogoUrl(Site $site, ?string $baseUrl, string $currentPath): ?string
    {
        $theme = $site->hosting?->theme_settings ?? [];

        return $this->resolveAssetUrl(
            $this->themeAsset($site, 'logo_asset_id')?->public_url ?? ($theme['logo_url'] ?? null),
            $site,
            $baseUrl,
            $currentPath,
        );
    }

    private function themeImageUrl(Site $site, ?string $baseUrl, string $currentPath): ?string
    {
        $theme = $site->hosting?->theme_settings ?? [];

        return $this->resolveAssetUrl(
            $this->themeAsset($site, 'social_image_asset_id')?->public_url
                ?? $theme['social_image_url']
                ?? ($this->themeAsset($site, 'logo_asset_id')?->public_url ?? ($theme['logo_url'] ?? null)),
            $site,
            $baseUrl,
            $currentPath,
        );
    }

    private function articleImageUrl(Article $article, Site $site, ?string $baseUrl, string $currentPath): ?string
    {
        return $this->resolveAssetUrl($article->featured_image_url, $site, $baseUrl, $currentPath);
    }

    private function resolveAssetUrl(?string $url, Site $site, ?string $baseUrl, ?string $currentPath = null): ?string
    {
        if (blank($url)) {
            return null;
        }

        $normalizedPath = $this->normalizeStoragePath($url);
        $resolvedBaseUrl = $this->baseUrl($site, $baseUrl);

        if ($normalizedPath !== null) {
            if ($resolvedBaseUrl === '' && $currentPath !== null) {
                return $this->assetExportHref($normalizedPath, $currentPath);
            }

            return $resolvedBaseUrl === '' ? $normalizedPath : $resolvedBaseUrl . $normalizedPath;
        }

        if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
            return $url;
        }

        if (str_starts_with($url, '/')) {
            return $resolvedBaseUrl === '' ? $url : $resolvedBaseUrl . $url;
        }

        return $url;
    }

    private function themeAsset(Site $site, string $themeKey): ?HostedAsset
    {
        $assetId = $site->hosting?->theme_settings[$themeKey] ?? null;

        if (!$assetId) {
            return null;
        }

        return $site->relationLoaded('hostedAssets')
            ? $site->hostedAssets->firstWhere('id', $assetId)
            : $site->hostedAssets()->find($assetId);
    }

    private function pageCanonicalUrl(Site $site, ?HostedPage $page, ?string $baseUrl, string $fallbackPath): ?string
    {
        if ($page?->canonical_url) {
            return $page->canonical_url;
        }

        $resolvedBaseUrl = $this->baseUrl($site, $baseUrl);

        return $resolvedBaseUrl === '' ? null : $resolvedBaseUrl . $fallbackPath;
    }

    private function pageMetaRobots(Site $site, ?HostedPage $page): ?string
    {
        $stagingRobots = app()->bound('request') && request()->route() && request()->getHost() === $site->hosting?->staging_domain
            ? 'noindex, nofollow'
            : null;

        if ($stagingRobots) {
            return $stagingRobots;
        }

        return $page?->robots_noindex ? 'noindex, follow' : null;
    }

    private function pageSchemaEnabled(?HostedPage $page): bool
    {
        return $page?->schema_enabled ?? true;
    }

    private function pageBreadcrumbsEnabled(?HostedPage $page): bool
    {
        return $page?->breadcrumbs_enabled ?? true;
    }

    private function pageSocialImageUrl(Site $site, ?HostedPage $page, ?string $baseUrl, string $currentPath): ?string
    {
        if (!$page) {
            return null;
        }

        return $this->resolveAssetUrl(
            $page->socialImageAsset?->public_url ?? $page->social_image_url,
            $site,
            $baseUrl,
            $currentPath,
        );
    }

    private function normalizeStoragePath(string $url): ?string
    {
        if (str_starts_with($url, '/storage/')) {
            return parse_url($url, PHP_URL_PATH) ?: $url;
        }

        if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
            $path = parse_url($url, PHP_URL_PATH);

            return is_string($path) && str_starts_with($path, '/storage/')
                ? $path
                : null;
        }

        return null;
    }

    private function assetExportHref(string $assetPath, string $currentPath): string
    {
        $currentDirectory = dirname($this->exportFileForPath($currentPath));
        $currentSegments = $currentDirectory === '.' ? [] : array_values(array_filter(explode('/', $currentDirectory)));
        $targetSegments = array_values(array_filter(explode('/', ltrim($assetPath, '/'))));

        while (!empty($currentSegments) && !empty($targetSegments) && $currentSegments[0] === $targetSegments[0]) {
            array_shift($currentSegments);
            array_shift($targetSegments);
        }

        return str_repeat('../', count($currentSegments)) . implode('/', $targetSegments);
    }
}
