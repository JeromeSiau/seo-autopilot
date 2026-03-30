<?php

namespace App\Services\Refresh;

use App\Models\AiPrompt;
use App\Models\Article;
use App\Models\RefreshRecommendation;
use App\Models\Site;
use App\Services\Webhooks\WebhookDispatcher;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class RefreshDetectionService
{
    public function __construct(
        private readonly WebhookDispatcher $webhooks,
    ) {}

    public function detectForSite(Site $site): Collection
    {
        $site->loadMissing([
            'articles.analytics',
            'articles.keyword',
            'articles.score',
            'articles.refreshRecommendations',
            'aiPrompts.checks.mentions',
        ]);

        $createdOrUpdated = collect();

        foreach ($site->articles->where('status', Article::STATUS_PUBLISHED) as $article) {
            foreach ($this->detectTriggers($site, $article) as $trigger) {
                $createdOrUpdated->push($this->upsertRecommendation($article, $trigger));
            }
        }

        return $createdOrUpdated->filter()->values();
    }

    private function detectTriggers(Site $site, Article $article): array
    {
        $triggers = [];
        $recent = $this->metricsForPeriod($article, 7);
        $previous = $this->metricsForPeriod($article, 14, 7);

        if ($previous['avg_position'] !== null && $recent['avg_position'] !== null && ($recent['avg_position'] - $previous['avg_position']) >= 5) {
            $triggers[] = $this->buildTrigger(
                RefreshRecommendation::TRIGGER_POSITION_DROP,
                'high',
                "Average position dropped from {$previous['avg_position']} to {$recent['avg_position']} over the last two weeks.",
                ['Update the opening sections', 'Add fresher supporting evidence', 'Strengthen internal links'],
                compact('recent', 'previous'),
            );
        }

        if ($previous['clicks'] >= 20 && $recent['clicks'] <= (int) round($previous['clicks'] * 0.7)) {
            $triggers[] = $this->buildTrigger(
                RefreshRecommendation::TRIGGER_TRAFFIC_DROP,
                'high',
                "Clicks fell from {$previous['clicks']} to {$recent['clicks']} across comparable 7-day windows.",
                ['Refresh the title and meta description', 'Re-check search intent fit', 'Expand the most clicked sections'],
                compact('recent', 'previous'),
            );
        }

        if (
            $previous['ctr'] !== null
            && $recent['ctr'] !== null
            && $previous['impressions'] >= 50
            && $recent['impressions'] >= (int) round($previous['impressions'] * 0.8)
            && $recent['ctr'] <= round($previous['ctr'] * 0.75, 2)
        ) {
            $triggers[] = $this->buildTrigger(
                RefreshRecommendation::TRIGGER_CTR_DROP,
                'medium',
                "CTR slipped from {$previous['ctr']}% to {$recent['ctr']}% while impressions stayed relatively stable.",
                ['Rewrite the title hook', 'Sharpen the meta description', 'Align the intro with the search promise'],
                compact('recent', 'previous'),
            );
        }

        $aiVisibility = $this->aiVisibilitySignal($site, $article);
        if (
            $aiVisibility
            && (
                ($aiVisibility['previous_avg'] !== null
                    && $aiVisibility['delta'] !== null
                    && $aiVisibility['delta'] <= -6)
                || (($aiVisibility['recent_avg'] ?? 0) < 45 && ($aiVisibility['previous_avg'] ?? 0) >= 55)
            )
        ) {
            $triggers[] = $this->buildTrigger(
                RefreshRecommendation::TRIGGER_AI_VISIBILITY_DROP,
                $aiVisibility['delta'] <= -18 || ($aiVisibility['recent_avg'] ?? 0) < 45 ? 'high' : 'medium',
                "Estimated AI visibility declined from {$aiVisibility['previous_avg']} to {$aiVisibility['recent_avg']} across the mapped prompt set.",
                [
                    'Add an explicit FAQ block',
                    'Surface stronger proof points',
                    'Cover missing subtopics more directly',
                    'Tighten comparisons against competing approaches',
                ],
                [
                    'recent' => $recent,
                    'previous' => $previous,
                    'ai_visibility' => $aiVisibility,
                ],
            );
        }

        if (
            $aiVisibility
            && !empty($aiVisibility['competitor_domains'])
            && (($aiVisibility['appears_rate'] ?? 0) < 0.6 || ($aiVisibility['recent_avg'] ?? 0) < 65)
        ) {
            $triggers[] = $this->buildTrigger(
                RefreshRecommendation::TRIGGER_COMPETITOR_GAP,
                (($aiVisibility['recent_avg'] ?? 0) < 45 || ($aiVisibility['appears_rate'] ?? 0) < 0.25) ? 'high' : 'medium',
                'Competing domains are showing up more consistently than your article on the mapped AI prompt cluster.',
                [
                    'Add a stronger comparison section',
                    'Clarify differentiators in the introduction',
                    'Reinforce citations and proof points',
                ],
                [
                    'recent' => $recent,
                    'previous' => $previous,
                    'ai_visibility' => $aiVisibility,
                ],
            );
        }

        $ageInDays = $article->published_at?->diffInDays(now()) ?? 0;
        $readinessScore = (int) ($article->score?->readiness_score ?? 0);

        if ($ageInDays >= 120 && $readinessScore < 75) {
            $triggers[] = $this->buildTrigger(
                RefreshRecommendation::TRIGGER_CONTENT_DECAY,
                'low',
                "This article is {$ageInDays} days old and its readiness score is {$readinessScore}/100.",
                ['Update outdated sections', 'Add new sources and examples', 'Review internal links and CTA placement'],
                [
                    'recent' => $recent,
                    'previous' => $previous,
                    'age_in_days' => $ageInDays,
                    'readiness_score' => $readinessScore,
                ],
            );
        }

        return $triggers;
    }

    private function metricsForPeriod(Article $article, int $days, int $offsetDays = 0): array
    {
        $end = now()->subDays($offsetDays);
        $start = now()->subDays($offsetDays + $days);
        $cpc = max((float) ($article->keyword?->cpc ?? 0.5), 0.1);

        $period = $article->analytics
            ->whereBetween('date', [$start, $end]);

        $clicks = (int) $period->sum('clicks');
        $impressions = (int) $period->sum('impressions');
        $sessions = (int) $period->sum('sessions');
        $pageViews = (int) $period->sum('page_views');
        $conversions = (int) $period->sum('conversions');
        $avgPosition = $period->avg('position');
        $ctr = $impressions > 0 ? round(($clicks / $impressions) * 100, 2) : null;
        $baseline = $sessions > 0 ? $sessions : $clicks;
        $estimatedConversions = $conversions > 0 ? (float) $conversions : round($baseline * 0.02, 1);

        return [
            'clicks' => $clicks,
            'impressions' => $impressions,
            'sessions' => $sessions,
            'page_views' => $pageViews,
            'conversions' => $conversions,
            'estimated_conversions' => $estimatedConversions,
            'conversion_source' => $conversions > 0 ? 'tracked' : 'modeled',
            'traffic_value' => round($clicks * $cpc, 2),
            'avg_position' => $avgPosition !== null ? round((float) $avgPosition, 1) : null,
            'ctr' => $ctr,
        ];
    }

    private function aiVisibilitySignal(Site $site, Article $article): ?array
    {
        $matchingPromptIds = $site->aiPrompts
            ->filter(fn (AiPrompt $prompt) => $this->promptMatchesArticle($prompt, $article))
            ->pluck('id');

        if ($matchingPromptIds->isEmpty()) {
            return null;
        }

        $checks = $site->aiPrompts
            ->whereIn('id', $matchingPromptIds)
            ->flatMap->checks;

        if ($checks->isEmpty()) {
            return null;
        }

        $checkHistory = $checks
            ->sortByDesc(fn ($check) => optional($check->checked_at)?->timestamp ?? 0)
            ->groupBy(fn ($check) => "{$check->ai_prompt_id}:{$check->engine}");

        $latestChecks = $checkHistory
            ->map(fn (Collection $group) => $group->first())
            ->filter()
            ->values();

        if ($latestChecks->isEmpty()) {
            return null;
        }

        $previousChecks = $checkHistory
            ->map(fn (Collection $group) => $group->skip(1)->first())
            ->filter()
            ->values();

        $recent = $latestChecks->avg('visibility_score');
        $previous = $previousChecks->isNotEmpty() ? $previousChecks->avg('visibility_score') : null;
        $largestDrop = $latestChecks
            ->map(function ($check) use ($previousChecks) {
                $previousCheck = $previousChecks->first(
                    fn ($candidate) => $candidate->ai_prompt_id === $check->ai_prompt_id && $candidate->engine === $check->engine
                );
                $delta = $previousCheck ? round((float) $check->visibility_score - (float) $previousCheck->visibility_score, 1) : null;

                return [
                    'topic' => $check->prompt?->topic ?: $check->prompt?->prompt,
                    'engine' => $check->engine,
                    'current_score' => (int) $check->visibility_score,
                    'previous_score' => $previousCheck ? (int) $previousCheck->visibility_score : null,
                    'delta' => $delta,
                ];
            })
            ->filter(fn (array $row) => $row['delta'] !== null)
            ->sortBy('delta')
            ->first();
        $competitorDomains = $latestChecks
            ->flatMap(fn ($check) => $check->mentions
                ->where('is_our_brand', false)
                ->pluck('domain'))
            ->filter()
            ->unique()
            ->values();
        $appearsRate = $latestChecks->count() > 0
            ? round($latestChecks->where('appears', true)->count() / $latestChecks->count(), 2)
            : null;

        return [
            'recent_avg' => $recent !== null ? round((float) $recent, 1) : null,
            'previous_avg' => $previous !== null ? round((float) $previous, 1) : null,
            'delta' => $recent !== null && $previous !== null ? round((float) $recent - (float) $previous, 1) : null,
            'appears_rate' => $appearsRate,
            'matching_prompts' => $site->aiPrompts
                ->whereIn('id', $matchingPromptIds)
                ->map(fn (AiPrompt $prompt) => $prompt->topic ?: $prompt->prompt)
                ->filter()
                ->unique()
                ->take(5)
                ->values()
                ->all(),
            'competitor_domains' => $competitorDomains->take(5)->all(),
            'weakest_engine' => $latestChecks->sortBy('visibility_score')->first()?->engine,
            'weakest_score' => $latestChecks->min('visibility_score'),
            'largest_drop' => $largestDrop,
        ];
    }

    private function promptMatchesArticle(AiPrompt $prompt, Article $article): bool
    {
        $needle = Str::lower(implode(' ', array_filter([
            $article->keyword?->keyword,
            $article->title,
        ])));
        $promptText = Str::lower(trim(($prompt->topic ?: '') . ' ' . $prompt->prompt));

        if (blank($needle) || blank($promptText)) {
            return false;
        }

        if (Str::contains($promptText, $needle) || Str::contains($needle, Str::lower((string) $prompt->topic))) {
            return true;
        }

        $tokens = collect(preg_split('/[^a-z0-9]+/i', $needle) ?: [])
            ->filter(fn (?string $token) => filled($token) && Str::length($token) >= 4)
            ->values();

        return $tokens->isNotEmpty()
            && $tokens->filter(fn (string $token) => Str::contains($promptText, $token))->count() >= max(1, min(2, $tokens->count()));
    }

    private function buildTrigger(string $type, string $severity, string $reason, array $recommendedActions, array $metrics): array
    {
        return [
            'trigger_type' => $type,
            'severity' => $severity,
            'reason' => $reason,
            'recommended_actions' => $recommendedActions,
            'metrics_snapshot' => $metrics,
            'detected_at' => now(),
        ];
    }

    private function upsertRecommendation(Article $article, array $trigger): RefreshRecommendation
    {
        $recommendation = $article->refreshRecommendations()
            ->where('trigger_type', $trigger['trigger_type'])
            ->whereIn('status', [RefreshRecommendation::STATUS_OPEN, RefreshRecommendation::STATUS_ACCEPTED])
            ->first();

        if ($recommendation) {
            $recommendation->update($trigger);

            return $recommendation->fresh();
        }

        $created = $article->refreshRecommendations()->create([
            'site_id' => $article->site_id,
            ...$trigger,
            'status' => RefreshRecommendation::STATUS_OPEN,
        ]);

        $this->webhooks->dispatch($article->site->team, 'refresh.detected', [
            'team_id' => $article->site->team_id,
            'site_id' => $article->site_id,
            'article_id' => $article->id,
            'refresh_recommendation_id' => $created->id,
            'trigger_type' => $created->trigger_type,
            'severity' => $created->severity,
            'reason' => $created->reason,
        ]);

        return $created;
    }
}
