<?php

namespace App\Services\Webhooks;

use App\Jobs\DeliverWebhookJob;
use App\Models\Team;
use App\Models\WebhookEndpoint;

class WebhookDispatcher
{
    public function dispatch(Team $team, string $eventName, array $payload): int
    {
        $endpoints = $team->webhookEndpoints()
            ->where('is_active', true)
            ->get()
            ->filter(fn (WebhookEndpoint $endpoint) => in_array($eventName, $endpoint->events ?? [], true));

        foreach ($endpoints as $endpoint) {
            DeliverWebhookJob::dispatch($endpoint, $eventName, $payload);
        }

        return $endpoints->count();
    }
}
