<?php

namespace App\Providers;

use App\Models\Article;
use App\Models\Integration;
use App\Models\Keyword;
use App\Models\Site;
use App\Policies\ArticlePolicy;
use App\Policies\IntegrationPolicy;
use App\Policies\KeywordPolicy;
use App\Policies\SitePolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);

        Gate::policy(Article::class, ArticlePolicy::class);
        Gate::policy(Integration::class, IntegrationPolicy::class);
        Gate::policy(Keyword::class, KeywordPolicy::class);
        Gate::policy(Site::class, SitePolicy::class);
    }
}
