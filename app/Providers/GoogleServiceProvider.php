<?php

namespace App\Providers;

use App\Services\Google\GA4Service;
use App\Services\Google\GoogleAuthService;
use App\Services\Google\SearchConsoleService;
use Illuminate\Support\ServiceProvider;

class GoogleServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->singleton(GoogleAuthService::class, function ($app) {
            return new GoogleAuthService();
        });

        $this->app->singleton(SearchConsoleService::class, function ($app) {
            return new SearchConsoleService(
                $app->make(GoogleAuthService::class)
            );
        });

        $this->app->singleton(GA4Service::class, function ($app) {
            return new GA4Service(
                $app->make(GoogleAuthService::class)
            );
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
