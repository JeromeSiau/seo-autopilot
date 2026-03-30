<?php

namespace App\Console\Commands;

use App\Models\AiVisibilityAlert;
use App\Models\HostedExportRun;
use App\Models\SiteHosting;
use App\Models\WebhookDelivery;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Schema;

class SystemHealthCommand extends Command
{
    private const AGENT_HEARTBEAT_KEY = 'agents:process-events:last-seen';

    protected $signature = 'system:health {--max-agent-lag=15 : Maximum allowed lag in seconds for the agent bridge} {--json : Output JSON only}';
    protected $description = 'Aggregate the main operational health signals for SEO Autopilot';

    public function handle(): int
    {
        $payload = [
            'agent_events' => $this->agentEventsHealth(),
            'webhooks' => $this->webhookHealth(),
            'hosting' => $this->hostingHealth(),
            'exports' => $this->exportHealth(),
            'ai_visibility' => $this->aiVisibilityHealth(),
        ];
        $payload['status'] = $this->overallStatus($payload);

        if ($this->option('json')) {
            $this->line(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $this->line('status=' . $payload['status']);
            foreach ($payload as $section => $data) {
                if (!is_array($data)) {
                    continue;
                }

                $this->line('');
                $this->info($section);
                foreach ($data as $key => $value) {
                    $this->line("  {$key}=" . (is_array($value) ? json_encode($value) : var_export($value, true)));
                }
            }
        }

        return $payload['status'] === 'ok' ? self::SUCCESS : self::FAILURE;
    }

    private function agentEventsHealth(): array
    {
        $lastSeen = Cache::get(self::AGENT_HEARTBEAT_KEY);
        $lag = $lastSeen ? time() - (int) $lastSeen : null;
        $maxLag = (int) $this->option('max-agent-lag');

        try {
            $queueSize = (int) Redis::llen('agent-events-queue');
        } catch (\Throwable) {
            $queueSize = null;
        }

        return [
            'status' => $lastSeen !== null && $lag !== null && $lag <= $maxLag ? 'ok' : 'critical',
            'last_seen_at' => $lastSeen ? date(DATE_ATOM, (int) $lastSeen) : null,
            'lag_seconds' => $lag,
            'queue_size' => $queueSize,
        ];
    }

    private function webhookHealth(): array
    {
        if (!Schema::hasTable('webhook_deliveries')) {
            return [
                'status' => 'warning',
                'retrying_deliveries' => 0,
                'failed_last_24h' => 0,
            ];
        }

        $retrying = WebhookDelivery::query()->where('status', WebhookDelivery::STATUS_RETRYING)->count();
        $failed = WebhookDelivery::query()
            ->where('status', WebhookDelivery::STATUS_FAILED)
            ->where('attempted_at', '>=', now()->subDay())
            ->count();

        return [
            'status' => $failed > 0 ? 'critical' : ($retrying > 0 ? 'warning' : 'ok'),
            'retrying_deliveries' => $retrying,
            'failed_last_24h' => $failed,
        ];
    }

    private function hostingHealth(): array
    {
        if (!Schema::hasTable('site_hostings')) {
            return [
                'status' => 'warning',
                'sites_with_errors' => 0,
                'ssl_pending' => 0,
                'domain_pending' => 0,
            ];
        }

        $withErrors = SiteHosting::query()->whereNotNull('last_error')->count();
        $sslPending = SiteHosting::query()->where('ssl_status', SiteHosting::SSL_STATUS_PENDING)->count();
        $domainPending = SiteHosting::query()->whereIn('domain_status', [
            SiteHosting::DOMAIN_STATUS_DNS_PENDING,
            SiteHosting::DOMAIN_STATUS_TENANT_PENDING,
            SiteHosting::DOMAIN_STATUS_SSL_PENDING,
        ])->count();

        return [
            'status' => $withErrors > 0 ? 'critical' : (($sslPending + $domainPending) > 0 ? 'warning' : 'ok'),
            'sites_with_errors' => $withErrors,
            'ssl_pending' => $sslPending,
            'domain_pending' => $domainPending,
        ];
    }

    private function exportHealth(): array
    {
        if (!Schema::hasTable('hosted_export_runs')) {
            return [
                'status' => 'warning',
                'running_exports' => 0,
                'failed_last_24h' => 0,
            ];
        }

        $running = HostedExportRun::query()->where('status', HostedExportRun::STATUS_RUNNING)->count();
        $failed = HostedExportRun::query()
            ->where('status', HostedExportRun::STATUS_FAILED)
            ->where('created_at', '>=', now()->subDay())
            ->count();

        return [
            'status' => $failed > 0 ? 'warning' : 'ok',
            'running_exports' => $running,
            'failed_last_24h' => $failed,
        ];
    }

    private function aiVisibilityHealth(): array
    {
        if (!Schema::hasTable('ai_visibility_alerts')) {
            return [
                'status' => 'warning',
                'open_high_risk_alerts' => 0,
            ];
        }

        $openHighRisk = AiVisibilityAlert::query()
            ->where('status', AiVisibilityAlert::STATUS_OPEN)
            ->where('severity', 'high')
            ->count();

        return [
            'status' => $openHighRisk > 0 ? 'warning' : 'ok',
            'open_high_risk_alerts' => $openHighRisk,
        ];
    }

    private function overallStatus(array $payload): string
    {
        $statuses = collect($payload)
            ->filter(fn ($value) => is_array($value) && isset($value['status']))
            ->pluck('status');

        if ($statuses->contains('critical')) {
            return 'degraded';
        }

        if ($statuses->contains('warning')) {
            return 'attention';
        }

        return 'ok';
    }
}
