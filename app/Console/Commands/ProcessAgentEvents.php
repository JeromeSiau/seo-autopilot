<?php

namespace App\Console\Commands;

use App\Events\AgentActivityEvent;
use App\Models\AgentEvent;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class ProcessAgentEvents extends Command
{
    protected $signature = 'agents:process-events';
    protected $description = 'Process agent events from Redis queue and broadcast them';

    private bool $shouldRun = true;

    public function handle(): int
    {
        $this->info('Starting agent events processor...');
        $this->registerSignalHandlers();

        while ($this->shouldRun) {
            try {
                $event = Redis::lpop('agent-events-queue');

                if ($event) {
                    $this->processEvent($event);
                } else {
                    usleep(100000); // 100ms
                }
            } catch (\Exception $e) {
                $this->error("Redis error: {$e->getMessage()}");
                sleep(1); // Wait before retry
            }
        }

        $this->info('Shutting down gracefully...');
        return 0;
    }

    private function registerSignalHandlers(): void
    {
        if (extension_loaded('pcntl')) {
            pcntl_async_signals(true);
            pcntl_signal(SIGTERM, fn() => $this->shouldRun = false);
            pcntl_signal(SIGINT, fn() => $this->shouldRun = false);
        }
    }

    private function processEvent(string $eventJson): void
    {
        $data = json_decode($eventJson, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->error("Invalid JSON: " . json_last_error_msg());
            return;
        }

        $requiredFields = ['article_id', 'agent_type', 'event_type', 'message'];
        foreach ($requiredFields as $field) {
            if (!isset($data[$field])) {
                $this->error("Missing required field: {$field}");
                return;
            }
        }

        try {
            DB::reconnect();

            $agentEvent = AgentEvent::create([
                'article_id' => $data['article_id'],
                'agent_type' => $data['agent_type'],
                'event_type' => $data['event_type'],
                'message' => $data['message'],
                'reasoning' => $data['reasoning'] ?? null,
                'metadata' => $data['metadata'] ?? null,
                'progress_current' => $data['progress_current'] ?? null,
                'progress_total' => $data['progress_total'] ?? null,
            ]);

            broadcast(new AgentActivityEvent($agentEvent))->toOthers();

            $this->line("[{$data['agent_type']}] {$data['message']}");
        } catch (\Exception $e) {
            $this->error("Failed to process event: {$e->getMessage()}");
        }
    }
}
