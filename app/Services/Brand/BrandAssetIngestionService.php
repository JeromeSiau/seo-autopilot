<?php

namespace App\Services\Brand;

use App\Models\Article;
use App\Models\BrandAsset;
use App\Models\HostedPage;
use App\Models\Site;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Str;

class BrandAssetIngestionService
{
    public function importHostedPages(Site $site): array
    {
        $site->loadMissing(['hosting', 'hostedPages', 'brandAssets']);

        if (!$site->isHosted()) {
            return $this->emptyCounts();
        }

        $counts = $this->emptyCounts();
        $importedAssets = $this->importedAssetsBySource($site->brandAssets, 'hosted_page', 'hosted_page_id');

        foreach ($site->hostedPages->where('is_published', true) as $page) {
            $content = $this->extractContent($page->body_html);

            if ($content === '') {
                $counts['skipped']++;

                continue;
            }

            $url = $this->resolveHostedPageUrl($site, $page);
            $existing = $this->resolveExistingAsset($site, $importedAssets, $page->id, $url, 'hosted_page');

            if ($existing === false) {
                $counts['skipped']++;

                continue;
            }

            $counts[$this->syncImportedAsset(
                $site,
                $existing,
                $importedAssets,
                sourceId: $page->id,
                payload: [
                    'type' => $this->mapHostedPageToAssetType($page),
                    'title' => $page->title,
                    'source_url' => $url,
                    'content' => $content,
                    'priority' => $this->priorityForHostedPage($page),
                    'metadata' => [
                        'imported_from' => 'hosted_page',
                        'hosted_page_id' => $page->id,
                        'hosted_page_kind' => $page->kind,
                    ],
                ],
            )]++;
        }

        return $counts;
    }

    public function importPublishedArticles(Site $site): array
    {
        $site->loadMissing(['hosting', 'articles', 'brandAssets']);

        $counts = $this->emptyCounts();
        $importedAssets = $this->importedAssetsBySource($site->brandAssets, 'published_article', 'article_id');

        /** @var EloquentCollection<int, Article> $articles */
        $articles = $site->articles
            ->where('status', Article::STATUS_PUBLISHED)
            ->filter(fn (Article $article) => filled($article->content));

        foreach ($articles as $article) {
            $content = $this->extractContent($article->content);

            if ($content === '') {
                $counts['skipped']++;

                continue;
            }

            $url = $article->published_url ?: $this->resolveHostedArticleUrl($site, $article);
            $existing = $this->resolveExistingAsset($site, $importedAssets, $article->id, $url, 'published_article');

            if ($existing === false) {
                $counts['skipped']++;

                continue;
            }

            $counts[$this->syncImportedAsset(
                $site,
                $existing,
                $importedAssets,
                sourceId: $article->id,
                payload: [
                    'type' => BrandAsset::TYPE_STYLE_SAMPLE,
                    'title' => $article->title,
                    'source_url' => $url,
                    'content' => $content,
                    'priority' => 65,
                    'metadata' => [
                        'imported_from' => 'published_article',
                        'article_id' => $article->id,
                        'article_slug' => $article->slug,
                        'published_via' => $article->published_via,
                    ],
                ],
            )]++;
        }

        return $counts;
    }

    protected function emptyCounts(): array
    {
        return [
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
        ];
    }

    protected function importedAssetsBySource(EloquentCollection $brandAssets, string $sourceType, string $sourceIdKey): EloquentCollection
    {
        return $brandAssets
            ->filter(fn (BrandAsset $asset) => data_get($asset->metadata, 'imported_from') === $sourceType)
            ->keyBy(fn (BrandAsset $asset) => (string) data_get($asset->metadata, $sourceIdKey));
    }

    protected function resolveExistingAsset(
        Site $site,
        EloquentCollection $importedAssets,
        int $sourceId,
        ?string $url,
        string $expectedImportType,
    ): BrandAsset|bool|null {
        $existing = $importedAssets->get((string) $sourceId);

        if ($existing || !$url) {
            return $existing;
        }

        $existing = $site->brandAssets->firstWhere('source_url', $url);

        if ($existing && data_get($existing->metadata, 'imported_from') !== $expectedImportType) {
            return false;
        }

        return $existing;
    }

    protected function syncImportedAsset(
        Site $site,
        ?BrandAsset $existing,
        EloquentCollection $importedAssets,
        int $sourceId,
        array $payload,
    ): string {
        if (!$existing) {
            $created = $site->brandAssets()->create([
                ...$payload,
                'is_active' => true,
            ]);

            $site->brandAssets->push($created);
            $importedAssets->put((string) $sourceId, $created);

            return 'created';
        }

        $existing->fill([
            ...$payload,
            'is_active' => $existing->is_active,
        ]);

        if ($existing->isDirty()) {
            $existing->save();

            return 'updated';
        }

        return 'skipped';
    }

    protected function extractContent(?string $bodyHtml): string
    {
        if (!$bodyHtml) {
            return '';
        }

        return (string) Str::of(html_entity_decode(strip_tags($bodyHtml)))
            ->replaceMatches('/\s+/', ' ')
            ->trim()
            ->limit(5000, '');
    }

    protected function resolveHostedPageUrl(Site $site, HostedPage $page): string
    {
        $baseUrl = rtrim($site->public_url, '/');
        $path = $page->path();

        return $path === '/' ? $baseUrl : $baseUrl . $path;
    }

    protected function resolveHostedArticleUrl(Site $site, Article $article): ?string
    {
        if (!$site->isHosted() || !$site->public_url) {
            return null;
        }

        return rtrim($site->public_url, '/') . '/blog/' . $article->slug;
    }

    protected function mapHostedPageToAssetType(HostedPage $page): string
    {
        return match ($page->kind) {
            HostedPage::KIND_HOME => BrandAsset::TYPE_STYLE_SAMPLE,
            HostedPage::KIND_ABOUT => BrandAsset::TYPE_PILLAR_PAGE,
            HostedPage::KIND_LEGAL => BrandAsset::TYPE_POLICY,
            default => BrandAsset::TYPE_PILLAR_PAGE,
        };
    }

    protected function priorityForHostedPage(HostedPage $page): int
    {
        return match ($page->kind) {
            HostedPage::KIND_HOME => 90,
            HostedPage::KIND_ABOUT => 80,
            HostedPage::KIND_LEGAL => 70,
            default => 60,
        };
    }
}
