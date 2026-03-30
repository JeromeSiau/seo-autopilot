<?php

namespace Tests\Feature\Smoke;

use App\Models\Article;
use App\Models\CampaignRun;
use App\Models\HostedPage;
use App\Models\RefreshRecommendation;
use App\Models\Site;
use App\Models\SiteHosting;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use App\Services\AiVisibility\AiPromptService;
use App\Services\AiVisibility\AiVisibilityRunner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\CreatesTeams;
use Tests\TestCase;

class CriticalFlowsSmokeTest extends TestCase
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

    public function test_critical_product_surfaces_render_for_a_seeded_site(): void
    {
        $this->withoutVite();

        $user = $this->createUserWithTeam();
        $site = $this->createHostedSite($user);

        $published = Article::factory()->published()->create([
            'site_id' => $site->id,
            'title' => 'Warehouse Automation Guide',
            'content' => '<h2>Warehouse automation</h2><p>Practical implementation guide.</p>',
            'published_url' => 'https://preview.release-smoke.test/blog/warehouse-automation-guide',
        ]);
        $published->score()->create([
            'readiness_score' => 86,
            'brand_fit_score' => 84,
            'seo_score' => 88,
            'citation_score' => 79,
            'internal_link_score' => 74,
            'fact_confidence_score' => 82,
            'warnings' => [],
            'checklist' => [],
        ]);
        $published->citations()->create([
            'source_type' => 'brand',
            'title' => 'Automation proof',
            'url' => 'https://release-smoke.test/case-study',
            'domain' => 'release-smoke.test',
            'excerpt' => 'Downtime fell by 12 percent.',
            'metadata' => [],
        ]);

        $review = Article::factory()->create([
            'site_id' => $site->id,
            'title' => 'Review Me',
            'status' => Article::STATUS_REVIEW,
        ]);
        $review->score()->create([
            'readiness_score' => 80,
            'brand_fit_score' => 80,
            'seo_score' => 81,
            'citation_score' => 75,
            'internal_link_score' => 77,
            'fact_confidence_score' => 80,
            'warnings' => [],
            'checklist' => [],
        ]);

        $site->brandAssets()->create([
            'type' => 'proof',
            'title' => 'Operational proof',
            'content' => 'Warehouse automation improved throughput and reduced errors.',
            'priority' => 90,
            'is_active' => true,
        ]);

        app(AiPromptService::class)->syncForSite($site);
        app(AiVisibilityRunner::class)->runForSite($site);

        $site->refreshRecommendations()->create([
            'article_id' => $published->id,
            'trigger_type' => RefreshRecommendation::TRIGGER_AI_VISIBILITY_DROP,
            'severity' => 'high',
            'reason' => 'AI visibility dropped for a core topic.',
            'recommended_actions' => ['Add stronger FAQ coverage.'],
            'metrics_snapshot' => ['visibility_delta' => -18.5],
            'status' => RefreshRecommendation::STATUS_OPEN,
            'detected_at' => now(),
        ]);

        CampaignRun::create([
            'site_id' => $site->id,
            'created_by' => $user->id,
            'name' => 'Warehouse campaign',
            'status' => CampaignRun::STATUS_COMPLETED,
            'input_type' => 'keywords',
            'payload' => ['keywords' => ['warehouse automation software']],
            'processed_count' => 1,
            'succeeded_count' => 1,
            'failed_count' => 0,
            'started_at' => now()->subMinutes(5),
            'completed_at' => now()->subMinute(),
        ]);

        $endpoint = WebhookEndpoint::create([
            'team_id' => $user->currentTeam->id,
            'url' => 'https://hooks.example.test/release-smoke',
            'events' => ['article.approved', 'refresh.executed'],
            'secret' => 'release-smoke-secret',
            'is_active' => true,
        ]);

        WebhookDelivery::create([
            'webhook_endpoint_id' => $endpoint->id,
            'event_name' => 'refresh.executed',
            'payload' => ['event' => 'refresh.executed'],
            'status' => WebhookDelivery::STATUS_SUCCESS,
            'attempt_number' => 1,
            'max_attempts' => 3,
            'response_code' => 200,
            'attempted_at' => now(),
        ]);

        $this->actingAs($user)
            ->get(route('sites.hosting.show', $site))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Sites/Hosting')
                ->where('site.id', $site->id)
                ->where('site.hosting_health.overall_status', fn ($value) => in_array($value, ['healthy', 'warning', 'critical', 'neutral'], true))
            );

        $this->actingAs($user)
            ->get(route('analytics.ai-visibility.index', ['site_id' => $site->id]))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Analytics/AiVisibility')
                ->where('selectedSite.id', $site->id)
                ->where('aiVisibility.summary.total_prompts', fn ($value) => $value > 0)
            );

        $this->actingAs($user)
            ->get(route('articles.needs-refresh', ['site_id' => $site->id]))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Articles/NeedsRefresh')
                ->where('selectedSite.id', $site->id)
                ->where('refreshPlanner.summary.total', fn ($value) => $value >= 1)
            );

        $this->actingAs($user)
            ->get(route('articles.review-queue', ['site_id' => $site->id]))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Articles/ReviewQueue')
                ->where('stats.all', fn ($value) => $value >= 1)
            );

        $this->actingAs($user)
            ->get(route('campaigns.index', ['site_id' => $site->id]))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Campaigns/Index')
                ->where('selectedSite.id', $site->id)
                ->where('summary.total', 1)
            );

        $this->actingAs($user)
            ->get(route('settings.notifications'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Settings/Notifications')
                ->where('recentWebhookDeliveries.0.event_name', 'refresh.executed')
            );
    }

    private function createHostedSite($user): Site
    {
        $site = $this->createSiteForUser($user, [
            'mode' => Site::MODE_HOSTED,
            'name' => 'Release Smoke',
            'domain' => 'release-smoke.test',
            'language' => 'en',
            'business_description' => 'Smoke coverage site.',
            'topics' => ['warehouse automation'],
            'target_audience' => 'operations leaders',
        ]);

        $site->hosting()->create([
            'staging_domain' => 'preview.release-smoke.test',
            'canonical_domain' => 'preview.release-smoke.test',
            'domain_status' => SiteHosting::DOMAIN_STATUS_ACTIVE,
            'ssl_status' => SiteHosting::SSL_STATUS_ACTIVE,
            'template_key' => SiteHosting::TEMPLATE_EDITORIAL,
            'theme_settings' => [
                'brand_name' => 'Release Smoke',
            ],
        ]);

        $site->hostedPages()->create([
            'kind' => HostedPage::KIND_HOME,
            'slug' => 'home',
            'title' => 'Home',
            'navigation_label' => 'Home',
            'body_html' => '<p>Home body</p>',
            'meta_title' => 'Release Smoke',
            'meta_description' => 'Home',
            'show_in_navigation' => true,
            'show_in_sitemap' => true,
            'sort_order' => 0,
            'is_published' => true,
        ]);

        return $site->fresh(['hosting', 'hostedPages']);
    }
}
