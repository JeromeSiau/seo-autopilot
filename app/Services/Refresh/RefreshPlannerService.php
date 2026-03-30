<?php

namespace App\Services\Refresh;

use App\Models\ArticleRefreshRun;
use App\Models\RefreshRecommendation;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class RefreshPlannerService
{
    public function buildQueue(Collection $recommendations): array
    {
        $items = $recommendations
            ->map(fn (RefreshRecommendation $recommendation) => $this->mapRecommendation($recommendation))
            ->values()
            ->all();

        return [
            'summary' => [
                'total' => $recommendations->count(),
                'open' => $recommendations->where('status', RefreshRecommendation::STATUS_OPEN)->count(),
                'accepted' => $recommendations->where('status', RefreshRecommendation::STATUS_ACCEPTED)->count(),
                'executed' => $recommendations->where('status', RefreshRecommendation::STATUS_EXECUTED)->count(),
                'dismissed' => $recommendations->where('status', RefreshRecommendation::STATUS_DISMISSED)->count(),
            ],
            'items' => $items,
        ];
    }

    private function mapRecommendation(RefreshRecommendation $recommendation): array
    {
        /** @var ArticleRefreshRun|null $latestRun */
        $latestRun = $recommendation->runs->sortByDesc('created_at')->first();
        $oldReadiness = data_get($latestRun?->old_score_snapshot, 'readiness_score');
        $newReadiness = data_get($latestRun?->new_score_snapshot, 'readiness_score');
        $draftContent = (string) data_get($latestRun?->metadata, 'draft_content', '');
        $aiVisibility = data_get($recommendation->metrics_snapshot, 'ai_visibility', []);
        $recentMetrics = data_get($recommendation->metrics_snapshot, 'recent', []);
        $previousMetrics = data_get($recommendation->metrics_snapshot, 'previous', []);

        return [
            'id' => $recommendation->id,
            'site_id' => $recommendation->site_id,
            'site_name' => $recommendation->site?->name,
            'article_id' => $recommendation->article_id,
            'article_title' => $recommendation->article?->title,
            'article_status' => $recommendation->article?->status,
            'trigger_type' => $recommendation->trigger_type,
            'severity' => $recommendation->severity,
            'reason' => $recommendation->reason,
            'recommended_actions' => $recommendation->recommended_actions ?? [],
            'metrics_snapshot' => $recommendation->metrics_snapshot ?? [],
            'business_attribution' => $this->mapBusinessAttribution($recentMetrics, $previousMetrics),
            'ai_visibility' => !empty($aiVisibility) ? [
                'recent_avg' => data_get($aiVisibility, 'recent_avg'),
                'previous_avg' => data_get($aiVisibility, 'previous_avg'),
                'delta' => data_get($aiVisibility, 'delta'),
                'appears_rate' => data_get($aiVisibility, 'appears_rate'),
                'weakest_engine' => data_get($aiVisibility, 'weakest_engine'),
                'weakest_score' => data_get($aiVisibility, 'weakest_score'),
                'matching_prompts' => data_get($aiVisibility, 'matching_prompts', []),
                'competitor_domains' => data_get($aiVisibility, 'competitor_domains', []),
                'largest_drop' => data_get($aiVisibility, 'largest_drop'),
            ] : null,
            'status' => $recommendation->status,
            'detected_at' => optional($recommendation->detected_at)->toIso8601String(),
            'executed_at' => optional($recommendation->executed_at)->toIso8601String(),
            'next_action' => $this->nextAction($recommendation),
            'latest_run' => $latestRun ? [
                'id' => $latestRun->id,
                'status' => $latestRun->status,
                'summary' => $latestRun->summary,
                'draft_meta_title' => data_get($latestRun->metadata, 'draft_meta_title'),
                'draft_meta_description' => data_get($latestRun->metadata, 'draft_meta_description'),
                'draft_content_excerpt' => Str::limit(trim(strip_tags($draftContent)), 220),
                'diff' => data_get($latestRun->metadata, 'diff', []),
                'focus_sections' => data_get($latestRun->metadata, 'focus_sections', []),
                'business_case' => data_get($latestRun->metadata, 'business_case', []),
                'old_readiness_score' => is_numeric($oldReadiness) ? (int) $oldReadiness : null,
                'new_readiness_score' => is_numeric($newReadiness) ? (int) $newReadiness : null,
                'readiness_delta' => is_numeric($oldReadiness) && is_numeric($newReadiness)
                    ? (int) $newReadiness - (int) $oldReadiness
                    : null,
            ] : null,
        ];
    }

    private function nextAction(RefreshRecommendation $recommendation): string
    {
        return match ($recommendation->status) {
            RefreshRecommendation::STATUS_OPEN => 'accept_recommendation',
            RefreshRecommendation::STATUS_ACCEPTED => 'generate_refresh_draft',
            RefreshRecommendation::STATUS_EXECUTED => 'review_draft',
            RefreshRecommendation::STATUS_DISMISSED => 'dismissed',
            default => 'review_recommendation',
        };
    }

    private function mapBusinessAttribution(array $recent, array $previous): ?array
    {
        if (empty($recent) && empty($previous)) {
            return null;
        }

        return [
            'recent' => [
                'clicks' => data_get($recent, 'clicks'),
                'sessions' => data_get($recent, 'sessions'),
                'estimated_conversions' => data_get($recent, 'estimated_conversions'),
                'traffic_value' => data_get($recent, 'traffic_value'),
                'conversion_source' => data_get($recent, 'conversion_source'),
            ],
            'previous' => [
                'clicks' => data_get($previous, 'clicks'),
                'sessions' => data_get($previous, 'sessions'),
                'estimated_conversions' => data_get($previous, 'estimated_conversions'),
                'traffic_value' => data_get($previous, 'traffic_value'),
                'conversion_source' => data_get($previous, 'conversion_source'),
            ],
            'deltas' => [
                'clicks' => $this->buildDelta(data_get($recent, 'clicks'), data_get($previous, 'clicks')),
                'sessions' => $this->buildDelta(data_get($recent, 'sessions'), data_get($previous, 'sessions')),
                'estimated_conversions' => $this->buildDelta(data_get($recent, 'estimated_conversions'), data_get($previous, 'estimated_conversions')),
                'traffic_value' => $this->buildDelta(data_get($recent, 'traffic_value'), data_get($previous, 'traffic_value')),
            ],
        ];
    }

    private function buildDelta(mixed $recent, mixed $previous): array
    {
        $recentValue = is_numeric($recent) ? (float) $recent : 0.0;
        $previousValue = is_numeric($previous) ? (float) $previous : 0.0;

        return [
            'absolute' => round($recentValue - $previousValue, 2),
            'percentage' => $previousValue > 0 ? round((($recentValue - $previousValue) / $previousValue) * 100, 2) : null,
        ];
    }
}
