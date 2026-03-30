<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WebhookDeliveryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'webhook_endpoint_id' => $this->webhook_endpoint_id,
            'endpoint_url' => $this->whenLoaded('endpoint', fn () => $this->endpoint?->url),
            'event_name' => $this->event_name,
            'status' => $this->status,
            'attempt_number' => $this->attempt_number,
            'max_attempts' => $this->max_attempts,
            'response_code' => $this->response_code,
            'error_message' => $this->error_message,
            'attempted_at' => $this->attempted_at,
            'next_retry_at' => $this->next_retry_at,
            'created_at' => $this->created_at,
        ];
    }
}
