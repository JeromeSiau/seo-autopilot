<?php

namespace Tests\Feature\Settings;

use App\Models\Article;
use App\Models\Keyword;
use App\Models\Notification;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\CreatesTeams;
use Tests\TestCase;

class WebhookEndpointManagementTest extends TestCase
{
    use CreatesTeams;
    use RefreshDatabase;

    public function test_notifications_page_exposes_webhook_configuration(): void
    {
        $this->withoutVite();

        $user = $this->createUserWithTeam();

        $response = $this->actingAs($user)->get(route('settings.notifications'));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Settings/Notifications')
            ->where('canManageWebhooks', true)
            ->has('availableWebhookEvents')
            ->where('availableWebhookEvents.3', 'article.published')
            ->where('availableWebhookEvents.4', 'approval.requested')
            ->where('availableWebhookEvents', fn ($events) => collect($events)->contains('refresh.ready_for_review'))
            ->has('availableWebhookEvents', 12)
        );
    }

    public function test_owner_can_manage_and_trigger_webhook_endpoint(): void
    {
        $this->withoutVite();

        config()->set('queue.default', 'sync');

        Http::fake([
            'https://hooks.example.test/*' => Http::response(['ok' => true], 200),
        ]);

        $user = $this->createUserWithTeam();
        $site = $this->createSiteForUser($user);
        $article = Article::factory()->create([
            'site_id' => $site->id,
            'status' => Article::STATUS_REVIEW,
        ]);

        $this->actingAs($user)
            ->post(route('settings.webhooks.store'), [
                'url' => 'https://hooks.example.test/seo-autopilot',
                'events' => ['article.approved', 'article.published'],
                'secret' => 'shared-secret',
                'is_active' => true,
            ])
            ->assertRedirect();

        $endpoint = $user->currentTeam->webhookEndpoints()->firstOrFail();

        $this->actingAs($user)
            ->patch(route('settings.webhooks.update', $endpoint), [
                'url' => 'https://hooks.example.test/seo-autopilot-updated',
                'events' => ['article.approved'],
                'secret' => '',
                'is_active' => true,
            ])
            ->assertRedirect();

        $this->actingAs($user)
            ->post(route('settings.webhooks.test', $endpoint))
            ->assertRedirect();

        $this->actingAs($user)
            ->post(route('articles.approve', $article))
            ->assertRedirect();

        $this->assertDatabaseHas('webhook_endpoints', [
            'id' => $endpoint->id,
            'team_id' => $user->currentTeam->id,
            'url' => 'https://hooks.example.test/seo-autopilot-updated',
        ]);
        $this->assertDatabaseHas('webhook_deliveries', [
            'event_name' => 'webhook.test',
            'status' => WebhookDelivery::STATUS_SUCCESS,
            'attempt_number' => 1,
            'max_attempts' => 3,
        ]);
        $this->assertDatabaseHas('webhook_deliveries', [
            'event_name' => 'article.approved',
            'status' => WebhookDelivery::STATUS_SUCCESS,
            'attempt_number' => 1,
            'max_attempts' => 3,
        ]);

        Http::assertSentCount(2);
    }

    public function test_failed_test_delivery_records_retry_metadata_and_redirects_with_error(): void
    {
        $this->withoutVite();

        config()->set('queue.default', 'sync');

        Http::fake([
            'https://hooks.example.test/*' => Http::response(['error' => 'downstream failure'], 500),
        ]);

        $user = $this->createUserWithTeam();
        $endpoint = WebhookEndpoint::create([
            'team_id' => $user->currentTeam->id,
            'url' => 'https://hooks.example.test/seo-autopilot',
            'events' => ['article.approved'],
            'secret' => 'shared-secret',
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->post(route('settings.webhooks.test', $endpoint))
            ->assertRedirect()
            ->assertSessionHas('error');

        $delivery = WebhookDelivery::query()->latest()->firstOrFail();

        $this->assertSame('webhook.test', $delivery->event_name);
        $this->assertSame(WebhookDelivery::STATUS_RETRYING, $delivery->status);
        $this->assertSame(1, $delivery->attempt_number);
        $this->assertSame(3, $delivery->max_attempts);
        $this->assertSame(500, $delivery->response_code);
        $this->assertNotNull($delivery->next_retry_at);
    }

    public function test_notifications_page_exposes_recent_delivery_retry_metadata(): void
    {
        $this->withoutVite();

        $user = $this->createUserWithTeam();
        $endpoint = WebhookEndpoint::create([
            'team_id' => $user->currentTeam->id,
            'url' => 'https://hooks.example.test/seo-autopilot',
            'events' => ['article.approved'],
            'secret' => 'shared-secret',
            'is_active' => true,
        ]);

        WebhookDelivery::create([
            'webhook_endpoint_id' => $endpoint->id,
            'event_name' => 'article.approved',
            'payload' => ['event' => 'article.approved'],
            'status' => WebhookDelivery::STATUS_RETRYING,
            'attempt_number' => 2,
            'max_attempts' => 3,
            'response_code' => 500,
            'error_message' => 'HTTP 500',
            'attempted_at' => now(),
            'next_retry_at' => now()->addMinute(),
        ]);

        $response = $this->actingAs($user)->get(route('settings.notifications'));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Settings/Notifications')
            ->has('recentWebhookDeliveries', 1)
            ->where('recentWebhookDeliveries.0.endpoint_url', 'https://hooks.example.test/seo-autopilot')
            ->where('recentWebhookDeliveries.0.status', WebhookDelivery::STATUS_RETRYING)
            ->where('recentWebhookDeliveries.0.attempt_number', 2)
            ->where('recentWebhookDeliveries.0.max_attempts', 3)
        );
    }

    public function test_ai_visibility_sync_dispatches_actionable_webhook_payload_and_notification(): void
    {
        $this->withoutVite();

        config()->set('queue.default', 'sync');

        Http::fake([
            'https://hooks.example.test/*' => Http::response(['ok' => true], 200),
        ]);

        $user = $this->createUserWithTeam();
        $site = $this->createSiteForUser($user, [
            'name' => 'Acme',
            'domain' => 'acme.test',
            'topics' => ['warehouse automation'],
            'target_audience' => 'operations leaders',
        ]);

        Keyword::factory()->create([
            'site_id' => $site->id,
            'keyword' => 'warehouse automation software',
            'score' => 84,
        ]);

        $endpoint = WebhookEndpoint::create([
            'team_id' => $user->currentTeam->id,
            'url' => 'https://hooks.example.test/seo-autopilot',
            'events' => ['ai_visibility.changed'],
            'secret' => 'shared-secret',
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->post(route('analytics.ai-visibility.sync', $site))
            ->assertRedirect();

        $this->assertDatabaseHas('webhook_deliveries', [
            'webhook_endpoint_id' => $endpoint->id,
            'event_name' => 'ai_visibility.changed',
            'status' => WebhookDelivery::STATUS_SUCCESS,
        ]);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $user->id,
            'site_id' => $site->id,
            'type' => Notification::TYPE_AI_VISIBILITY_ALERT,
        ]);

        Http::assertSent(function ($request) {
            $data = $request['data'] ?? [];

            return $request->url() === 'https://hooks.example.test/seo-autopilot'
                && ($request['event'] ?? null) === 'ai_visibility.changed'
                && is_array($data['summary'] ?? null)
                && (int) ($data['summary']['high_risk_prompts'] ?? 0) > 0
                && !empty($data['alerts'])
                && !empty($data['recommendations']);
        });
    }
}
