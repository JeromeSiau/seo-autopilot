<?php

namespace App\Services\AiVisibility;

use App\Models\AiVisibilityCheck;
use App\Models\Article;
use App\Models\BrandAsset;
use App\Models\HostedPage;
use App\Models\AiPrompt;
use App\Models\Site;
use App\Models\SitePage;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class AiVisibilityRunner
{
    public function __construct(
        private readonly AiPromptService $promptService,
        private readonly AiVisibilityConnectorRegistry $connectors,
    ) {}

    public function runForSite(Site $site, ?array $engines = null, ?int $promptLimit = null, ?string $provider = null): Collection
    {
        $site->loadMissing([
            'aiPrompts',
            'articles.keyword',
            'articles.citations',
            'articles.score',
            'brandAssets',
            'pages',
            'hostedPages',
        ]);

        $prompts = $site->aiPrompts
            ->where('is_active', true)
            ->sortByDesc('priority')
            ->take($promptLimit ?? 12);

        if ($prompts->isEmpty()) {
            $prompts = $this->promptService->syncForSite($site, $promptLimit ?? 12);
        }

        $engines ??= AiVisibilityCheck::ENGINES;
        $connector = $this->connectors->resolve($provider);
        $checks = collect();

        foreach ($prompts as $prompt) {
            $analysis = $this->analysePromptCoverage($site, $prompt->prompt, $prompt->topic ?: $prompt->prompt);

            foreach ($engines as $engine) {
                $evaluation = $connector->evaluate($site, $prompt, $engine, $analysis);

                $check = $prompt->checks()->create([
                    'site_id' => $site->id,
                    'engine' => $engine,
                    'provider' => $connector->key(),
                    'status' => $evaluation['status'],
                    'visibility_score' => $evaluation['visibility_score'],
                    'appears' => $evaluation['appears'],
                    'rank_bucket' => $evaluation['rank_bucket'],
                    'raw_response' => $evaluation['raw_response'],
                    'metadata' => $evaluation['metadata'],
                    'checked_at' => now(),
                ]);

                $this->persistMentions($check, $evaluation['mentions'] ?? []);
                $this->persistSources($check, $evaluation['sources'] ?? []);

                $checks->push($check->load(['mentions', 'sources', 'prompt']));
            }
        }

        return $checks;
    }

    private function analysePromptCoverage(Site $site, string $promptText, string $topic): array
    {
        $tokens = $this->tokens($topic . ' ' . $promptText);

        $matchedArticles = $site->articles
            ->whereIn('status', [Article::STATUS_REVIEW, Article::STATUS_APPROVED, Article::STATUS_PUBLISHED])
            ->filter(fn (Article $article) => $this->textMatches($tokens, implode(' ', array_filter([
                $article->title,
                $article->keyword?->keyword,
                strip_tags((string) Str::limit((string) $article->content, 1200, '')),
            ]))))
            ->values();

        $matchedBrandAssets = $site->brandAssets
            ->where('is_active', true)
            ->filter(fn (BrandAsset $asset) => $this->textMatches($tokens, implode(' ', [$asset->title, Str::limit((string) $asset->content, 1200, '')])))
            ->values();

        $matchedPages = $site->pages
            ->filter(fn (SitePage $page) => $this->textMatches($tokens, implode(' ', [$page->title, (string) $page->url])))
            ->values();

        $matchedHostedPages = $site->hostedPages
            ->where('is_published', true)
            ->filter(fn (HostedPage $page) => $this->textMatches($tokens, implode(' ', [
                $page->title,
                strip_tags((string) Str::limit((string) $page->body_html, 1200, '')),
            ])))
            ->values();

        $coverageScore = min(50, $matchedArticles->count() * 18)
            + min(15, $matchedPages->count() * 5)
            + min(15, $matchedHostedPages->count() * 6);
        $brandScore = min(25, $matchedBrandAssets->count() * 8);
        $citationScore = min(25, $matchedArticles->sum(fn (Article $article) => min(5, $article->citations->count() * 2)));
        $readinessLift = min(12, (int) round($matchedArticles->avg(fn (Article $article) => $article->score?->readiness_score ?? 60) / 10));

        return [
            'matched_articles' => $matchedArticles,
            'matched_brand_assets' => $matchedBrandAssets,
            'matched_pages' => $matchedPages,
            'matched_hosted_pages' => $matchedHostedPages,
            'coverage_score' => $coverageScore,
            'brand_score' => $brandScore,
            'citation_score' => $citationScore,
            'base_score' => max(10, min(100, $coverageScore + $brandScore + $citationScore + $readinessLift)),
        ];
    }

    private function persistMentions(AiVisibilityCheck $check, array $mentions): void
    {
        foreach ($mentions as $mention) {
            if (!is_array($mention) || blank($mention['domain'] ?? null)) {
                continue;
            }

            $check->mentions()->create([
                'domain' => $mention['domain'],
                'url' => $mention['url'] ?? null,
                'brand_name' => $mention['brand_name'] ?? null,
                'mention_type' => $mention['mention_type'] ?? 'domain',
                'position' => $mention['position'] ?? null,
                'is_our_brand' => (bool) ($mention['is_our_brand'] ?? false),
            ]);
        }
    }

    private function persistSources(AiVisibilityCheck $check, array $sources): void
    {
        foreach ($sources as $index => $source) {
            if (!is_array($source) || (blank($source['source_url'] ?? null) && blank($source['source_title'] ?? null))) {
                continue;
            }

            $check->sources()->create([
                'source_domain' => $source['source_domain'] ?? null,
                'source_url' => $source['source_url'] ?? null,
                'source_title' => $source['source_title'] ?? null,
                'position' => $source['position'] ?? ($index + 1),
            ]);
        }
    }

    private function textMatches(array $tokens, string $text): bool
    {
        if (empty($tokens)) {
            return false;
        }

        $haystack = Str::lower($text);
        $matches = collect($tokens)
            ->filter(fn (string $token) => Str::contains($haystack, $token))
            ->count();

        return $matches >= max(1, min(2, count($tokens)));
    }

    private function tokens(string $text): array
    {
        return collect(preg_split('/[^a-z0-9]+/i', Str::lower($text)) ?: [])
            ->filter(fn (?string $token) => filled($token) && Str::length($token) >= 4)
            ->reject(fn (string $token) => in_array($token, ['what', 'best', '2026', 'with', 'your', 'from', 'that', 'this', 'into', 'does', 'work', 'should', 'choose'], true))
            ->unique()
            ->values()
            ->all();
    }
}
