<?php

namespace App\Jobs;

use App\Models\Site;
use App\Services\AiVisibility\AiVisibilityAlertService;
use App\Services\AiVisibility\AiVisibilityRunner;
use App\Services\AiVisibility\AiVisibilityRecommendationService;
use App\Services\AiVisibility\AiVisibilityScoringService;
use App\Services\Notification\NotificationService;
use App\Services\Webhooks\WebhookDispatcher;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RunAiVisibilityChecksJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $timeout = 180;

    public function __construct(
        public readonly Site $site,
        public readonly ?array $engines = null,
    ) {}

    public function handle(
        AiVisibilityRunner $runner,
        AiVisibilityScoringService $scoring,
        AiVisibilityRecommendationService $recommendations,
        AiVisibilityAlertService $alertHistory,
        NotificationService $notifications,
        WebhookDispatcher $webhooks,
    ): void
    {
        $checks = $runner->runForSite($this->site, $this->engines);
        $site = $this->site->fresh();
        $payload = $scoring->buildDashboardPayload(collect([$site]));
        $payload['recommendations'] = $recommendations->buildRecommendations(
            $site->loadMissing(['articles.keyword', 'brandAssets']),
            $scoring->latestChecksForSites([$site->id]),
        );
        $alertHistory->syncForSite($site, $payload['alerts'] ?? [], $checks);
        $payload = $scoring->buildDashboardPayload(collect([$site]));
        $payload['recommendations'] = $recommendations->buildRecommendations(
            $site->loadMissing(['articles.keyword', 'brandAssets']),
            $scoring->latestChecksForSites([$site->id]),
        );

        $webhookPayload = [
            'team_id' => $site->team_id,
            'site_id' => $site->id,
            'checks_count' => $checks->count(),
            'engines' => $checks->pluck('engine')->unique()->values()->all(),
            'summary' => $payload['summary'],
            'alerts' => collect($payload['alerts'] ?? [])->take(3)->values()->all(),
            'recommendations' => collect($payload['recommendations'] ?? [])->take(3)->values()->all(),
        ];

        if (($payload['summary']['high_risk_prompts'] ?? 0) > 0 || collect($payload['alerts'] ?? [])->contains(fn ($alert) => ($alert['severity'] ?? null) === 'high')) {
            $notifications->notifyAiVisibilityAlert($site, $payload);
        }

        $webhooks->dispatch($site->team, 'ai_visibility.changed', $webhookPayload);
    }
}
