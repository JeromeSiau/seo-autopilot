<?php

namespace App\Console\Commands;

use App\Models\AiPrompt;
use App\Models\AiVisibilityAlert;
use App\Models\AiVisibilityCheck;
use App\Models\HostedExportRun;
use App\Models\SiteHosting;
use App\Models\WebhookDelivery;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Schema;

class SystemHealthCommand extends Command
{
    private const AGENT_HEARTBEAT_KEY = 'agents:process-events:last-seen';

    protected $signature = 'system:health
        {--max-agent-lag=15 : Maximum allowed lag in seconds for the agent bridge}
        {--max-queue-age=900 : Maximum age in seconds for pending queue jobs before they are considered stale}
        {--max-webhook-retry-lag=900 : Maximum lag in seconds for retrying webhooks before they are considered stale}
        {--max-hosting-pending-age=3600 : Maximum age in seconds for pending hosting states before they are considered stale}
        {--max-export-runtime=900 : Maximum runtime in seconds for hosted exports before they are considered stale}
        {--max-ai-visibility-age=86400 : Maximum age in seconds for AI visibility checks before a site is considered stale}
        {--json : Output JSON only}';
    protected $description = 'Aggregate the main operational health signals for SEO Autopilot';

    public function handle(): int
    {
        $payload = [
            'agent_events' => $this->agentEventsHealth(),
            'queues' => $this->queueHealth(),
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
                'due_retries' => 0,
                'stale_retries' => 0,
                'failed_last_24h' => 0,
            ];
        }

        $maxRetryLag = max(1, (int) $this->option('max-webhook-retry-lag'));
        $retrying = WebhookDelivery::query()->where('status', WebhookDelivery::STATUS_RETRYING)->count();
        $dueRetries = WebhookDelivery::query()
            ->where('status', WebhookDelivery::STATUS_RETRYING)
            ->whereNotNull('next_retry_at')
            ->where('next_retry_at', '<=', now())
            ->count();
        $staleRetries = WebhookDelivery::query()
            ->where('status', WebhookDelivery::STATUS_RETRYING)
            ->whereNotNull('next_retry_at')
            ->where('next_retry_at', '<=', now()->subSeconds($maxRetryLag))
            ->count();
        $failed = WebhookDelivery::query()
            ->where('status', WebhookDelivery::STATUS_FAILED)
            ->where('attempted_at', '>=', now()->subDay())
            ->count();

