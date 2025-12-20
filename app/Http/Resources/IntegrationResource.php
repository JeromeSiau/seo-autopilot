<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class IntegrationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'name' => $this->name,
            'is_active' => $this->is_active,
            'site_id' => $this->site_id,
            'site' => new SiteResource($this->whenLoaded('site')),
            'created_at' => $this->created_at,
        ];
    }
}
