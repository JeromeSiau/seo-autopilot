<?php

namespace App\Events;

use App\Models\AgentEvent;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AgentActivityEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly AgentEvent $agentEvent
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('article.' . $this->agentEvent->article_id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'agent.activity';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->agentEvent->id,
            'agent_type' => $this->agentEvent->agent_type,
            'event_type' => $this->agentEvent->event_type,
            'message' => $this->agentEvent->message,
            'reasoning' => $this->agentEvent->reasoning,
            'metadata' => $this->agentEvent->metadata,
            'progress_current' => $this->agentEvent->progress_current,
            'progress_total' => $this->agentEvent->progress_total,
            'progress_percent' => $this->agentEvent->progress_percent,
            'created_at' => $this->agentEvent->created_at->toISOString(),
        ];
    }
}
