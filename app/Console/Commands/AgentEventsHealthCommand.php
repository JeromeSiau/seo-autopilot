<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;

class AgentEventsHealthCommand extends Command
{
    private const HEARTBEAT_KEY = 'agents:process-events:last-seen';

    protected $signature = 'agents:health {--max-lag=15 : Maximum allowed heartbeat lag in seconds}';
    protected $description = 'Check the health of the agent events bridge';

    public function handle(): int
    {
        $lastSeen = Cache::get(self::HEARTBEAT_KEY);
        $queueSize = (int) Redis::llen('agent-events-queue');
        $maxLag = (int) $this->option('max-lag');
        $lag = $lastSeen ? time() - (int) $lastSeen : null;
        $isHealthy = $lastSeen !== null && $lag <= $maxLag;

        $this->line('queue_size=' . $queueSize);
        $this->line('last_seen=' . ($lastSeen ? date(DATE_ATOM, (int) $lastSeen) : 'never'));
        $this->line('lag_seconds=' . ($lag ?? 'unknown'));

        if ($isHealthy) {
            $this->info('Agent events bridge is healthy.');
            return self::SUCCESS;
        }

        $this->error('Agent events bridge heartbeat is stale or missing.');
        return self::FAILURE;
    }
}
