<?php

namespace App\Jobs;

use App\Models\Site;
use App\Services\ContentPlan\ContentPlanGeneratorService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class GenerateContentPlanJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 300;

    public function __construct(
        public Site $site
    ) {}

    public function handle(ContentPlanGeneratorService $generator): void
    {
        $generator->generate($this->site);
    }
}
