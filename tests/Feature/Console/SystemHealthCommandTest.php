<?php

namespace Tests\Feature\Console;

use App\Models\AiVisibilityAlert;
use App\Models\HostedExportRun;
use App\Models\SiteHosting;
use App\Models\WebhookDelivery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
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
}
