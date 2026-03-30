<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CampaignRunResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'site_id' => $this->site_id,
            'created_by' => $this->created_by,
            'name' => $this->name,
            'status' => $this->status,
            'input_type' => $this->input_type,
            'payload' => $this->payload ?? [],
            'processed_count' => $this->processed_count,
            'succeeded_count' => $this->succeeded_count,
            'failed_count' => $this->failed_count,
            'started_at' => $this->started_at,
            'completed_at' => $this->completed_at,
            'site' => $this->whenLoaded('site', fn () => (new SiteResource($this->site))->resolve()),
            'creator' => $this->whenLoaded('creator', fn () => (new UserSummaryResource($this->creator))->resolve()),
            'created_at' => $this->created_at,
        ];
    }
}
