<?php

namespace App\Providers;

use App\Services\Content\ArticleGenerator;
use App\Services\LLM\LLMManager;
use Illuminate\Support\ServiceProvider;

class ContentServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->singleton(LLMManager::class, function ($app) {
            return new LLMManager();
        });

        $this->app->singleton(ArticleGenerator::class, function ($app) {
            return new ArticleGenerator(
                $app->make(LLMManager::class)
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
