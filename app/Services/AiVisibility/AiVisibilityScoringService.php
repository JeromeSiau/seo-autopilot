<?php

namespace App\Services\AiVisibility;

use App\Models\AiPrompt;
use App\Models\AiPromptSet;
use App\Models\AiVisibilityAlert;
use App\Models\AiVisibilityCheck;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class AiVisibilityScoringService
{
    public function buildDashboardPayload(Collection $sites): array
    {
        $siteIds = $sites->pluck('id')->all();

        $prompts = AiPrompt::query()
            ->with('promptSet')
            ->whereIn('site_id', $siteIds)
            ->where('is_active', true)
            ->get();
        $promptSets = AiPromptSet::query()
            ->whereIn('site_id', $siteIds)
            ->where('is_active', true)
            ->get();

        $checkHistory = $this->checkHistoryForSites($siteIds, 2);
        $latestChecks = $checkHistory
            ->map(fn (Collection $checks) => $checks->first())
            ->filter()
            ->values();
        $previousChecks = $checkHistory
            ->mapWithKeys(fn (Collection $checks, string $key) => [$key => $checks->skip(1)->first()])
            ->filter();
        $historicalChecks = AiVisibilityCheck::query()
            ->with('prompt')
            ->whereIn('site_id', $siteIds)
            ->where('checked_at', '>=', now()->subDays(21))
            ->orderBy('checked_at')
            ->get();
        $promptRows = $latestChecks
            ->map(fn (AiVisibilityCheck $check) => $this->promptRow(
                $check,
                $previousChecks->get($this->historyKey($check)),
            ))
            ->values();

        $engineBreakdown = collect(AiVisibilityCheck::ENGINES)
            ->map(function (string $engine) use ($latestChecks) {
                $checks = $latestChecks->where('engine', $engine);

                return [
                    'engine' => $engine,
                    'avg_visibility_score' => round($checks->avg('visibility_score') ?? 0, 1),
                    'covered_prompts' => $checks->where('appears', true)->count(),
                    'total_prompts' => $checks->count(),
                ];
            })
            ->values()
            ->all();

        $avgVisibilityDelta = round(
            $promptRows
                ->filter(fn (array $row) => $row['previous_visibility_score'] !== null)
                ->avg('visibility_delta') ?? 0,
            1,
        );
        $decliningChecks = $promptRows
            ->filter(fn (array $row) => $row['previous_visibility_score'] !== null && $row['visibility_delta'] <= -6)
            ->count();
        $improvingChecks = $promptRows
            ->filter(fn (array $row) => $row['previous_visibility_score'] !== null && $row['visibility_delta'] >= 6)
            ->count();
        $highRiskPrompts = $promptRows
            ->filter(fn (array $row) => $row['visibility_score'] < 45
                || ($row['previous_visibility_score'] !== null
                    && $row['visibility_delta'] <= -12
                    && ($row['previous_visibility_score'] ?? 0) >= 55))
            ->count();
        $latestCheck = $latestChecks->sortByDesc('checked_at')->first();
        $checksByPromptId = $latestChecks->groupBy('ai_prompt_id');
        $promptSetRows = $promptSets
            ->map(function (AiPromptSet $set) use ($prompts, $checksByPromptId) {
                $setPrompts = $prompts->where('ai_prompt_set_id', $set->id);
                $checks = $setPrompts
                    ->flatMap(fn (AiPrompt $prompt) => $checksByPromptId->get($prompt->id, collect()))
                    ->values();

                return [
                    'id' => $set->id,
                    'key' => $set->key,
                    'name' => $set->name,
                    'description' => $set->description,
                    'is_default' => $set->is_default,
                    'prompt_count' => $setPrompts->count(),
                    'covered_prompts' => $checks->where('appears', true)->count(),
                    'avg_visibility_score' => round($checks->avg('visibility_score') ?? 0, 1),
                    'last_synced_at' => optional($set->last_synced_at)->toIso8601String(),
                ];
            })
            ->sortByDesc('avg_visibility_score')
            ->values()
            ->all();
        $alertHistory = AiVisibilityAlert::query()
            ->with(['prompt', 'article'])
            ->whereIn('site_id', $siteIds)
            ->latest('last_detected_at')
            ->take(count($siteIds) === 1 ? 12 : 8)
            ->get()
            ->map(fn (AiVisibilityAlert $alert) => [
                'id' => $alert->id,
                'type' => $alert->type,
                'severity' => $alert->severity,
                'status' => $alert->status,
                'title' => $alert->title,
                'reason' => $alert->reason,
                'prompt_id' => $alert->ai_prompt_id,
                'engine' => $alert->engine,
                'article_id' => $alert->article_id,
                'article_title' => $alert->article?->title ?: data_get($alert->metadata, 'article_title'),
                'visibility_delta' => $alert->visibility_delta,
                'related_domains' => array_values($alert->related_domains ?? []),
                'first_detected_at' => optional($alert->first_detected_at)->toIso8601String(),
                'last_detected_at' => optional($alert->last_detected_at)->toIso8601String(),
                'resolved_at' => optional($alert->resolved_at)->toIso8601String(),
            ])
            ->values()
            ->all();
        $intents = $prompts
            ->groupBy(fn (AiPrompt $prompt) => $prompt->intent ?: 'other')
            ->map(function (Collection $intentPrompts, string $intent) use ($checksByPromptId) {
                $checks = $intentPrompts
                    ->flatMap(fn (AiPrompt $prompt) => $checksByPromptId->get($prompt->id, collect()))
                    ->values();

                return [
                    'intent' => $intent,
                    'total_prompts' => $intentPrompts->count(),
                    'covered_prompts' => $checks->where('appears', true)->count(),
                    'avg_visibility_score' => round($checks->avg('visibility_score') ?? 0, 1),
                ];
            })
            ->sortByDesc('avg_visibility_score')
            ->values()
            ->all();

        return [
            'summary' => [
                'total_prompts' => $prompts->count(),
                'checked_prompts' => $latestChecks->pluck('ai_prompt_id')->unique()->count(),
                'covered_prompts' => $latestChecks->where('appears', true)->count(),
                'avg_visibility_score' => round($latestChecks->avg('visibility_score') ?? 0, 1),
                'avg_visibility_delta' => $avgVisibilityDelta,
                'declining_checks' => $decliningChecks,
                'improving_checks' => $improvingChecks,
                'high_risk_prompts' => $highRiskPrompts,
                'last_checked_at' => optional($latestCheck?->checked_at)->toIso8601String(),
            ],
            'engines' => $engineBreakdown,
            'top_prompts' => $promptRows
                ->sortByDesc('visibility_score')
                ->take(10)
                ->values()
                ->all(),
            'weakest_prompts' => $promptRows
                ->sortBy(fn (array $row) => ($row['visibility_score'] * 1000) + ($row['visibility_delta'] ?? 0))
                ->take(10)
                ->values()
                ->all(),
            'trend' => $historicalChecks
                ->groupBy(fn (AiVisibilityCheck $check) => optional($check->checked_at)->format('Y-m-d') ?: now()->format('Y-m-d'))
                ->map(function (Collection $checks, string $date) {
                    return [
                        'date' => $date,
                        'avg_visibility_score' => round($checks->avg('visibility_score') ?? 0, 1),
                        'covered_prompts' => $checks->where('appears', true)->count(),
                        'total_checks' => $checks->count(),
                    ];
                })
                ->sortBy('date')
                ->values()
                ->all(),
            'alerts' => $this->buildAlerts($promptRows),
            'movers' => $promptRows
                ->filter(fn (array $row) => $row['previous_visibility_score'] !== null)
                ->sortByDesc(fn (array $row) => abs((float) ($row['visibility_delta'] ?? 0)))
                ->take(8)
                ->values()
                ->all(),
            'competitors' => $latestChecks
                ->flatMap(fn (AiVisibilityCheck $check) => $check->mentions
                    ->where('is_our_brand', false)
                    ->map(fn ($mention) => [
                        'domain' => $mention->domain,
                        'brand_name' => $mention->brand_name ?: Str::headline((string) Str::before((string) $mention->domain, '.')),
                        'engine' => $check->engine,
                        'position' => $mention->position,
                    ]))
                ->filter(fn (array $mention) => filled($mention['domain']))
                ->groupBy('domain')
                ->map(function (Collection $mentions, string $domain) {
                    return [
                        'domain' => $domain,
                        'brand_name' => $mentions->first()['brand_name'] ?? Str::headline((string) Str::before($domain, '.')),
                        'mentions' => $mentions->count(),
                        'average_position' => round($mentions->avg('position') ?? 0, 1),
                        'engines' => $mentions->pluck('engine')->unique()->values()->all(),
                    ];
                })
                ->sortByDesc('mentions')
                ->take(10)
                ->values()
                ->all(),
            'sources' => $latestChecks
                ->flatMap(fn (AiVisibilityCheck $check) => $check->sources
                    ->map(fn ($source) => [
                        'source_domain' => $source->source_domain ?: parse_url((string) $source->source_url, PHP_URL_HOST),
                        'source_title' => $source->source_title,
                        'source_url' => $source->source_url,
                        'engine' => $check->engine,
                        'position' => $source->position,
                    ]))
                ->filter(fn (array $source) => filled($source['source_domain']) || filled($source['source_title']))
                ->groupBy(fn (array $source) => ($source['source_domain'] ?? 'unknown') . '|' . ($source['source_url'] ?? $source['source_title'] ?? ''))
                ->map(function (Collection $sources) {
                    $first = $sources->first();

                    return [
                        'source_domain' => $first['source_domain'] ?? null,
                        'source_title' => $first['source_title'] ?? null,
                        'source_url' => $first['source_url'] ?? null,
                        'mentions' => $sources->count(),
                        'average_position' => round($sources->avg('position') ?? 0, 1),
                        'engines' => $sources->pluck('engine')->unique()->values()->all(),
                    ];
                })
                ->sortByDesc('mentions')
                ->take(10)
                ->values()
                ->all(),
            'intents' => $intents,
            'prompt_sets' => $promptSetRows,
            'alert_history' => $alertHistory,
        ];
    }

    public function latestChecksForSites(array $siteIds): Collection
    {
        return $this->checkHistoryForSites($siteIds, 2)
            ->map(fn (Collection $checks) => $checks->first())
            ->filter()
            ->values();
    }

    public function averageForPrompt(AiPrompt $prompt, int $days = 7): ?float
    {
        $value = $prompt->checks()
            ->where('checked_at', '>=', now()->subDays($days))
            ->avg('visibility_score');

        return $value !== null ? round((float) $value, 1) : null;
    }

    private function checkHistoryForSites(array $siteIds, int $limitPerPromptEngine = 2): Collection
    {
        return AiVisibilityCheck::query()
            ->with(['prompt', 'mentions', 'sources'])
            ->whereIn('site_id', $siteIds)
            ->orderByDesc('checked_at')
            ->orderByDesc('id')
            ->get()
            ->groupBy(fn (AiVisibilityCheck $check) => $this->historyKey($check))
            ->map(fn (Collection $checks) => $checks->take($limitPerPromptEngine)->values());
    }

    private function historyKey(AiVisibilityCheck $check): string
    {
        return "{$check->ai_prompt_id}:{$check->engine}";
    }

    private function promptRow(AiVisibilityCheck $check, ?AiVisibilityCheck $previousCheck): array
    {
        $previousScore = $previousCheck?->visibility_score;
        $delta = $previousScore !== null
            ? round((float) $check->visibility_score - (float) $previousScore, 1)
            : 0.0;

        return [
            'id' => $check->id,
            'prompt_id' => $check->ai_prompt_id,
            'site_id' => $check->site_id,
            'prompt' => $check->prompt?->prompt,
            'topic' => $check->prompt?->topic,
            'intent' => $check->prompt?->intent,
            'engine' => $check->engine,
            'visibility_score' => (int) $check->visibility_score,
            'previous_visibility_score' => $previousScore !== null ? (int) $previousScore : null,
            'visibility_delta' => $delta,
            'appears' => (bool) $check->appears,
            'rank_bucket' => $check->rank_bucket,
            'checked_at' => optional($check->checked_at)->toIso8601String(),
            'prompt_priority' => (int) (data_get($check->metadata, 'prompt_priority', $check->prompt?->priority) ?? 0),
            'source_type' => data_get($check->prompt?->metadata ?? [], 'source_type', data_get($check->metadata, 'prompt_source_type')),
            'source_label' => data_get($check->prompt?->metadata ?? [], 'source_label', data_get($check->metadata, 'prompt_source_label')),
            'article_id' => data_get($check->metadata, 'primary_article_id'),
            'article_title' => data_get($check->metadata, 'primary_article_title'),
            'matched_sources_count' => (int) data_get($check->metadata, 'matched_sources_count', $check->sources->count()),
            'related_domains' => $check->mentions
                ->where('is_our_brand', false)
                ->pluck('domain')
                ->filter()
                ->unique()
                ->values()
                ->all(),
            'trend' => match (true) {
                $previousScore === null => 'flat',
                $delta >= 6 => 'up',
                $delta <= -6 => 'down',
                default => 'flat',
            },
        ];
    }

    private function buildAlerts(Collection $promptRows): array
    {
        $alerts = collect();

        $coverageDrops = $promptRows
            ->filter(fn (array $row) => $row['previous_visibility_score'] !== null && $row['visibility_delta'] <= -10)
            ->sortBy('visibility_delta')
            ->take(3)
            ->map(fn (array $row) => [
                'type' => 'coverage_drop',
                'severity' => $row['visibility_delta'] <= -18 || $row['visibility_score'] < 45 ? 'high' : 'medium',
                'title' => "Coverage dropped for “{$row['topic']}”",
                'reason' => "This prompt lost {$this->formatDelta($row['visibility_delta'])} points on " . $this->engineLabel($row['engine']) . '.',
                'prompt_id' => $row['prompt_id'],
                'engine' => $row['engine'],
                'article_id' => $row['article_id'],
                'article_title' => $row['article_title'],
                'visibility_delta' => $row['visibility_delta'],
                'related_domains' => $row['related_domains'],
            ]);

        $competitorPressure = $promptRows
            ->filter(fn (array $row) => !empty($row['related_domains']) && (!$row['appears'] || $row['visibility_score'] < 60))
            ->sortBy(fn (array $row) => $row['visibility_score'])
            ->take(2)
            ->map(fn (array $row) => [
                'type' => 'competitor_pressure',
                'severity' => $row['visibility_score'] < 45 ? 'high' : 'medium',
                'title' => "Competitors are winning “{$row['topic']}”",
                'reason' => 'Competing domains are surfacing more strongly than your coverage for this prompt.',
                'prompt_id' => $row['prompt_id'],
                'engine' => $row['engine'],
                'article_id' => $row['article_id'],
                'article_title' => $row['article_title'],
                'visibility_delta' => $row['visibility_delta'],
                'related_domains' => array_slice($row['related_domains'], 0, 3),
            ]);

        $sourceGaps = $promptRows
            ->filter(fn (array $row) => $row['article_id'] !== null && $row['matched_sources_count'] < 2 && $row['visibility_score'] < 60)
            ->sortBy('matched_sources_count')
            ->take(2)
            ->map(fn (array $row) => [
                'type' => 'source_gap',
                'severity' => 'medium',
                'title' => "Source depth is thin for “{$row['topic']}”",
                'reason' => 'The linked article exists, but the latest checks still show weak source support.',
                'prompt_id' => $row['prompt_id'],
                'engine' => $row['engine'],
                'article_id' => $row['article_id'],
                'article_title' => $row['article_title'],
                'visibility_delta' => $row['visibility_delta'],
                'related_domains' => [],
            ]);

        $opportunities = $promptRows
            ->filter(fn (array $row) => $row['article_id'] === null && $row['visibility_score'] < 50)
            ->sortByDesc(fn (array $row) => $row['prompt_priority'])
            ->take(2)
            ->map(fn (array $row) => [
                'type' => 'opportunity',
                'severity' => $row['visibility_score'] < 35 ? 'high' : 'medium',
                'title' => "No owned coverage for “{$row['topic']}”",
                'reason' => 'This prompt cluster is active in AI answers, but your site does not have a clearly mapped article yet.',
                'prompt_id' => $row['prompt_id'],
                'engine' => $row['engine'],
                'article_id' => null,
                'article_title' => null,
                'visibility_delta' => $row['visibility_delta'],
                'related_domains' => array_slice($row['related_domains'], 0, 3),
            ]);

        $alerts = $alerts
            ->merge($coverageDrops)
            ->merge($competitorPressure)
            ->merge($sourceGaps)
            ->merge($opportunities)
            ->unique(fn (array $alert) => ($alert['type'] ?? 'alert') . ':' . ($alert['prompt_id'] ?? 'site') . ':' . ($alert['engine'] ?? 'all'))
            ->sortByDesc(fn (array $alert) => match ($alert['severity']) {
                'high' => 3,
                'medium' => 2,
                default => 1,
            })
            ->values();

        return $alerts->all();
    }

    private function engineLabel(string $engine): string
    {
        return match ($engine) {
            AiVisibilityCheck::ENGINE_AI_OVERVIEWS => 'AI Overviews',
            AiVisibilityCheck::ENGINE_CHATGPT => 'ChatGPT',
            AiVisibilityCheck::ENGINE_PERPLEXITY => 'Perplexity',
            AiVisibilityCheck::ENGINE_GEMINI => 'Gemini',
            default => Str::headline($engine),
        };
    }

    private function formatDelta(float $delta): string
    {
        return number_format(abs($delta), 1);
    }
}
