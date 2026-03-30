<?php

namespace Tests\Feature\Hosted;

use App\Models\HostedPage;
use App\Models\Site;
use App\Models\SiteHosting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\CreatesTeams;
use Tests\TestCase;

class HostedOpsCenterTest extends TestCase
{
    use CreatesTeams;
    use RefreshDatabase;

    public function test_hosting_page_exposes_health_export_and_event_history(): void
    {
        $this->withoutVite();

        $user = $this->createUserWithTeam();
        $site = $this->createHostedSite($user);
        $hosting = $site->hosting;

        $hosting->exportRuns()->create([
            'status' => 'completed',
            'target_path' => storage_path('app/exports/sites/site-' . $site->id . '.zip'),
            'size_bytes' => 2048,
            'started_at' => now()->subMinutes(2),
            'completed_at' => now()->subMinute(),
        ]);

        $hosting->deployEvents()->create([
            'type' => 'export_completed',
            'status' => 'success',
            'title' => 'ZIP export completed',
            'message' => 'Hosted export is ready to download.',
            'occurred_at' => now()->subMinute(),
        ]);

        $this->actingAs($user)
            ->get(route('sites.hosting.show', $site))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Sites/Hosting')
                ->where('site.hosting_health.overall_status', 'healthy')
                ->has('site.hosting_health.checks', 4)
                ->has('site.hosted_export_runs', 1)
                ->has('site.hosted_deploy_events', 1)
                ->where('site.hosted_export_runs.0.status', 'completed')
                ->where('site.hosted_deploy_events.0.type', 'export_completed')
            );
    }

    private function createHostedSite($user): Site
    {
        $site = $this->createSiteForUser($user, [
            'mode' => Site::MODE_HOSTED,
            'name' => 'Ops Site',
            'domain' => 'ops.test',
            'language' => 'en',
            'business_description' => 'Hosted operations test site.',
        ]);

        $site->hosting()->create([
            'staging_domain' => 'preview.ops.test',
            'canonical_domain' => 'preview.ops.test',
            'domain_status' => SiteHosting::DOMAIN_STATUS_ACTIVE,
            'ssl_status' => SiteHosting::SSL_STATUS_ACTIVE,
            'template_key' => SiteHosting::TEMPLATE_EDITORIAL,
            'theme_settings' => [
                'brand_name' => 'Ops Site',
            ],
        ]);

        $site->hostedPages()->createMany([
            [
                'kind' => HostedPage::KIND_HOME,
                'slug' => 'home',
                'title' => 'Home',
                'navigation_label' => 'Home',
                'body_html' => '<p>Home body</p>',
                'meta_title' => 'Ops Site',
                'meta_description' => 'Home',
                'show_in_navigation' => true,
                'sort_order' => 0,
                'is_published' => true,
            ],
            [
                'kind' => HostedPage::KIND_ABOUT,
                'slug' => 'about',
                'title' => 'About',
                'navigation_label' => 'About',
                'body_html' => '<p>About body</p>',
                'meta_title' => 'About',
                'meta_description' => 'About',
                'show_in_navigation' => true,
                'sort_order' => 200,
                'is_published' => true,
            ],
        ]);

        return $site->fresh(['hosting', 'hostedPages']);
    }
}
