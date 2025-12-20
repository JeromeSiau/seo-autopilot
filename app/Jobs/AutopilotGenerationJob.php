<?php

namespace App\Jobs;

use App\Services\Autopilot\AutopilotService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class AutopilotGenerationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 60;

    public function handle(AutopilotService $autopilot): void
    {
        Log::info('AutopilotGenerationJob: Checking for articles to generate');

        $sites = $autopilot->getActiveSites();
        $generated = 0;

        foreach ($sites as $site) {
            try {
                if ($autopilot->processArticleGeneration($site)) {
                    $generated++;
                }
            } catch (\Exception $e) {
                Log::error("AutopilotGenerationJob: Failed for site {$site->domain}", [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::info("AutopilotGenerationJob: Dispatched {$generated} article generation jobs");
    }

    public function tags(): array
    {
        return ['autopilot', 'generation'];
    }
}
