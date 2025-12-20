<?php

use App\Jobs\AutopilotDiscoveryJob;
use App\Jobs\AutopilotGenerationJob;
use App\Jobs\AutopilotPublishJob;
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
