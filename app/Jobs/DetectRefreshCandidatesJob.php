<?php

namespace App\Jobs;

use App\Models\Site;
use App\Services\Refresh\RefreshDetectionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class DetectRefreshCandidatesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $timeout = 180;

    public function __construct(
        public readonly Site $site,
    ) {}

    public function handle(RefreshDetectionService $service): void
    {
        $service->detectForSite($this->site);
    }
}
