<?php

namespace App\Services\Campaign;

use App\Jobs\GenerateArticleJob;
use App\Models\CampaignRun;
use App\Models\Keyword;
use App\Models\Site;
use App\Models\User;
use App\Services\Webhooks\WebhookDispatcher;
use Illuminate\Support\Collection;

class CampaignRunService
{
    public function __construct(
        protected WebhookDispatcher $webhooks,
    ) {}

    public function dispatchKeywordGenerationCampaign(Site $site, Collection $keywords, ?User $user = null): CampaignRun
    {
        $keywords = $keywords->values();

        $campaign = $site->campaignRuns()->create([
            'created_by' => $user?->id,
            'name' => 'Bulk generation - ' . now()->format('Y-m-d H:i'),
            'status' => CampaignRun::STATUS_PENDING,
            'input_type' => 'keyword_ids',
            'payload' => [
                'keyword_ids' => $keywords->pluck('id')->all(),
                'keyword_count' => $keywords->count(),
            ],
            'started_at' => now(),
        ]);

        $dispatched = 0;

        foreach ($keywords as $keyword) {
            if (!$keyword instanceof Keyword || $keyword->status !== Keyword::STATUS_PENDING) {
                continue;
            }

            $keyword->addToQueue();
            GenerateArticleJob::dispatch($keyword);
            $dispatched++;
        }

        $campaign->update([
            'status' => $dispatched > 0 ? CampaignRun::STATUS_DISPATCHED : CampaignRun::STATUS_FAILED,
            'processed_count' => $keywords->count(),
            'succeeded_count' => $dispatched,
            'failed_count' => max(0, $keywords->count() - $dispatched),
            'completed_at' => now(),
        ]);

        $campaign = $campaign->fresh(['site']);

        $this->webhooks->dispatch($site->team, 'campaign.completed', [
            'team_id' => $site->team_id,
            'site_id' => $site->id,
            'campaign_run_id' => $campaign->id,
            'name' => $campaign->name,
            'status' => $campaign->status,
            'processed_count' => $campaign->processed_count,
            'succeeded_count' => $campaign->succeeded_count,
            'failed_count' => $campaign->failed_count,
        ]);

        return $campaign;
    }
}
