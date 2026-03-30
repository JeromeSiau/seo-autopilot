<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class HostedRedirectResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'site_id' => $this->site_id,
            'source_path' => $this->source_path,
            'destination_url' => $this->destination_url,
            'http_status' => $this->http_status,
            'hit_count' => $this->hit_count,
            'last_used_at' => $this->last_used_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
