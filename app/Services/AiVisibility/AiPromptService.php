<?php

namespace App\Services\AiVisibility;

use App\Models\AiPrompt;
use App\Models\AiPromptSet;
use App\Models\Article;
use App\Models\Keyword;
use App\Models\RefreshRecommendation;
use App\Models\Site;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class AiPromptService
{
    public function syncForSite(Site $site, int $limit = 12): Collection
    {
        $site->loadMissing([
            'keywords',
            'brandAssets',
            'pages',
            'hostedPages',
            'articles.keyword',
            'articles.score',
            'refreshRecommendations.article.keyword',
            'latestContentPlanGeneration',
        ]);
        $defaultPromptSet = AiPromptSet::updateOrCreate(
            [
                'site_id' => $site->id,
                'key' => 'core_coverage',
            ],
            [
                'name' => 'Core coverage',
                'description' => 'Default AI visibility prompt set generated from keywords, brand assets, pages, hosted content and refresh signals.',
                'is_active' => true,
                'is_default' => true,
                'last_synced_at' => now(),
            ],
        );

        $hasContentPlanSignal = (bool) optional($site->latestContentPlanGeneration, function ($generation) {
            return $generation->isCompleted() || $generation->keywords_found > 0 || $generation->articles_planned > 0;
        });

        $keywordCandidates = $site->keywords
            ->sortByDesc(fn (Keyword $keyword) => (float) ($keyword->score ?? 0))
            ->map(fn (Keyword $keyword) => [
                'topic' => $keyword->keyword,
                'priority' => min(100, max(40, (int) round((float) ($keyword->score ?? 50)) + ($hasContentPlanSignal ? 5 : 0))),
                'source' => 'keyword',
                'source_id' => $keyword->id,
                'source_label' => $keyword->keyword,
                'metadata' => [],
            ]);

        $topicCandidates = collect($site->topics ?? [])
            ->map(fn (string $topic) => [
                'topic' => $topic,
                'priority' => 65 + ($hasContentPlanSignal ? 5 : 0),
                'source' => 'site_topic',
                'source_id' => null,
                'source_label' => $topic,
                'metadata' => [],
            ]);

        $brandCandidates = $site->brandAssets
            ->where('is_active', true)
            ->sortByDesc('priority')
            ->map(fn ($asset) => [
                'topic' => $asset->title,
                'priority' => min(100, max(35, (int) $asset->priority)),
                'source' => 'brand_asset',
                'source_id' => $asset->id,
                'source_label' => $asset->title,
                'metadata' => [
                    'source_url' => $asset->source_url,
                ],
            ]);

        $pageCandidates = $site->pages
            ->map(fn ($page) => [
                'topic' => $page->title ?: Str::headline(trim(parse_url((string) $page->url, PHP_URL_PATH) ?: 'overview', '/')),
                'priority' => 45,
                'source' => 'site_page',
                'source_id' => $page->id,
                'source_label' => $page->title ?: $page->url,
                'metadata' => [
                    'source_url' => $page->url,
                ],
            ]);

        $hostedPageCandidates = $site->hostedPages
            ->where('is_published', true)
            ->map(fn ($page) => [
                'topic' => $page->title,
                'priority' => 52,
                'source' => 'hosted_page',
                'source_id' => $page->id,
                'source_label' => $page->title,
                'metadata' => [
                    'page_path' => $page->path(),
                ],
            ]);

        $publishedArticleCandidates = $site->articles
            ->where('status', Article::STATUS_PUBLISHED)
            ->sortByDesc(fn (Article $article) => ($article->score?->readiness_score ?? 70) + ($article->published_at?->timestamp ?? 0))
            ->map(function (Article $article) {
                $topic = trim((string) ($article->keyword?->keyword ?: $article->title));

                return [
                    'topic' => $topic,
                    'priority' => min(100, max(55, (int) round($article->score?->readiness_score ?? 72))),
                    'source' => 'published_article',
                    'source_id' => $article->id,
                    'source_label' => $article->title,
                    'metadata' => [
                        'seed_article_id' => $article->id,
                        'article_title' => $article->title,
                        'published_url' => $article->published_url,
                    ],
                ];
            });

        $refreshCandidates = $site->refreshRecommendations
            ->whereIn('status', [
                RefreshRecommendation::STATUS_OPEN,
                RefreshRecommendation::STATUS_ACCEPTED,
                RefreshRecommendation::STATUS_EXECUTED,
            ])
            ->sortByDesc(fn (RefreshRecommendation $recommendation) => match ($recommendation->severity) {
                'high' => 100,
                'medium' => 85,
                default => 72,
            })
            ->map(function (RefreshRecommendation $recommendation) {
                $topic = trim((string) ($recommendation->article?->keyword?->keyword ?: $recommendation->article?->title ?: ''));

                return [
                    'topic' => $topic,
                    'priority' => match ($recommendation->severity) {
                        'high' => 98,
                        'medium' => 86,
                        default => 74,
                    },
                    'source' => 'refresh_recommendation',
                    'source_id' => $recommendation->id,
                    'source_label' => $recommendation->article?->title ?: $topic,
                    'metadata' => [
                        'seed_article_id' => $recommendation->article_id,
                        'article_title' => $recommendation->article?->title,
                        'refresh_trigger' => $recommendation->trigger_type,
                        'refresh_severity' => $recommendation->severity,
                    ],
                ];
            });

        $topics = collect()
            ->merge($keywordCandidates)
            ->merge($topicCandidates)
            ->merge($brandCandidates)
            ->merge($pageCandidates)
            ->merge($hostedPageCandidates)
            ->merge($publishedArticleCandidates)
            ->merge($refreshCandidates)
            ->filter(fn (array $candidate) => filled($candidate['topic']))
            ->map(function (array $candidate) {
                $candidate['topic'] = trim((string) $candidate['topic']);

                return $candidate;
            })
            ->filter(fn (array $candidate) => Str::length($candidate['topic']) >= 4)
            ->groupBy(fn (array $candidate) => Str::lower($candidate['topic']))
            ->map(fn (Collection $group) => $group->sortByDesc('priority')->first())
            ->sortByDesc('priority')
            ->take(max(4, (int) ceil($limit / 3)))
            ->values();

        $promptPayloads = $topics
            ->flatMap(fn (array $candidate) => $this->promptVariants($site, $candidate))
            ->take($limit)
            ->values();

        $activePrompts = [];

        foreach ($promptPayloads as $payload) {
            $prompt = AiPrompt::updateOrCreate(
                [
                    'site_id' => $site->id,
                    'prompt' => $payload['prompt'],
                ],
                [
                    'ai_prompt_set_id' => $defaultPromptSet->id,
                    'topic' => $payload['topic'],
                    'intent' => $payload['intent'],
                    'priority' => $payload['priority'],
                    'locale' => $site->language ?: 'en',
                    'country' => null,
                    'is_active' => true,
                    'metadata' => $payload['metadata'],
                    'last_generated_at' => now(),
                ],
            );

            $activePrompts[] = $prompt->id;
        }

        if (!empty($activePrompts)) {
            $site->aiPrompts()
                ->whereNotIn('id', $activePrompts)
                ->update(['is_active' => false]);
        }

        return $site->aiPrompts()
            ->whereIn('id', $activePrompts)
            ->orderByDesc('priority')
            ->get();
    }

    private function promptVariants(Site $site, array $candidate): Collection
    {
        $topic = trim($candidate['topic']);
        $audience = trim((string) ($site->target_audience ?: 'buyers'));
        $priority = (int) $candidate['priority'];
        $source = $candidate['source'];
        $sourceMetadata = [
            'generated_from' => $source,
            'source_type' => $source,
            'source_id' => $candidate['source_id'] ?? null,
            'source_label' => $candidate['source_label'] ?? $topic,
            'has_content_plan_signal' => (bool) optional($site->latestContentPlanGeneration, function ($generation) {
                return $generation->isCompleted() || $generation->keywords_found > 0 || $generation->articles_planned > 0;
            }),
        ];
        $sourceMetadata = array_filter(
            $sourceMetadata + ($candidate['metadata'] ?? []),
            fn ($value) => $value !== null && $value !== '',
        );

        $variants = [
            [
                'prompt' => "What is {$topic} and how should a {$audience} evaluate it?",
                'topic' => $topic,
                'intent' => 'understand',
                'priority' => $priority,
                'metadata' => $sourceMetadata,
            ],
            [
                'prompt' => "How does {$topic} compare to the main alternatives for {$audience}?",
                'topic' => $topic,
                'intent' => 'comparison',
                'priority' => max(35, $priority - 2),
                'metadata' => $sourceMetadata,
            ],
        ];

        if (in_array($source, ['published_article', 'refresh_recommendation'], true)) {
            $variants[] = [
                'prompt' => "How should teams implement {$topic} successfully in 2026?",
                'topic' => $topic,
                'intent' => 'implementation',
                'priority' => max(35, $priority - 4),
                'metadata' => $sourceMetadata,
            ];
        } else {
            $variants[] = [
                'prompt' => "Best practices for {$topic} in 2026",
                'topic' => $topic,
                'intent' => 'best_practices',
                'priority' => max(35, $priority - 4),
                'metadata' => $sourceMetadata,
            ];
        }

        if ($priority >= 82 || in_array($source, ['brand_asset', 'hosted_page', 'refresh_recommendation'], true)) {
            $variants[] = [
                'prompt' => "What questions should {$audience} ask before choosing {$topic}?",
                'topic' => $topic,
                'intent' => 'faq',
                'priority' => max(35, $priority - 8),
                'metadata' => $sourceMetadata,
            ];
        }

        return collect($variants);
    }
}
