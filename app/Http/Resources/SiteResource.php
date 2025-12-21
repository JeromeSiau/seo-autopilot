<?php

namespace App\Http\Resources;

use App\Http\Resources\KeywordResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SiteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'domain' => $this->domain,
            'language' => $this->language,
            'business_description' => $this->business_description,
            'target_audience' => $this->target_audience,
            'topics' => $this->topics,
            'gsc_connected' => $this->isGscConnected(),
            'gsc_property_id' => $this->when($this->gsc_property_id, $this->gsc_property_id),
            'ga4_connected' => $this->isGa4Connected(),
            'ga4_property_id' => $this->when($this->ga4_property_id, $this->ga4_property_id),
            'keywords_count' => $this->whenCounted('keywords'),
            'articles_count' => $this->whenCounted('articles'),
            'integrations_count' => $this->whenCounted('integrations'),
            'published_articles_count' => $this->whenCounted('articles', $this->published_articles_count),
            'pending_keywords_count' => $this->whenCounted('keywords', $this->pending_keywords_count),
            'keywords' => KeywordResource::collection($this->whenLoaded('keywords')),
            'settings' => $this->whenLoaded('settings'),
            'autopilot_status' => $this->getAutopilotStatus(),
            'onboarding_completed_at' => $this->onboarding_completed_at,
            'onboarding_complete' => (bool) $this->onboarding_completed_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    protected function getAutopilotStatus(): string
    {
        // Not configured if onboarding not completed
        if (!$this->onboarding_completed_at) {
            return 'not_configured';
        }

        // Check if site has settings
        $settings = $this->relationLoaded('settings') ? $this->settings : $this->settings()->first();

        if (!$settings) {
            return 'not_configured';
        }

        // Check autopilot enabled status
        if ($settings->autopilot_enabled ?? false) {
            return 'active';
        }

        return 'paused';
    }
}
