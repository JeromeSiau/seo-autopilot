<?php

namespace App\Console\Commands;

use App\Events\AgentActivityEvent;
use App\Models\AgentEvent;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;

class ProcessAgentEvents extends Command
{
    protected $signature = 'agents:process-events';
    protected $description = 'Process agent events from Redis queue and broadcast them';

    public function handle(): int
    {
        $this->info('Starting agent events processor...');

        while (true) {
            $event = Redis::lpop('agent-events-queue');

            if ($event) {
                $this->processEvent(json_decode($event, true));
            } else {
                // No events, wait a bit
                usleep(100000); // 100ms
            }
        }

        return 0;
    }

    private function processEvent(array $data): void
    {
        try {
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
