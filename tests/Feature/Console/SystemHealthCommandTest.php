<?php

namespace Tests\Feature\Console;

use App\Models\AiPrompt;
use App\Models\AiVisibilityAlert;
use App\Models\AiVisibilityCheck;
use App\Models\HostedExportRun;
use App\Models\SiteHosting;
use App\Models\WebhookDelivery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreatesTeams;
use Tests\TestCase;

class SystemHealthCommandTest extends TestCase
{
    use CreatesTeams;
    use RefreshDatabase;

    public function test_system_health_reports_degraded_state_when_critical_signals_exist(): void
    {
        Cache::put('agents:process-events:last-seen', time());

        $user = $this->createUserWithTeam();
        $site = $this->createSiteForUser($user, [
            'name' => 'Acme',
            'domain' => 'acme.test',
        ]);

        $hosting = SiteHosting::create([
            'site_id' => $site->id,
            'staging_domain' => 'preview.acme.test',
            'canonical_domain' => 'preview.acme.test',
            'domain_status' => SiteHosting::DOMAIN_STATUS_ERROR,
            'ssl_status' => SiteHosting::SSL_STATUS_ERROR,
            'template_key' => SiteHosting::TEMPLATE_EDITORIAL,
            'theme_settings' => [],
            'last_error' => 'Provisioning failed',
        ]);

        HostedExportRun::create([
            'site_hosting_id' => $hosting->id,
            'status' => HostedExportRun::STATUS_FAILED,
            'error_message' => 'ZIP packaging failed',
        ]);

        $endpoint = $user->currentTeam->webhookEndpoints()->create([
            'url' => 'https://example.com/webhook',
            'events' => ['refresh.executed'],
            'secret' => 'secret',
            'is_active' => true,
        ]);

        WebhookDelivery::create([
            'webhook_endpoint_id' => $endpoint->id,
            'event_name' => 'refresh.executed',
            'payload' => [],
            'status' => WebhookDelivery::STATUS_FAILED,
            'attempt_number' => 3,
            'max_attempts' => 3,
            'error_message' => 'Timeout',
            'attempted_at' => now(),
        ]);

        AiVisibilityAlert::create([
            'site_id' => $site->id,
            'fingerprint' => 'high-risk-test',
            'type' => 'coverage_drop',
            'severity' => 'high',
            'title' => 'Coverage drop',
            'reason' => 'Prompt coverage fell sharply.',
            'status' => AiVisibilityAlert::STATUS_OPEN,
            'related_domains' => ['competitor.test'],
            'first_detected_at' => now()->subHour(),
            'last_detected_at' => now(),
        ]);

        $exitCode = Artisan::call('system:health', ['--json' => true]);
        $output = Artisan::output();

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('"status": "degraded"', $output);
        $this->assertStringContainsString('"sites_with_errors"', $output);
        $this->assertStringContainsString('"failed_last_24h"', $output);
    }

    public function test_system_health_reports_deep_operational_signals(): void
    {
        Cache::put('agents:process-events:last-seen', time());

        config([
            'services.dataforseo.login' => null,
            'services.dataforseo.password' => null,
        ]);

        $user = $this->createUserWithTeam();
        $site = $this->createSiteForUser($user, [
            'name' => 'Beta',
            'domain' => 'beta.test',
        ]);

        $hosting = SiteHosting::create([
            'site_id' => $site->id,
            'staging_domain' => 'preview.beta.test',
            'canonical_domain' => 'preview.beta.test',
            'domain_status' => SiteHosting::DOMAIN_STATUS_DNS_PENDING,
            'ssl_status' => SiteHosting::SSL_STATUS_PENDING,
            'template_key' => SiteHosting::TEMPLATE_EDITORIAL,
            'theme_settings' => [],
            'last_error' => null,
        ]);
        $hosting->forceFill([
            'created_at' => now()->subDay(),
            'updated_at' => now()->subHours(3),
        ])->saveQuietly();

        $hosting->deployEvents()->create([
            'type' => 'dns_verification_pending',
            'status' => 'error',
            'title' => 'DNS verification failed',
            'message' => 'Records do not match.',
            'occurred_at' => now()->subHour(),
        ]);

        HostedExportRun::create([
            'site_hosting_id' => $hosting->id,
            'status' => HostedExportRun::STATUS_RUNNING,
            'target_path' => storage_path('app/exports/sites/site-' . $site->id . '.zip'),
            'started_at' => now()->subHours(2),
            'created_at' => now()->subHours(2),
        ]);

        $endpoint = $user->currentTeam->webhookEndpoints()->create([
            'url' => 'https://example.com/webhook',
            'events' => ['ai_visibility.changed'],
            'secret' => 'secret',
            'is_active' => true,
        ]);

        WebhookDelivery::create([
            'webhook_endpoint_id' => $endpoint->id,
            'event_name' => 'ai_visibility.changed',
            'payload' => [],
            'status' => WebhookDelivery::STATUS_RETRYING,
            'attempt_number' => 2,
            'max_attempts' => 3,
            'next_retry_at' => now()->subHour(),
            'attempted_at' => now()->subHours(2),
        ]);

        DB::table('jobs')->insert([
            'queue' => 'default',
            'payload' => json_encode(['displayName' => 'App\\Jobs\\DemoJob']),
            'attempts' => 0,
            'reserved_at' => null,
            'available_at' => time() - 3600,
            'created_at' => time() - 3600,
        ]);

        $prompt = AiPrompt::create([
            'site_id' => $site->id,
            'prompt' => 'beta seo automation',
            'topic' => 'beta seo automation',
            'intent' => 'informational',
            'priority' => 80,
            'locale' => 'en',
            'is_active' => true,
        ]);

        AiVisibilityCheck::create([
            'site_id' => $site->id,
            'ai_prompt_id' => $prompt->id,
            'engine' => AiVisibilityCheck::ENGINE_AI_OVERVIEWS,
            'provider' => 'estimated',
            'status' => 'completed',
            'visibility_score' => 42,
            'appears' => false,
            'rank_bucket' => 'low',
            'raw_response' => [],
            'metadata' => [],
            'checked_at' => now()->subDays(2),
        ]);

        $exitCode = Artisan::call('system:health', ['--json' => true]);
        $payload = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame(1, $exitCode);
        $this->assertSame('degraded', $payload['status']);
        $this->assertSame(1, $payload['queues']['stale_pending_jobs']);
        $this->assertSame(1, $payload['webhooks']['stale_retries']);
        $this->assertSame(1, $payload['hosting']['stale_pending_sites']);
        $this->assertSame(1, $payload['hosting']['recent_deploy_errors_last_24h']);
        $this->assertSame(1, $payload['exports']['stale_running_exports']);
        $this->assertSame(1, $payload['ai_visibility']['stale_sites']);
        $this->assertFalse($payload['ai_visibility']['dataforseo_ai_overview_available']);
        $this->assertSame(1, $payload['ai_visibility']['sites_missing_real_ai_overview_provider']);
    }
}
