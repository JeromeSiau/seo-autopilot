<?php

namespace App\Services\AiVisibility\Connectors;

use App\Models\AiPrompt;
use App\Models\AiVisibilityCheck;
use App\Models\Article;
use App\Models\Site;
use App\Services\SEO\DataForSEOService;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class DataForSeoAiOverviewConnector implements AiVisibilityConnector
{
    public function key(): string
    {
        return 'dataforseo_ai_overview';
    }

    public function supportsEngine(string $engine): bool
    {
        return $engine === AiVisibilityCheck::ENGINE_AI_OVERVIEWS;
    }

    public function isAvailable(): bool
    {
        return filled(config('services.dataforseo.login'))
            && filled(config('services.dataforseo.password'));
    }

    public function evaluate(Site $site, AiPrompt $prompt, string $engine, array $analysis): array
    {
        if (!$this->supportsEngine($engine)) {
            throw new \InvalidArgumentException("Connector [{$this->key()}] does not support engine [{$engine}].");
        }

        $result = app(DataForSEOService::class)->getGoogleOrganicAdvancedResult(
            keyword: $prompt->prompt,
            language: $prompt->locale ?: ($site->language ?: 'en'),
            location: $prompt->country ?: $this->locationForLanguage($prompt->locale ?: $site->language ?: 'en'),
            depth: 10,
        );

        $items = collect(data_get($result, 'items', []))
            ->filter(fn ($item) => is_array($item))
            ->values();
        $aiOverviewItem = $this->resolveAiOverviewItem($items);
        $organicItems = $items
            ->filter(fn (array $item) => data_get($item, 'type') === 'organic')
            ->values();
        $siteDomain = $this->normalizeDomain($site->domain);
        $aiSources = $this->collectSourcesFromAiOverview($aiOverviewItem);
        $aiSourceDomains = $aiSources
            ->pluck('source_domain')
            ->filter()
            ->map(fn (string $domain) => $this->normalizeDomain($domain))
            ->unique()
            ->values();
        $ownedInAiOverview = $aiSourceDomains->contains(fn (string $domain) => $this->isOwnedDomain($siteDomain, $domain));
        $organicRank = $organicItems
            ->map(function (array $item) use ($siteDomain) {
                $domain = $this->normalizeDomain((string) ($item['domain'] ?? parse_url((string) ($item['url'] ?? ''), PHP_URL_HOST)));

                if ($domain === '' || !$this->isOwnedDomain($siteDomain, $domain)) {
                    return null;
                }

                return (int) ($item['rank_absolute'] ?? $item['rank_group'] ?? 0);
            })
            ->filter(fn (?int $rank) => $rank !== null && $rank > 0)
            ->sort()
            ->first();
        $competitorDomains = $this->competitorDomains($organicItems, $aiSources, $siteDomain);
        $aiOverviewPresent = $aiOverviewItem !== null || $items->contains(fn (array $item) => Str::contains((string) data_get($item, 'type'), 'ai'));
        $score = $this->score(
            aiOverviewPresent: $aiOverviewPresent,
            ownedInAiOverview: $ownedInAiOverview,
            organicRank: $organicRank,
            aiSourceCount: $aiSources->count(),
            internalCoverageScore: (int) ($analysis['base_score'] ?? 0),
            competitorCount: $competitorDomains->count(),
        );

        return [
            'status' => 'completed',
            'visibility_score' => $score,
            'appears' => $ownedInAiOverview,
            'rank_bucket' => $this->rankBucket($score),
            'raw_response' => [
                'keyword' => $prompt->prompt,
                'check_url' => data_get($result, 'check_url'),
                'item_types' => data_get($result, 'item_types', []),
                'ai_overview_present' => $aiOverviewPresent,
                'owned_in_ai_overview' => $ownedInAiOverview,
                'organic_rank' => $organicRank,
                'top_source_urls' => $aiSources->pluck('source_url')->filter()->take(5)->values()->all(),
                'top_competitor_domains' => $competitorDomains->take(5)->values()->all(),
            ],
            'metadata' => [
                'estimated' => false,
                'provider' => $this->key(),
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
                'coverage_confidence' => $this->coverageConfidence($ownedInAiOverview, $aiOverviewPresent, $score),
                'check_url' => data_get($result, 'check_url'),
                'ai_overview_present' => $aiOverviewPresent,
                'owned_in_ai_overview' => $ownedInAiOverview,
                'organic_rank' => $organicRank,
                'competitor_domains' => $competitorDomains->values()->all(),
            ],
            'mentions' => $this->buildMentions($site, $analysis['matched_articles'], $ownedInAiOverview, $aiSources, $organicItems, $competitorDomains),
            'sources' => $this->buildSources($aiSources, $organicItems),
        ];
    }

    private function resolveAiOverviewItem(Collection $items): ?array
    {
        $item = $items->first(function (array $item) {
            $type = (string) data_get($item, 'type');

            return in_array($type, ['ai_overview', 'ai_summary'], true)
                || (filled(data_get($item, 'references')) && Str::contains($type, 'ai'));
        });

        return is_array($item) ? $item : null;
    }

    private function collectSourcesFromAiOverview(?array $item): Collection
    {
        if ($item === null) {
            return collect();
        }

        $sources = collect(data_get($item, 'references', []))
            ->merge(data_get($item, 'links', []))
            ->merge(data_get($item, 'sources', []))
            ->merge($this->collectNestedReferences($item))
            ->filter(fn ($source) => is_array($source))
            ->map(function (array $source) {
                $url = (string) ($source['url'] ?? $source['source_url'] ?? $source['link'] ?? $source['href'] ?? '');
                $domain = (string) ($source['domain'] ?? parse_url($url, PHP_URL_HOST));
                $title = (string) ($source['title'] ?? $source['source_title'] ?? $source['text'] ?? '');

                if ($url === '' && $title === '') {
                    return null;
                }

                return [
                    'source_domain' => $domain !== '' ? $this->normalizeDomain($domain) : null,
                    'source_url' => $url !== '' ? $url : null,
                    'source_title' => $title !== '' ? Str::limit($title, 140, '') : null,
                ];
            })
            ->filter()
            ->unique(fn (array $source) => ($source['source_url'] ?? '') . '|' . ($source['source_title'] ?? ''))
            ->values();

        return $sources;
    }

    private function collectNestedReferences(array $node): Collection
    {
        $collected = collect();

        foreach ($node as $value) {
            if (!is_array($value)) {
                continue;
            }

            if (isset($value['url']) || isset($value['source_url']) || isset($value['href']) || isset($value['link'])) {
                $collected->push($value);
            }

            $collected = $collected->merge($this->collectNestedReferences($value));
        }

        return $collected;
    }

    private function competitorDomains(Collection $organicItems, Collection $aiSources, string $siteDomain): Collection
    {
        return $organicItems
            ->map(fn (array $item) => $this->normalizeDomain((string) ($item['domain'] ?? parse_url((string) ($item['url'] ?? ''), PHP_URL_HOST))))
            ->merge($aiSources->pluck('source_domain'))
            ->filter()
            ->reject(fn (string $domain) => $this->isOwnedDomain($siteDomain, $domain))
            ->unique()
            ->values();
    }

    private function score(
        bool $aiOverviewPresent,
        bool $ownedInAiOverview,
        ?int $organicRank,
        int $aiSourceCount,
        int $internalCoverageScore,
        int $competitorCount,
    ): int {
        $score = $aiOverviewPresent ? 24 : 8;
        $score += $ownedInAiOverview ? 46 : 0;
        $score += match (true) {
            $organicRank !== null && $organicRank <= 2 => 16,
            $organicRank !== null && $organicRank <= 5 => 10,
            $organicRank !== null && $organicRank <= 10 => 5,
            default => 0,
        };
        $score += min(8, $aiSourceCount * 2);
        $score += min(10, (int) round($internalCoverageScore / 12));

        if (!$ownedInAiOverview && $aiOverviewPresent && $competitorCount > 0) {
            $score -= 10;
        }

        return max(5, min(100, $score));
    }

    private function buildMentions(
        Site $site,
        Collection $matchedArticles,
        bool $ownedInAiOverview,
        Collection $aiSources,
        Collection $organicItems,
        Collection $competitorDomains,
    ): array {
        $mentions = collect();

        if ($ownedInAiOverview) {
            $topArticle = $matchedArticles->first();
            $ownSource = $aiSources->first(fn (array $source) => $this->isOwnedDomain($this->normalizeDomain($site->domain), (string) ($source['source_domain'] ?? '')));

            $mentions->push([
                'domain' => $site->domain,
                'url' => $ownSource['source_url'] ?? ($topArticle?->published_url ?: $site->public_url),
                'brand_name' => $site->name,
                'mention_type' => 'owned_domain',
                'position' => 1,
                'is_our_brand' => true,
            ]);
        }

        foreach ($competitorDomains->take(3) as $index => $domain) {
            $organicMatch = $organicItems->first(fn (array $item) => $this->normalizeDomain((string) ($item['domain'] ?? parse_url((string) ($item['url'] ?? ''), PHP_URL_HOST))) === $domain);

            $mentions->push([
                'domain' => $domain,
                'url' => (string) ($organicMatch['url'] ?? "https://{$domain}"),
                'brand_name' => Str::headline((string) Str::before($domain, '.')),
                'mention_type' => 'competitor',
                'position' => (int) ($organicMatch['rank_absolute'] ?? ($index + ($ownedInAiOverview ? 2 : 1))),
                'is_our_brand' => false,
            ]);
        }

        return $mentions->values()->all();
    }

    private function buildSources(Collection $aiSources, Collection $organicItems): array
    {
        return $aiSources
            ->merge($organicItems->take(5)->map(fn (array $item) => [
                'source_domain' => $this->normalizeDomain((string) ($item['domain'] ?? parse_url((string) ($item['url'] ?? ''), PHP_URL_HOST))),
                'source_url' => (string) ($item['url'] ?? ''),
                'source_title' => (string) ($item['title'] ?? ''),
            ]))
            ->filter(fn (array $source) => filled($source['source_url']) || filled($source['source_title']))
            ->unique(fn (array $source) => ($source['source_url'] ?? '') . '|' . ($source['source_title'] ?? ''))
            ->take(5)
            ->values()
            ->map(fn (array $source, int $index) => $source + ['position' => $index + 1])
            ->all();
    }

    private function coverageConfidence(bool $ownedInAiOverview, bool $aiOverviewPresent, int $score): int
    {
        if ($ownedInAiOverview) {
            return min(100, max(70, $score));
        }

        if ($aiOverviewPresent) {
            return min(85, max(45, $score));
        }

        return min(60, max(20, $score));
    }

    private function locationForLanguage(string $language): string
    {
        return match (Str::lower($language)) {
            'fr', 'french' => 'France',
            'de', 'german' => 'Germany',
            'es', 'spanish' => 'Spain',
            'it', 'italian' => 'Italy',
            'pt', 'portuguese' => 'Brazil',
            default => 'United States',
        };
    }

    private function normalizeDomain(string $domain): string
    {
        return Str::lower((string) preg_replace('#^www\.#', '', trim($domain)));
    }

    private function isOwnedDomain(string $siteDomain, string $candidate): bool
    {
        $candidate = $this->normalizeDomain($candidate);

        return $candidate !== ''
            && ($candidate === $siteDomain || Str::endsWith($candidate, ".{$siteDomain}"));
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
