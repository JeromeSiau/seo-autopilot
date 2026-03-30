<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class HostedNavigationItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'site_id' => $this->site_id,
            'placement' => $this->placement,
            'type' => $this->type,
            'label' => $this->label,
            'path' => $this->path,
            'url' => $this->url,
            'target' => $this->target(),
            'open_in_new_tab' => $this->open_in_new_tab,
            'is_active' => $this->is_active,
            'sort_order' => $this->sort_order,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
