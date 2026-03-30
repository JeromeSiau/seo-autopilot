<?php

namespace Tests\Feature\Analytics;

use App\Models\Article;
use App\Models\ArticleAnalytic;
use App\Models\Keyword;
use App\Models\SiteAnalytic;
use App\Models\SiteSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\CreatesTeams;
use Tests\TestCase;

class BusinessAttributionTest extends TestCase
{
    use CreatesTeams;
    use RefreshDatabase;

    public function test_analytics_page_exposes_business_value_and_search_capture_metrics(): void
    {
        $user = $this->createUserWithTeam();
        $site = $this->createSiteForUser($user, [
            'name' => 'Acme',
            'domain' => 'acme.test',
        ]);

        SiteSetting::create([
            'site_id' => $site->id,
            'autopilot_enabled' => true,
            'articles_per_week' => 4,
            'publish_days' => ['mon', 'wed'],
            'auto_publish' => true,
            'modeled_conversion_rate' => 5.0,
            'average_conversion_value' => 200,
        ]);

        $keyword = Keyword::factory()->create([
            'site_id' => $site->id,
            'keyword' => 'industrial automation software',
            'cpc' => 4.0,
        ]);

        $article = Article::factory()->published()->create([
            'site_id' => $site->id,
            'keyword_id' => $keyword->id,
            'title' => 'Industrial Automation Software Guide',
            'generation_cost' => 50,
        ]);

        ArticleAnalytic::create([
            'article_id' => $article->id,
            'date' => now()->subDays(1),
            'clicks' => 10,
            'sessions' => 20,
            'page_views' => 25,
            'conversions' => 2,
            'impressions' => 120,
            'position' => 5.5,
            'ctr' => 8.3,
        ]);

        SiteAnalytic::create([
            'site_id' => $site->id,
            'date' => now()->subDays(1),
            'clicks' => 40,
            'impressions' => 1200,
            'position' => 7.1,
            'ctr' => 3.3,
        ]);

        $response = $this->actingAs($user)->get(route('analytics.index', ['site_id' => $site->id, 'range' => 28]));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Analytics/Index')
            ->where('selectedSite.id', $site->id)
            ->where('businessSummary.business_model.source', 'custom')
            ->where('businessSummary.business_model.modeled_conversion_rate', fn ($value) => (float) $value === 5.0)
            ->where('businessSummary.business_model.average_conversion_value', fn ($value) => (float) $value === 200.0)
            ->where('businessSummary.totals.attributed_revenue', fn ($value) => (float) $value === 400.0)
            ->where('businessSummary.totals.total_value', fn ($value) => (float) $value === 440.0)
            ->where('businessSummary.totals.net_value', fn ($value) => (float) $value === 390.0)
            ->where('businessSummary.totals.search_click_share', fn ($value) => (float) $value === 25.0)
            ->where('businessSummary.top_articles.0.article_id', $article->id)
        );
    }

    public function test_business_model_update_persists_site_assumptions(): void
    {
        $user = $this->createUserWithTeam();
        $site = $this->createSiteForUser($user);

        $response = $this->actingAs($user)->patch(route('analytics.business-model.update', $site), [
            'modeled_conversion_rate' => 3.5,
            'average_conversion_value' => 275,
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('site_settings', [
            'site_id' => $site->id,
            'modeled_conversion_rate' => 3.5,
            'average_conversion_value' => 275.0,
        ]);
    }
}
