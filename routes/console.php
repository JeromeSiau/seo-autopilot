<?php

use App\Jobs\AutopilotDiscoveryJob;
use App\Jobs\AutopilotGenerationJob;
use App\Jobs\AutopilotPublishJob;
use App\Jobs\DetectRefreshCandidatesJob;
use App\Jobs\GenerateAiPromptSetJob;
use App\Jobs\RunAiVisibilityChecksJob;
use App\Jobs\SyncSiteAnalyticsJob;
use App\Models\Site;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Keyword discovery - once daily at 6 AM
Schedule::job(new AutopilotDiscoveryJob)->dailyAt('06:00');

// Article generation - every hour from 8 AM to 8 PM
Schedule::job(new AutopilotGenerationJob)->hourly()->between('8:00', '20:00');

// Publishing - every hour
Schedule::job(new AutopilotPublishJob)->hourly();

// Sync analytics for all sites daily at 3 AM
Schedule::call(function () {
    Site::whereNotNull('gsc_token')
        ->orWhereNotNull('ga4_token')
        ->each(function ($site) {
            SyncSiteAnalyticsJob::dispatch($site, 7);
        });
})->dailyAt('03:00')->name('sync-all-site-analytics');

// Refresh prompt inventory before AI visibility checks.
Schedule::call(function () {
    Site::query()->each(fn (Site $site) => GenerateAiPromptSetJob::dispatch($site));
})->dailyAt('04:30')->name('sync-ai-visibility-prompts');

Schedule::call(function () {
    Site::query()->each(fn (Site $site) => RunAiVisibilityChecksJob::dispatch($site));
})->dailyAt('05:00')->name('run-ai-visibility-checks');

Schedule::call(function () {
    Site::query()->each(fn (Site $site) => DetectRefreshCandidatesJob::dispatch($site));
})->dailyAt('05:30')->name('detect-refresh-candidates');

// Weekly site re-crawl - Sundays at 2 AM (delta mode)
Schedule::command('sites:schedule-crawls')->weeklyOn(0, '02:00')->name('weekly-site-crawls');

// Artisan command for manual sync
Artisan::command('analytics:sync {site?}', function (?int $site = null) {
    if ($site) {
        $siteModel = Site::findOrFail($site);
        SyncSiteAnalyticsJob::dispatch($siteModel, 30);
        $this->info("Analytics sync job dispatched for site: {$siteModel->domain}");
    } else {
        $count = Site::whereNotNull('gsc_token')->count();
        Site::whereNotNull('gsc_token')->each(function ($site) {
            SyncSiteAnalyticsJob::dispatch($site, 30);
        });
        $this->info("Analytics sync jobs dispatched for {$count} sites");
    }
})->purpose('Sync analytics data for sites');

Artisan::command('ai-visibility:sync-prompts {site?}', function (?int $site = null) {
    if ($site) {
        $siteModel = Site::findOrFail($site);
        GenerateAiPromptSetJob::dispatchSync($siteModel);
        $this->info("AI prompt sync completed for {$siteModel->domain}");
        return;
    }

    Site::query()->each(fn (Site $siteModel) => GenerateAiPromptSetJob::dispatchSync($siteModel));
    $this->info('AI prompt sync completed for all sites');
})->purpose('Generate or refresh AI visibility prompts');

Artisan::command('ai-visibility:check {site?}', function (?int $site = null) {
    if ($site) {
        $siteModel = Site::findOrFail($site);
        RunAiVisibilityChecksJob::dispatchSync($siteModel);
        $this->info("AI visibility checks completed for {$siteModel->domain}");
        return;
    }

    Site::query()->each(fn (Site $siteModel) => RunAiVisibilityChecksJob::dispatchSync($siteModel));
    $this->info('AI visibility checks completed for all sites');
})->purpose('Run AI visibility checks');

Artisan::command('refresh:detect {site?}', function (?int $site = null) {
    if ($site) {
        $siteModel = Site::findOrFail($site);
        DetectRefreshCandidatesJob::dispatchSync($siteModel);
        $this->info("Refresh detection completed for {$siteModel->domain}");
        return;
    }

    Site::query()->each(fn (Site $siteModel) => DetectRefreshCandidatesJob::dispatchSync($siteModel));
    $this->info('Refresh detection completed for all sites');
})->purpose('Detect refresh opportunities');
