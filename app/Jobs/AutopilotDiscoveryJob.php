<?php

namespace App\Jobs;

use App\Services\Autopilot\AutopilotService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class AutopilotDiscoveryJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 300;

    public function handle(AutopilotService $autopilot): void
    {
        Log::info('AutopilotDiscoveryJob: Starting keyword discovery for all active sites');

        $sites = $autopilot->getActiveSites();
        $totalDiscovered = 0;

        foreach ($sites as $site) {
            try {
                $count = $autopilot->processKeywordDiscovery($site);
                $totalDiscovered += $count;

                Log::info("AutopilotDiscoveryJob: Discovered {$count} keywords for site {$site->domain}");
            } catch (\Exception $e) {
                Log::error("AutopilotDiscoveryJob: Failed for site {$site->domain}", [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::info("AutopilotDiscoveryJob: Completed. Total discovered: {$totalDiscovered}");
    }

    public function tags(): array
    {
        return ['autopilot', 'discovery'];
    }
}
