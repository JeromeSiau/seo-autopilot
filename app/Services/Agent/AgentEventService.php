<?php

namespace App\Services\Agent;

use App\Events\AgentActivityEvent;
use App\Models\AgentEvent;
use App\Models\Article;

class AgentEventService
{
    public function emit(
        Article $article,
        string $agentType,
        string $eventType,
        string $message,
        ?string $reasoning = null,
        ?array $metadata = null,
        ?int $progressCurrent = null,
        ?int $progressTotal = null
    ): AgentEvent {
        $event = AgentEvent::create([
            'article_id' => $article->id,
            'agent_type' => $agentType,
            'event_type' => $eventType,
            'message' => $message,
            'reasoning' => $reasoning,
            'metadata' => $metadata,
            'progress_current' => $progressCurrent,
            'progress_total' => $progressTotal,
        ]);

        broadcast(new AgentActivityEvent($event))->toOthers();

        return $event;
    }

    public function started(Article $article, string $agentType, string $message, ?string $reasoning = null): AgentEvent
    {
        return $this->emit($article, $agentType, AgentEvent::EVENT_STARTED, $message, $reasoning);
    }

    public function progress(
        Article $article,
        string $agentType,
        string $message,
        ?int $current = null,
        ?int $total = null,
        ?string $reasoning = null,
        ?array $metadata = null
    ): AgentEvent {
        return $this->emit($article, $agentType, AgentEvent::EVENT_PROGRESS, $message, $reasoning, $metadata, $current, $total);
    }

    public function completed(Article $article, string $agentType, string $message, ?string $reasoning = null, ?array $metadata = null): AgentEvent
    {
        return $this->emit($article, $agentType, AgentEvent::EVENT_COMPLETED, $message, $reasoning, $metadata);
    }

    public function error(Article $article, string $agentType, string $message, ?string $errorDetails = null): AgentEvent
    {
        return $this->emit($article, $agentType, AgentEvent::EVENT_ERROR, $message, null, ['error' => $errorDetails]);
    }
}
