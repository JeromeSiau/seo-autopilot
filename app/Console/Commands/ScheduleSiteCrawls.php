<?php

namespace App\Console\Commands;

use App\Jobs\SiteIndexJob;
use App\Models\Site;
use Illuminate\Console\Command;

class ScheduleSiteCrawls extends Command
{
    protected $signature = 'sites:schedule-crawls {--full : Run full crawl instead of delta}';
    protected $description = 'Schedule site crawl jobs with chunked delays';

    public function handle(): int
    {
        $delta = !$this->option('full');
        $delay = 0;
        $batchSize = 50;
        $delayBetweenBatches = 30; // minutes

        $totalSites = Site::whereNotNull('domain')->count();
        $this->info("Scheduling crawls for {$totalSites} sites...");

        Site::whereNotNull('domain')
            ->chunk($batchSize, function ($sites) use (&$delay, $delayBetweenBatches, $delta) {
                foreach ($sites as $site) {
                    SiteIndexJob::dispatch($site, $delta)
                        ->onQueue('crawl')
                        ->delay(now()->addMinutes($delay));
                }

                $delay += $delayBetweenBatches;
                $this->info("Scheduled batch, next batch in {$delay} minutes");
            });

        $this->info("All crawls scheduled. Last batch will run in ~{$delay} minutes.");

        return Command::SUCCESS;
    }
}
