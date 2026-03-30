<?php

namespace App\Jobs;

use App\Models\HostedExportRun;
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
        public readonly ?int $exportRunId = null,
    ) {}

    public function handle(HostedExportService $exports, HostedSiteService $hosting): void
    {
        $site = $this->site->fresh(['hosting', 'hostedPages']);
        $targetPath = $hosting->hostedSiteExportPath($site);
        $run = $this->exportRunId
            ? HostedExportRun::query()->find($this->exportRunId)
            : $hosting->queueSiteExport($site);

        if (!$run) {
            $run = $hosting->queueSiteExport($site);
        }

        $hosting->startSiteExportRun($site, $run, $targetPath);

        try {
            $exports->createSiteExport($site, $targetPath);
            $hosting->completeSiteExportRun($site, $run, $targetPath);
        } catch (\Throwable $exception) {
            $hosting->failSiteExportRun($site, $run, $exception);

            throw $exception;
        }

        $site->hosting?->update([
            'last_exported_at' => now(),
            'last_error' => null,
        ]);
    }
}
