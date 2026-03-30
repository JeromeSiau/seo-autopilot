<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class HostedDeployEventResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'status' => $this->status,
            'title' => $this->title,
            'message' => $this->message,
            'metadata' => $this->metadata,
            'occurred_at' => $this->occurred_at,
            'created_at' => $this->created_at,
        ];
    }
}
