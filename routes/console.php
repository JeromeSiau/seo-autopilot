<?php

use App\Jobs\SyncSiteAnalyticsJob;
use App\Models\Site;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

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
