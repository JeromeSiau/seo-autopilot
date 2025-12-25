<?php

namespace Tests\Feature;

use App\Jobs\SiteIndexJob;
use App\Models\Site;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SiteIndexerTest extends TestCase
{
    use RefreshDatabase;

    public function test_site_index_job_is_queued_at_onboarding(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $team = Team::factory()->create(['owner_id' => $user->id]);
        $site = Site::factory()->create([
            'team_id' => $team->id,
            'domain' => 'example.com',
        ]);

        // Simulate onboarding trigger
        SiteIndexJob::dispatch($site, delta: false)->onQueue('crawl');

        Queue::assertPushedOn('crawl', SiteIndexJob::class, function ($job) use ($site) {
            return $job->site->id === $site->id && $job->delta === false;
        });
    }

    public function test_weekly_crawl_uses_delta_mode(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $team = Team::factory()->create(['owner_id' => $user->id]);
        $site = Site::factory()->create([
            'team_id' => $team->id,
            'domain' => 'example.com',
            'last_crawled_at' => now()->subDays(7),
        ]);

        // Simulate weekly trigger
        SiteIndexJob::dispatch($site, delta: true)->onQueue('crawl');

        Queue::assertPushedOn('crawl', SiteIndexJob::class, function ($job) {
            return $job->delta === true;
        });
    }
}
