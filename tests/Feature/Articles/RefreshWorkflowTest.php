<?php

namespace Tests\Feature\Articles;

use App\Models\Article;
use App\Models\ArticleAnalytic;
use App\Models\AiPrompt;
use App\Models\Keyword;
use App\Models\RefreshRecommendation;
use App\Services\Refresh\RefreshDetectionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\CreatesTeams;
use Tests\TestCase;

class RefreshWorkflowTest extends TestCase
{
    use CreatesTeams;
    use RefreshDatabase;

    public function test_refresh_detection_and_execution_are_exposed_in_article_show(): void
    {
        $user = $this->createUserWithTeam();
        $site = $this->createSiteForUser($user, [
            'name' => 'Acme',
            'domain' => 'acme.test',
        ]);

        $keyword = Keyword::factory()->create([
            'site_id' => $site->id,
            'keyword' => 'warehouse automation software',
        ]);

        $article = Article::factory()->published()->create([
            'site_id' => $site->id,
            'keyword_id' => $keyword->id,
            'title' => 'Warehouse Automation Software',
            'content' => '<h2>Warehouse automation software</h2><p>Old content that needs a refresh.</p>',
            'meta_title' => 'Warehouse Automation Software',
            'meta_description' => str_repeat('A', 140),
            'published_url' => 'https://acme.test/blog/warehouse-automation-software',
            'published_at' => now()->subDays(180),
        ]);

        $article->score()->create([
            'readiness_score' => 62,
            'brand_fit_score' => 60,
            'seo_score' => 64,
            'citation_score' => 45,
            'internal_link_score' => 40,
            'fact_confidence_score' => 70,
            'warnings' => ['Needs fresher sources'],
            'checklist' => [],
        ]);

        foreach (range(1, 7) as $dayOffset) {
            ArticleAnalytic::create([
                'article_id' => $article->id,
                'date' => now()->subDays($dayOffset),
                'clicks' => 4,
                'impressions' => 180,
                'sessions' => 18,
                'page_views' => 25,
                'conversions' => 1,
                'position' => 18,
                'ctr' => 2.2,
            ]);
        }

        foreach (range(8, 14) as $dayOffset) {
            ArticleAnalytic::create([
                'article_id' => $article->id,
                'date' => now()->subDays($dayOffset),
                'clicks' => 18,
                'impressions' => 220,
                'sessions' => 42,
                'page_views' => 58,
                'conversions' => 3,
                'position' => 9,
                'ctr' => 8.1,
            ]);
        }

        app(RefreshDetectionService::class)->detectForSite($site);

        $recommendation = RefreshRecommendation::query()->firstOrFail();

        $this->actingAs($user)
            ->post(route('refresh-recommendations.accept', $recommendation))
            ->assertRedirect();

        $this->actingAs($user)
            ->post(route('refresh-recommendations.execute', $recommendation))
            ->assertRedirect();

        $this->actingAs($user)
            ->post(route('refresh-recommendations.apply', $recommendation))
            ->assertRedirect();

        $this->assertDatabaseHas('refresh_recommendations', [
            'id' => $recommendation->id,
            'status' => RefreshRecommendation::STATUS_EXECUTED,
        ]);
        $this->assertDatabaseCount('article_refresh_runs', 1);
        $this->assertDatabaseHas('article_refresh_runs', [
            'refresh_recommendation_id' => $recommendation->id,
            'status' => 'review_ready',
        ]);
        $this->assertDatabaseHas('articles', [
            'id' => $article->id,
            'status' => Article::STATUS_REVIEW,
        ]);

        $response = $this->actingAs($user)->get(route('articles.show', $article));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Articles/Show')
            ->where('article.id', $article->id)
            ->where('article.refresh_recommendations.0.article_id', $article->id)
            ->where('article.latest_refresh_run.status', 'review_ready')
            ->where('article.business_attribution.totals.conversion_source', 'tracked')
            ->where('article.latest_refresh_run.metadata.business_case.trigger_type', fn ($value) => filled($value))
        );

        $plannerResponse = $this->actingAs($user)->get(route('articles.needs-refresh', [
            'site_id' => $site->id,
            'status' => 'executed',
        ]));

