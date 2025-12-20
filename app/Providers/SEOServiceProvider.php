<?php

namespace App\Providers;

use App\Services\Google\SearchConsoleService;
use App\Services\Keyword\KeywordDiscoveryService;
use App\Services\Keyword\KeywordScoringService;
use App\Services\LLM\LLMManager;
use App\Services\SEO\DataForSEOService;
use Illuminate\Support\ServiceProvider;

class SEOServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->singleton(DataForSEOService::class, function ($app) {
            return new DataForSEOService();
        });

        $this->app->singleton(KeywordScoringService::class, function ($app) {
            return new KeywordScoringService();
        });

        $this->app->singleton(KeywordDiscoveryService::class, function ($app) {
            return new KeywordDiscoveryService(
                $app->make(SearchConsoleService::class),
                $app->make(DataForSEOService::class),
                $app->make(KeywordScoringService::class),
                $app->make(LLMManager::class),
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
