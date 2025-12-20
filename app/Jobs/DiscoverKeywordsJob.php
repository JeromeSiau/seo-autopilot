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

class DiscoverKeywordsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 300;
    public int $backoff = 60;

    public function __construct(
        public readonly Site $site,
        public readonly array $options = [],
    ) {}

    public function handle(KeywordDiscoveryService $discoveryService): void
    {
        Log::info("DiscoverKeywordsJob: Starting for site {$this->site->domain}");

        try {
            // Discover keywords
            $keywords = $discoveryService->discoverKeywords($this->site, $this->options);

            // Save to database
            $saved = $discoveryService->saveKeywords($this->site, $keywords);

            Log::info("DiscoverKeywordsJob: Completed", [
                'site_id' => $this->site->id,
                'discovered' => $keywords->count(),
                'saved' => $saved,
            ]);

            // Dispatch clustering job if we have new keywords
            if ($saved > 0) {
                ClusterKeywordsJob::dispatch($this->site)->delay(now()->addSeconds(30));
            }
        } catch (\Exception $e) {
            Log::error("DiscoverKeywordsJob: Failed", [
                'site_id' => $this->site->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("DiscoverKeywordsJob: Job failed permanently", [
            'site_id' => $this->site->id,
            'error' => $exception->getMessage(),
        ]);
    }

    public function tags(): array
    {
        return [
            'keyword-discovery',
            'site:' . $this->site->id,
        ];
    }
}
