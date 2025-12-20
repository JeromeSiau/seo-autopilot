<?php

namespace App\Jobs;

use App\Models\Site;
use App\Services\Analytics\AnalyticsSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncSiteAnalyticsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 300;
    public int $backoff = 120;

    public function __construct(
        public readonly Site $site,
        public readonly int $days = 7,
    ) {}

    public function handle(AnalyticsSyncService $syncService): void
    {
        Log::info("SyncSiteAnalyticsJob: Starting for site {$this->site->domain}");

        try {
            $stats = $syncService->syncSite($this->site, $this->days);

            Log::info("SyncSiteAnalyticsJob: Completed", [
                'site_id' => $this->site->id,
                'articles_synced' => $stats['articles_synced'],
                'gsc_records' => $stats['gsc_records'],
                'ga4_records' => $stats['ga4_records'],
            ]);
        } catch (\Exception $e) {
            Log::error("SyncSiteAnalyticsJob: Failed", [
                'site_id' => $this->site->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("SyncSiteAnalyticsJob: Job failed permanently", [
            'site_id' => $this->site->id,
            'error' => $exception->getMessage(),
        ]);
    }

    public function tags(): array
    {
        return [
            'analytics-sync',
            'site:' . $this->site->id,
        ];
    }
}
