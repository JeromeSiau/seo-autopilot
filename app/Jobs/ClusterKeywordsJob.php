<?php

namespace App\Jobs;

use App\Models\Site;
use App\Services\Keyword\KeywordDiscoveryService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ClusterKeywordsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $timeout = 120;
    public int $backoff = 30;

    public function __construct(
        public readonly Site $site,
    ) {}

    public function handle(KeywordDiscoveryService $discoveryService): void
    {
        Log::info("ClusterKeywordsJob: Starting for site {$this->site->domain}");

        try {
            $clusters = $discoveryService->clusterKeywords($this->site);

            Log::info("ClusterKeywordsJob: Completed", [
                'site_id' => $this->site->id,
                'clusters_created' => count($clusters),
            ]);
        } catch (\Exception $e) {
            Log::error("ClusterKeywordsJob: Failed", [
                'site_id' => $this->site->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("ClusterKeywordsJob: Job failed permanently", [
            'site_id' => $this->site->id,
            'error' => $exception->getMessage(),
        ]);
    }

    public function tags(): array
    {
        return [
            'keyword-clustering',
            'site:' . $this->site->id,
        ];
    }
}
