<?php

namespace App\Jobs;

use App\Events\SiteCrawlProgress;
use App\Models\Site;
use App\Services\Crawler\SiteIndexService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SiteIndexJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $timeout = 900;
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

        $this->site->update(['crawl_status' => 'running']);
        broadcast(new SiteCrawlProgress($this->site, 'running', $this->site->crawl_pages_count));

        try {
            $result = $indexService->indexSite($this->site, $this->delta);

            $this->site->update([
                'crawl_status' => 'completed',
                'crawl_pages_count' => $result['pages_indexed'] ?? 0,
                'last_crawled_at' => now(),
            ]);

            broadcast(new SiteCrawlProgress($this->site, 'completed', $result['pages_indexed'] ?? 0));

            Log::info('SiteIndexJob: Completed', [
                'site_id' => $this->site->id,
                'pages_indexed' => $result['pages_indexed'] ?? 0,
            ]);
        } catch (\Exception $e) {
            Log::error('SiteIndexJob: Failed', [
                'site_id' => $this->site->id,
                'error' => $e->getMessage(),
            ]);

            $this->site->update(['crawl_status' => 'failed']);
            broadcast(new SiteCrawlProgress($this->site, 'failed', $this->site->crawl_pages_count));

            throw $e;
        }
    }

    public function uniqueId(): string
    {
        return 'site-index-' . $this->site->id;
    }

    public function uniqueFor(): int
    {
        return 3600;
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('SiteIndexJob: Permanently failed', [
            'site_id' => $this->site->id,
            'domain' => $this->site->domain,
            'error' => $exception->getMessage(),
        ]);

        $this->site->update(['crawl_status' => 'failed']);
        broadcast(new SiteCrawlProgress($this->site, 'failed', $this->site->crawl_pages_count));
    }

    public function tags(): array
    {
        return [
            'site-indexer',
            'site:' . $this->site->id,
        ];
    }
}
