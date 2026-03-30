<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RefreshRecommendationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'site_id' => $this->site_id,
            'article_id' => $this->article_id,
            'trigger_type' => $this->trigger_type,
            'severity' => $this->severity,
            'reason' => $this->reason,
            'recommended_actions' => $this->recommended_actions ?? [],
            'metrics_snapshot' => $this->metrics_snapshot ?? [],
            'status' => $this->status,
            'detected_at' => $this->detected_at,
            'executed_at' => $this->executed_at,
        ];
    }
}
