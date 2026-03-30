<?php

namespace App\Services\AiVisibility\Connectors;

use App\Models\AiPrompt;
use App\Models\AiVisibilityCheck;
use App\Models\Article;
use App\Models\BrandAsset;
use App\Models\HostedPage;
use App\Models\Site;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class EstimatedAiVisibilityConnector implements AiVisibilityConnector
{
    private const ENGINE_WEIGHTS = [
        AiVisibilityCheck::ENGINE_AI_OVERVIEWS => 1.0,
        AiVisibilityCheck::ENGINE_CHATGPT => 0.96,
        AiVisibilityCheck::ENGINE_PERPLEXITY => 0.93,
        AiVisibilityCheck::ENGINE_GEMINI => 0.95,
    ];

    public function key(): string
    {
        return 'estimated';
    }

    public function evaluate(Site $site, AiPrompt $prompt, string $engine, array $analysis): array
    {
        $score = (int) round(min(100, $analysis['base_score'] * (self::ENGINE_WEIGHTS[$engine] ?? 1.0)));
        $appears = $score >= 55;

        return [
            'status' => 'completed',
            'visibility_score' => $score,
            'appears' => $appears,
            'rank_bucket' => $this->rankBucket($score),
            'raw_response' => [
                'matched_articles' => $analysis['matched_articles']->pluck('id')->all(),
                'matched_brand_assets' => $analysis['matched_brand_assets']->pluck('id')->all(),
                'matched_pages' => $analysis['matched_pages']->pluck('id')->all(),
                'matched_hosted_pages' => $analysis['matched_hosted_pages']->pluck('id')->all(),
            ],
            'metadata' => [
                'estimated' => true,
                'coverage_score' => $analysis['coverage_score'],
                'brand_score' => $analysis['brand_score'],
                'citation_score' => $analysis['citation_score'],
                'matched_articles_count' => $analysis['matched_articles']->count(),
                'matched_pages_count' => $analysis['matched_pages']->count(),
                'matched_brand_assets_count' => $analysis['matched_brand_assets']->count(),
                'matched_hosted_pages_count' => $analysis['matched_hosted_pages']->count(),
                'matched_sources_count' => $analysis['matched_articles']->count() + $analysis['matched_brand_assets']->count() + $analysis['matched_hosted_pages']->count(),
                'prompt_priority' => $prompt->priority,
                'prompt_source_type' => data_get($prompt->metadata, 'source_type', data_get($prompt->metadata, 'generated_from')),
                'prompt_source_label' => data_get($prompt->metadata, 'source_label'),
                'primary_article_id' => $analysis['matched_articles']->first()?->id,
                'primary_article_title' => $analysis['matched_articles']->first()?->title,
                'coverage_confidence' => min(100, max(15, $score)),
                'provider' => $this->key(),
            ],
            'mentions' => $this->buildMentions($site, $analysis, $appears),
            'sources' => $this->buildSources($analysis),
        ];
    }

    private function buildMentions(Site $site, array $analysis, bool $appears): array
    {
        $mentions = collect();

        if ($appears) {
            $topArticle = $analysis['matched_articles']->first();

            $mentions->push([
                'domain' => $site->domain,
                'url' => $topArticle?->published_url ?: $site->public_url,
                'brand_name' => $site->name,
                'mention_type' => 'owned_domain',
                'position' => 1,
                'is_our_brand' => true,
            ]);
        }

        $competitorDomains = $analysis['matched_articles']
            ->flatMap(fn (Article $article) => $article->citations->pluck('domain'))
            ->filter()
            ->filter(fn (string $domain) => !Str::contains($domain, $site->domain))
            ->unique()
            ->take(3)
            ->values();

        foreach ($competitorDomains as $index => $domain) {
            $mentions->push([
                'domain' => $domain,
                'url' => "https://{$domain}",
                'brand_name' => Str::headline((string) Str::before($domain, '.')),
                'mention_type' => 'competitor',
                'position' => $appears ? $index + 2 : $index + 1,
                'is_our_brand' => false,
            ]);
        }

        return $mentions->all();
    }

    private function buildSources(array $analysis): array
    {
        return collect()
            ->merge($analysis['matched_articles']->map(fn (Article $article) => [
                'source_domain' => $article->published_url ? parse_url($article->published_url, PHP_URL_HOST) : null,
                'source_url' => $article->published_url,
                'source_title' => $article->title,
            ]))
            ->merge($analysis['matched_brand_assets']->map(fn (BrandAsset $asset) => [
                'source_domain' => $asset->source_url ? parse_url($asset->source_url, PHP_URL_HOST) : null,
                'source_url' => $asset->source_url,
                'source_title' => $asset->title,
            ]))
            ->merge($analysis['matched_hosted_pages']->map(fn (HostedPage $page) => [
                'source_domain' => parse_url(
                    $analysis['matched_articles']->first()?->published_url ?: $page->site->public_url,
                    PHP_URL_HOST,
                ),
                'source_url' => rtrim((string) $page->site->public_url, '/') . $page->path(),
                'source_title' => $page->title,
            ]))
            ->filter(fn (array $source) => filled($source['source_url']) || filled($source['source_title']))
            ->unique(fn (array $source) => ($source['source_url'] ?? '') . '|' . ($source['source_title'] ?? ''))
            ->take(5)
            ->values()
            ->map(fn (array $source, int $index) => $source + ['position' => $index + 1])
            ->all();
    }

    private function rankBucket(int $score): ?string
    {
        return match (true) {
            $score >= 80 => 'primary',
            $score >= 65 => 'strong',
            $score >= 50 => 'emerging',
            default => null,
        };
    }
}
