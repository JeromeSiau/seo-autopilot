<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WebhookEndpointResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'team_id' => $this->team_id,
            'url' => $this->url,
            'events' => $this->events ?? [],
            'is_active' => $this->is_active,
            'has_secret' => filled($this->secret),
            'last_error' => $this->last_error,
            'last_delivered_at' => $this->last_delivered_at,
            'created_at' => $this->created_at,
        ];
    }
}