        return [
            'status' => $failed > 0 || $staleRetries > 0 ? 'critical' : (($dueRetries > 0 || $retrying > 0) ? 'warning' : 'ok'),
            'retrying_deliveries' => $retrying,
            'due_retries' => $dueRetries,
            'stale_retries' => $staleRetries,
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
                'stale_pending_sites' => 0,
                'recent_deploy_errors_last_24h' => 0,
            ];
        }

        $maxPendingAge = max(1, (int) $this->option('max-hosting-pending-age'));
        $withErrors = SiteHosting::query()->whereNotNull('last_error')->count();
        $sslPending = SiteHosting::query()->where('ssl_status', SiteHosting::SSL_STATUS_PENDING)->count();
        $domainPending = SiteHosting::query()->whereIn('domain_status', [
            SiteHosting::DOMAIN_STATUS_DNS_PENDING,
            SiteHosting::DOMAIN_STATUS_TENANT_PENDING,
            SiteHosting::DOMAIN_STATUS_SSL_PENDING,
        ])->count();
        $stalePending = SiteHosting::query()
            ->where(function ($query) {
                $query
                    ->where('ssl_status', SiteHosting::SSL_STATUS_PENDING)
                    ->orWhereIn('domain_status', [
                        SiteHosting::DOMAIN_STATUS_DNS_PENDING,
                        SiteHosting::DOMAIN_STATUS_TENANT_PENDING,
                        SiteHosting::DOMAIN_STATUS_SSL_PENDING,
                    ]);
            })
            ->where('updated_at', '<=', now()->subSeconds($maxPendingAge))
            ->count();
        $recentDeployErrors = Schema::hasTable('hosted_deploy_events')
            ? DB::table('hosted_deploy_events')
                ->where('status', 'error')
                ->where('occurred_at', '>=', now()->subDay())
                ->count()
            : 0;

        return [
            'status' => ($withErrors > 0 || $stalePending > 0 || $recentDeployErrors > 0)
                ? 'critical'
                : (($sslPending + $domainPending) > 0 ? 'warning' : 'ok'),
            'sites_with_errors' => $withErrors,
            'ssl_pending' => $sslPending,
            'domain_pending' => $domainPending,
            'stale_pending_sites' => $stalePending,
            'recent_deploy_errors_last_24h' => $recentDeployErrors,
        ];
    }

    private function exportHealth(): array
    {
        if (!Schema::hasTable('hosted_export_runs')) {
            return [
                'status' => 'warning',
                'running_exports' => 0,
                'stale_running_exports' => 0,
                'stale_pending_exports' => 0,
                'failed_last_24h' => 0,
            ];
        }

        $maxRuntime = max(1, (int) $this->option('max-export-runtime'));
        $running = HostedExportRun::query()->where('status', HostedExportRun::STATUS_RUNNING)->count();
        $staleRunning = HostedExportRun::query()
            ->where('status', HostedExportRun::STATUS_RUNNING)
            ->where(function ($query) use ($maxRuntime) {
                $query
                    ->whereNull('started_at')
                    ->orWhere('started_at', '<=', now()->subSeconds($maxRuntime));
            })
            ->count();
        $stalePending = HostedExportRun::query()
            ->where('status', HostedExportRun::STATUS_PENDING)
            ->where('created_at', '<=', now()->subSeconds($maxRuntime))
            ->count();
        $failed = HostedExportRun::query()
            ->where('status', HostedExportRun::STATUS_FAILED)
            ->where('created_at', '>=', now()->subDay())
            ->count();

        return [
            'status' => ($staleRunning > 0 || $stalePending > 0)
                ? 'critical'
                : ($failed > 0 || $running > 0 ? 'warning' : 'ok'),
            'running_exports' => $running,
            'stale_running_exports' => $staleRunning,
            'stale_pending_exports' => $stalePending,
            'failed_last_24h' => $failed,
        ];
    }

    private function aiVisibilityHealth(): array
    {
        if (!Schema::hasTable('ai_visibility_alerts') || !Schema::hasTable('ai_prompts') || !Schema::hasTable('ai_visibility_checks')) {
            return [
                'status' => 'warning',
                'open_high_risk_alerts' => 0,
                'active_prompt_sites' => 0,
                'stale_sites' => 0,
                'estimated_ai_overview_sites' => 0,
                'dataforseo_ai_overview_available' => false,
            ];
        }

        $maxAge = max(1, (int) $this->option('max-ai-visibility-age'));
        $openHighRisk = AiVisibilityAlert::query()
            ->where('status', AiVisibilityAlert::STATUS_OPEN)
            ->where('severity', 'high')
            ->count();
        $activePromptSiteIds = AiPrompt::query()
            ->where('is_active', true)
            ->pluck('site_id')
            ->unique()
            ->values();
        $staleSites = 0;
        $estimatedAiOverviewSites = 0;

        foreach ($activePromptSiteIds as $siteId) {
            $latestCheckedAt = AiVisibilityCheck::query()
                ->where('site_id', $siteId)
                ->max('checked_at');

            if (blank($latestCheckedAt) || now()->subSeconds($maxAge)->greaterThan(\Illuminate\Support\Carbon::parse($latestCheckedAt))) {
                $staleSites++;
            }

            $latestAiOverviewProvider = AiVisibilityCheck::query()
                ->where('site_id', $siteId)
                ->where('engine', AiVisibilityCheck::ENGINE_AI_OVERVIEWS)
                ->orderByDesc('checked_at')
                ->value('provider');

            if ($latestAiOverviewProvider !== null && $latestAiOverviewProvider !== 'dataforseo_ai_overview') {
                $estimatedAiOverviewSites++;
            }
        }
        $dataForSeoAvailable = filled(config('services.dataforseo.login'))
            && filled(config('services.dataforseo.password'));
        $sitesMissingRealProvider = $dataForSeoAvailable ? $estimatedAiOverviewSites : $activePromptSiteIds->count();

        return [
            'status' => ($openHighRisk > 0 || $staleSites > 0 || $sitesMissingRealProvider > 0) ? 'warning' : 'ok',
            'open_high_risk_alerts' => $openHighRisk,
            'active_prompt_sites' => $activePromptSiteIds->count(),
            'stale_sites' => $staleSites,
            'estimated_ai_overview_sites' => $estimatedAiOverviewSites,
            'sites_missing_real_ai_overview_provider' => $sitesMissingRealProvider,
            'dataforseo_ai_overview_available' => $dataForSeoAvailable,
        ];
    }

    private function queueHealth(): array
    {
        if (!Schema::hasTable('jobs') || !Schema::hasTable('failed_jobs')) {
            return [
                'status' => 'warning',
                'pending_jobs' => 0,
                'pending_by_queue' => [],
                'reserved_jobs' => 0,
                'oldest_pending_seconds' => null,
                'stale_pending_jobs' => 0,
                'failed_last_24h' => 0,
            ];
        }

        $maxQueueAge = max(1, (int) $this->option('max-queue-age'));
        $pendingJobs = DB::table('jobs')
            ->whereNull('reserved_at')
            ->count();
        $pendingByQueue = DB::table('jobs')
            ->select('queue', DB::raw('COUNT(*) as total'))
            ->whereNull('reserved_at')
            ->groupBy('queue')
            ->pluck('total', 'queue')
            ->map(fn ($total) => (int) $total)
            ->all();
        $reservedJobs = DB::table('jobs')
            ->whereNotNull('reserved_at')
            ->count();
        $oldestPendingCreatedAt = DB::table('jobs')
            ->whereNull('reserved_at')
            ->min('created_at');
        $oldestPendingSeconds = $oldestPendingCreatedAt
            ? max(0, time() - (int) $oldestPendingCreatedAt)
            : null;
        $stalePendingJobs = DB::table('jobs')
            ->whereNull('reserved_at')
            ->where('created_at', '<=', time() - $maxQueueAge)
            ->count();
        $failedLast24h = DB::table('failed_jobs')
            ->where('failed_at', '>=', now()->subDay())
            ->count();

        return [
            'status' => $failedLast24h > 0
                ? 'critical'
                : ($stalePendingJobs > 0 ? 'warning' : 'ok'),
            'pending_jobs' => $pendingJobs,
            'pending_by_queue' => $pendingByQueue,
            'reserved_jobs' => $reservedJobs,
            'oldest_pending_seconds' => $oldestPendingSeconds,
            'stale_pending_jobs' => $stalePendingJobs,
            'failed_last_24h' => $failedLast24h,
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
