<?php

namespace Tests\Feature\Keywords;

use App\Jobs\GenerateArticleJob;
use App\Models\CampaignRun;
use App\Models\Keyword;
use App\Models\WebhookDelivery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\CreatesTeams;
use Tests\TestCase;

class CampaignRunTest extends TestCase
{
    use CreatesTeams;
    use RefreshDatabase;

    public function test_bulk_generation_creates_campaign_run_and_dispatches_jobs(): void
    {
        Queue::fake([GenerateArticleJob::class]);
        Http::fake([
            'https://hooks.example.test/*' => Http::response(['ok' => true], 200),
        ]);

        $user = $this->createUserWithTeam();
        $site = $this->createSiteForUser($user);
        $user->currentTeam->webhookEndpoints()->create([
            'url' => 'https://hooks.example.test/campaigns',
            'events' => ['campaign.completed'],
            'secret' => 'shared-secret',
            'is_active' => true,
        ]);

        $keywords = Keyword::factory()
            ->count(2)
            ->create([
                'site_id' => $site->id,
                'status' => Keyword::STATUS_PENDING,
            ]);

        $this->actingAs($user)
            ->post(route('keywords.generate-bulk'), [
                'keyword_ids' => $keywords->pluck('id')->all(),
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('campaign_runs', [
            'site_id' => $site->id,
            'status' => CampaignRun::STATUS_DISPATCHED,
            'processed_count' => 2,
            'succeeded_count' => 2,
            'failed_count' => 0,
        ]);
        $this->assertDatabaseHas('webhook_deliveries', [
            'event_name' => 'campaign.completed',
            'status' => WebhookDelivery::STATUS_SUCCESS,
        ]);

        Queue::assertPushed(GenerateArticleJob::class, 2);

        $this->actingAs($user)
            ->get(route('campaigns.index', ['site_id' => $site->id]))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Campaigns/Index')
                ->where('selectedSite.id', $site->id)
                ->where('summary.dispatched', 1)
                ->where('campaigns.0.site_id', $site->id)
                ->where('campaigns.0.status', CampaignRun::STATUS_DISPATCHED)
            );
    }
}
