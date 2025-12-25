<?php

namespace App\Jobs;

use App\Models\Site;
use App\Services\Crawler\SiteIndexService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SiteIndexJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $timeout = 900; // 15 minutes
    public int $backoff = 120;

    public function __construct(
        public readonly Site $site,
        public readonly bool $delta = true,
    ) {}

    public function handle(SiteIndexService $indexService): void
    {
        Log::info('SiteIndexJob: Starting', [
            'site_id' => $this->site->id,
            'domain' => $this->site->domain,
            'delta' => $this->delta,
        ]);

        try {
            $result = $indexService->indexSite($this->site, $this->delta);

            Log::info('SiteIndexJob: Completed', [
                'site_id' => $this->site->id,
                'pages_indexed' => $result['pages_indexed'] ?? 0,
            ]);
        } catch (\Exception $e) {
            Log::error('SiteIndexJob: Failed', [
                'site_id' => $this->site->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    public function tags(): array
    {
        return [
            'site-indexer',
            'site:' . $this->site->id,
        ];
    }
}
