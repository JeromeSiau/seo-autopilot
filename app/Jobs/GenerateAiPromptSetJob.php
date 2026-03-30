<?php

namespace App\Jobs;

use App\Models\Site;
use App\Services\AiVisibility\AiPromptService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class GenerateAiPromptSetJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $timeout = 120;

    public function __construct(
        public readonly Site $site,
        public readonly int $limit = 12,
    ) {}

    public function handle(AiPromptService $service): void
    {
        $service->syncForSite($this->site, $this->limit);
    }
}
