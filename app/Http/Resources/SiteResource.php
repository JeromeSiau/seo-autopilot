<?php

namespace App\Http\Resources;

use App\Http\Resources\KeywordResource;
use App\Http\Resources\HostedAuthorResource;
use App\Http\Resources\HostedCategoryResource;
use App\Http\Resources\HostedNavigationItemResource;
use App\Http\Resources\HostedRedirectResource;
use App\Http\Resources\HostedTagResource;
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
            'mode' => $this->mode,
            'public_url' => $this->public_url,
            'language' => $this->language,
            'business_description' => $this->business_description,
            'target_audience' => $this->target_audience,
            'topics' => $this->topics,
            'tone' => $this->tone,
            'writing_style' => $this->writing_style,
            'vocabulary' => $this->vocabulary,
            'brand_examples' => $this->brand_examples,
            'gsc_connected' => $this->isGscConnected(),
            'gsc_property_id' => $this->when($this->gsc_property_id, $this->gsc_property_id),
            'ga4_connected' => $this->isGa4Connected(),
            'ga4_property_id' => $this->when($this->ga4_property_id, $this->ga4_property_id),
            'keywords_count' => $this->whenCounted('keywords'),
            'articles_count' => $this->whenCounted('articles'),
            'integrations_count' => $this->whenCounted('integrations'),
            'published_articles_count' => $this->whenCounted('articles', $this->published_articles_count),
            'pending_keywords_count' => $this->whenCounted('keywords', $this->pending_keywords_count),
            'keywords' => $this->whenLoaded('keywords', fn () => KeywordResource::collection($this->keywords)),
            'settings' => $this->whenLoaded('settings'),
            'hosting' => $this->whenLoaded('hosting'),
            'hosted_pages' => $this->whenLoaded('hostedPages'),
            'hosted_redirects' => $this->whenLoaded('hostedRedirects', fn () => HostedRedirectResource::collection($this->hostedRedirects)->resolve()),
            'hosted_authors' => $this->whenLoaded('hostedAuthors', fn () => HostedAuthorResource::collection($this->hostedAuthors)->resolve()),
            'hosted_categories' => $this->whenLoaded('hostedCategories', fn () => HostedCategoryResource::collection($this->hostedCategories)->resolve()),
            'hosted_tags' => $this->whenLoaded('hostedTags', fn () => HostedTagResource::collection($this->hostedTags)->resolve()),
            'hosted_assets' => $this->whenLoaded('hostedAssets', fn () => HostedAssetResource::collection($this->hostedAssets)->resolve()),
            'hosted_navigation_items' => $this->whenLoaded('hostedNavigationItems', fn () => HostedNavigationItemResource::collection($this->hostedNavigationItems)->resolve()),
            'autopilot_status' => $this->getAutopilotStatus(),
            'onboarding_completed_at' => $this->onboarding_completed_at,
            'onboarding_complete' => (bool) $this->onboarding_completed_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    protected function getAutopilotStatus(): string
    {
        return $this->autopilotStatus();
    }
}
