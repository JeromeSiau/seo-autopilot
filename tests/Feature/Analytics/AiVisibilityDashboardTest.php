<?php

namespace Tests\Feature\Analytics;

use App\Models\Article;
use App\Models\ArticleAnalytic;
use App\Models\Keyword;
use App\Services\AiVisibility\AiPromptService;
use App\Services\AiVisibility\AiVisibilityRunner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\CreatesTeams;
use Tests\TestCase;

class AiVisibilityDashboardTest extends TestCase
{
    use CreatesTeams;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.dataforseo.login' => null,
            'services.dataforseo.password' => null,
        ]);
    }

    public function test_analytics_page_exposes_ai_visibility_payload_for_selected_site(): void
    {
        $user = $this->createUserWithTeam();
        $site = $this->createSiteForUser($user, [
            'name' => 'Acme',
            'domain' => 'acme.test',
            'topics' => ['industrial automation'],
            'target_audience' => 'operations leaders',
        ]);

        $keyword = Keyword::factory()->create([
            'site_id' => $site->id,
            'keyword' => 'industrial automation software',
            'score' => 88,
        ]);

        $article = Article::factory()->published()->create([
            'site_id' => $site->id,
            'keyword_id' => $keyword->id,
            'title' => 'Industrial Automation Software Guide',
            'content' => '<h2>Industrial automation software</h2><p>How to evaluate vendors and deployment models.</p>',
            'published_url' => 'https://acme.test/blog/industrial-automation-software-guide',
        ]);

        $article->score()->create([
            'readiness_score' => 84,
            'brand_fit_score' => 80,
            'seo_score' => 86,
            'citation_score' => 78,
            'internal_link_score' => 70,
            'fact_confidence_score' => 82,
            'warnings' => [],
            'checklist' => [],
        ]);

        $article->citations()->create([
            'source_type' => 'brand',
            'title' => 'Proof point',
            'url' => 'https://acme.test/case-study',
            'domain' => 'acme.test',
            'excerpt' => 'Downtime fell by 18 percent.',
            'metadata' => [],
        ]);

        ArticleAnalytic::create([
            'article_id' => $article->id,
            'date' => now()->subDay(),
            'clicks' => 24,
            'impressions' => 320,
            'sessions' => 48,
            'page_views' => 67,
            'conversions' => 3,
            'position' => 8.2,
            'ctr' => 7.5,
        ]);
        $article->citations()->create([
            'source_type' => 'serp',
            'title' => 'Competitor benchmark',
            'url' => 'https://competitor.test/benchmark',
            'domain' => 'competitor.test',
            'excerpt' => 'External benchmark source.',
            'metadata' => [],
        ]);

        $site->brandAssets()->create([
            'type' => 'proof',
            'title' => 'Automation case study',
            'content' => 'Industrial automation rollout reduced downtime and improved throughput.',
            'priority' => 90,
            'is_active' => true,
        ]);

        app(AiPromptService::class)->syncForSite($site);
        app(AiVisibilityRunner::class)->runForSite($site);

        $response = $this->actingAs($user)->get(route('analytics.index', ['site_id' => $site->id]));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Analytics/Index')
            ->where('selectedSite.id', $site->id)
            ->where('aiVisibility.summary.total_prompts', fn ($value) => $value > 0)
            ->where('aiVisibility.top_prompts.0.engine', fn ($value) => in_array($value, ['ai_overviews', 'chatgpt', 'perplexity', 'gemini'], true))
            ->where('businessSummary.totals.estimated_conversions', fn ($value) => $value >= 3)
            ->where('businessSummary.top_articles.0.article_id', $article->id)
        );
    }

    public function test_ai_visibility_page_exposes_richer_payload_for_selected_site(): void
    {
        $user = $this->createUserWithTeam();
        $site = $this->createSiteForUser($user, [
            'name' => 'Acme',
            'domain' => 'acme.test',
            'topics' => ['industrial automation'],
            'target_audience' => 'operations leaders',
        ]);

        $keyword = Keyword::factory()->create([
            'site_id' => $site->id,
            'keyword' => 'industrial automation software',
            'score' => 88,
        ]);

        $article = Article::factory()->published()->create([
            'site_id' => $site->id,
            'keyword_id' => $keyword->id,
            'title' => 'Industrial Automation Software Guide',
            'content' => '<h2>Industrial automation software</h2><p>How to evaluate vendors and deployment models.</p>',
            'published_url' => 'https://acme.test/blog/industrial-automation-software-guide',
        ]);

        $article->score()->create([
            'readiness_score' => 84,
            'brand_fit_score' => 80,
            'seo_score' => 86,
            'citation_score' => 78,
            'internal_link_score' => 70,
            'fact_confidence_score' => 82,
            'warnings' => [],
            'checklist' => [],
        ]);

        $article->citations()->create([
            'source_type' => 'serp',
            'title' => 'Competitor benchmark',
            'url' => 'https://competitor.test/benchmark',
            'domain' => 'competitor.test',
            'excerpt' => 'External benchmark source.',
            'metadata' => [],
        ]);

        $site->brandAssets()->create([
            'type' => 'proof',
            'title' => 'Automation case study',
            'source_url' => 'https://acme.test/case-study',
            'content' => 'Industrial automation rollout reduced downtime and improved throughput.',
            'priority' => 90,
            'is_active' => true,
        ]);

        collect([
            ['url' => 'https://acme.test/solutions/industrial-automation', 'title' => 'Industrial automation solutions'],
            ['url' => 'https://acme.test/guide/industrial-automation-checklist', 'title' => 'Industrial automation checklist'],
            ['url' => 'https://acme.test/blog/industrial-automation-platforms', 'title' => 'Industrial automation platforms'],
        ])->each(fn (array $page) => $site->pages()->create($page + ['source' => 'sitemap']));

        $site->hostedPages()->create([
            'kind' => 'custom',
            'slug' => 'industrial-automation-playbook',
            'title' => 'Industrial automation playbook',
            'body_html' => '<p>Industrial automation software planning guide.</p>',
            'is_published' => true,
        ]);

        app(AiPromptService::class)->syncForSite($site);

        $this->travelTo(now()->subDay());
        app(AiVisibilityRunner::class)->runForSite($site);

        $article->update([
            'status' => Article::STATUS_DRAFT,
            'content' => '<p>General overview.</p>',
        ]);
        $article->citations()->delete();
        $site->brandAssets()->update(['is_active' => false]);
        $site->pages()->delete();
        $site->hostedPages()->update(['is_published' => false]);

        $this->travelBack();
        app(AiVisibilityRunner::class)->runForSite($site);

        $response = $this->actingAs($user)->get(route('analytics.ai-visibility.index', ['site_id' => $site->id]));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Analytics/AiVisibility')
            ->where('selectedSite.id', $site->id)
            ->where('aiVisibility.summary.total_prompts', fn ($value) => $value > 0)
            ->where('aiVisibility.summary.avg_visibility_delta', fn ($value) => is_numeric($value))
            ->where('aiVisibility.summary.high_risk_prompts', fn ($value) => $value > 0)
            ->where('aiVisibility.trend.0.avg_visibility_score', fn ($value) => is_numeric($value))
            ->where('aiVisibility.weakest_prompts.0.engine', fn ($value) => in_array($value, ['ai_overviews', 'chatgpt', 'perplexity', 'gemini'], true))
            ->where('aiVisibility.alerts.0.severity', fn ($value) => in_array($value, ['high', 'medium', 'low'], true))
            ->where('aiVisibility.movers.0.visibility_delta', fn ($value) => is_numeric($value))
            ->where('aiVisibility.recommendations.0.severity', fn ($value) => in_array($value, ['high', 'medium', 'low'], true))
            ->where('aiVisibility.sources.0.source_domain', fn ($value) => filled($value))
            ->where('aiVisibility.prompt_sets.0.name', 'Core coverage')
            ->where('aiVisibility.alert_history.0.status', fn ($value) => in_array($value, ['open', 'resolved'], true))
        );
    }
}
