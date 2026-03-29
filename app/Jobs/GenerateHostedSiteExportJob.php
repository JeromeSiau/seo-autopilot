<?php

namespace App\Jobs;

use App\Models\Site;
use App\Services\Hosted\HostedExportService;
use App\Services\Hosted\HostedSiteService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class GenerateHostedSiteExportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly Site $site,
    ) {}

    public function handle(HostedExportService $exports, HostedSiteService $hosting): void
    {
        $targetPath = $hosting->hostedSiteExportPath($this->site);
        $exports->createSiteExport($this->site->fresh(['hosting', 'hostedPages']), $targetPath);

        $this->site->hosting?->update([
            'last_exported_at' => now(),
        ]);
    }
}
