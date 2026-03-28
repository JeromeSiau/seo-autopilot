<?php

namespace Tests\Feature\Dashboard;

use App\Models\Article;
use App\Models\ContentPlanGeneration;
use App\Models\SiteSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\CreatesTeams;
use Tests\TestCase;

class DashboardSitePayloadTest extends TestCase
{
    use CreatesTeams;
    use RefreshDatabase;

    public function test_dashboard_exposes_the_canonical_site_payload(): void
    {
        $user = $this->createUserWithTeam();
        $site = $this->createSiteForUser($user, [
            'onboarding_completed_at' => now(),
        ]);

        SiteSetting::create([
            'site_id' => $site->id,
            'autopilot_enabled' => true,
            'articles_per_week' => 4,
            'publish_days' => ['mon', 'wed'],
            'auto_publish' => true,
        ]);

        ContentPlanGeneration::create([
            'site_id' => $site->id,
            'status' => 'running',
            'current_step' => 1,
            'total_steps' => 3,
            'steps' => [],
        ]);

        Article::factory()->create([
            'site_id' => $site->id,
            'status' => Article::STATUS_REVIEW,
            'created_at' => now(),
        ]);

        Article::factory()->create([
            'site_id' => $site->id,
            'status' => Article::STATUS_PUBLISHED,
            'created_at' => now(),
        ]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('sites.0.autopilot_status', 'active')
            ->where('sites.0.onboarding_complete', true)
            ->where('sites.0.articles_per_week', 4)
            ->where('sites.0.articles_this_week', 2)
            ->where('sites.0.articles_in_review', 1)
            ->where('sites.0.is_generating', true)
        );
    }
}