        $plannerResponse->assertInertia(fn (Assert $page) => $page
            ->component('Articles/NeedsRefresh')
            ->where('selectedSite.id', $site->id)
            ->where('refreshPlanner.summary.executed', 1)
            ->where('refreshPlanner.items.0.article_id', $article->id)
            ->where('refreshPlanner.items.0.next_action', 'review_draft')
            ->where('refreshPlanner.items.0.latest_run.status', 'review_ready')
            ->where('refreshPlanner.items.0.business_attribution.deltas.traffic_value.absolute', fn ($value) => is_numeric($value))
            ->where('refreshPlanner.items.0.latest_run.diff.word_delta', fn ($value) => is_numeric($value))
        );
    }

    public function test_ai_visibility_regressions_are_exposed_in_refresh_planner_context(): void
    {
        $user = $this->createUserWithTeam();
        $site = $this->createSiteForUser($user, [
            'name' => 'Acme',
            'domain' => 'acme.test',
            'topics' => ['warehouse automation'],
            'target_audience' => 'operations leaders',
        ]);

        $keyword = Keyword::factory()->create([
            'site_id' => $site->id,
            'keyword' => 'warehouse automation software',
            'score' => 86,
        ]);

        $article = Article::factory()->published()->create([
            'site_id' => $site->id,
            'keyword_id' => $keyword->id,
            'title' => 'Warehouse Automation Software Guide',
            'content' => '<h2>Warehouse automation software</h2><p>Evaluation criteria and deployment notes.</p>',
            'published_url' => 'https://acme.test/blog/warehouse-automation-software-guide',
            'published_at' => now()->subDays(30),
        ]);

        $article->score()->create([
            'readiness_score' => 82,
            'brand_fit_score' => 80,
            'seo_score' => 84,
            'citation_score' => 74,
            'internal_link_score' => 65,
            'fact_confidence_score' => 80,
            'warnings' => [],
            'checklist' => [],
        ]);

        $prompt = AiPrompt::create([
            'site_id' => $site->id,
            'prompt' => 'How should operations leaders evaluate warehouse automation software?',
            'topic' => 'warehouse automation software',
            'intent' => 'comparison',
            'priority' => 88,
            'locale' => 'en',
            'country' => null,
            'is_active' => true,
            'metadata' => [
                'source_type' => 'keyword',
                'source_label' => 'warehouse automation software',
            ],
            'last_generated_at' => now(),
        ]);

        $previousCheck = $prompt->checks()->create([
            'site_id' => $site->id,
            'engine' => 'chatgpt',
            'status' => 'completed',
            'visibility_score' => 74,
            'appears' => true,
            'rank_bucket' => 'strong',
            'raw_response' => [],
            'metadata' => [],
            'checked_at' => now()->subDay(),
        ]);
        $previousCheck->mentions()->create([
            'domain' => 'acme.test',
            'url' => $article->published_url,
            'brand_name' => 'Acme',
            'mention_type' => 'owned_domain',
            'position' => 1,
            'is_our_brand' => true,
        ]);

        $latestCheck = $prompt->checks()->create([
            'site_id' => $site->id,
            'engine' => 'chatgpt',
            'status' => 'completed',
            'visibility_score' => 38,
            'appears' => false,
            'rank_bucket' => null,
            'raw_response' => [],
            'metadata' => [],
            'checked_at' => now(),
        ]);
        $latestCheck->mentions()->create([
            'domain' => 'competitor.test',
            'url' => 'https://competitor.test/warehouse-automation',
            'brand_name' => 'Competitor',
            'mention_type' => 'competitor',
            'position' => 1,
            'is_our_brand' => false,
        ]);

        app(RefreshDetectionService::class)->detectForSite($site);

        $recommendation = RefreshRecommendation::query()
            ->where('article_id', $article->id)
            ->where('trigger_type', RefreshRecommendation::TRIGGER_AI_VISIBILITY_DROP)
            ->firstOrFail();

        $this->assertNotNull(data_get($recommendation->metrics_snapshot, 'ai_visibility.delta'));
        $this->assertEquals('competitor.test', data_get($recommendation->metrics_snapshot, 'ai_visibility.competitor_domains.0'));

        $plannerResponse = $this->actingAs($user)->get(route('articles.needs-refresh', [
            'site_id' => $site->id,
            'status' => 'active',
        ]));

        $plannerResponse->assertInertia(fn (Assert $page) => $page
            ->component('Articles/NeedsRefresh')
            ->where('selectedSite.id', $site->id)
            ->where('refreshPlanner.items', fn ($items) => collect($items)->contains(function ($item) {
                return ($item['trigger_type'] ?? null) === RefreshRecommendation::TRIGGER_AI_VISIBILITY_DROP
                    && is_numeric(data_get($item, 'ai_visibility.delta'))
                    && data_get($item, 'ai_visibility.competitor_domains.0') === 'competitor.test';
            }))
        );
    }
}
