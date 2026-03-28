<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DashboardSiteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $settings = $this->relationLoaded('settings') ? $this->settings : $this->settings()->first();
        $generation = $this->relationLoaded('latestContentPlanGeneration')
            ? $this->latestContentPlanGeneration
            : $this->latestContentPlanGeneration()->first();

        return [
            'id' => $this->id,
            'name' => $this->name,
            'domain' => $this->domain,
            'autopilot_status' => $this->autopilotStatus(),
            'onboarding_complete' => $this->isOnboardingComplete(),
            'articles_per_week' => (int) ($settings?->articles_per_week ?? 0),
            'articles_this_week' => (int) ($this->this_week_articles_count ?? 0),
            'articles_in_review' => (int) ($this->review_articles_count ?? 0),
            'is_generating' => (bool) ($generation?->status === 'running'),
        ];
    }
}
