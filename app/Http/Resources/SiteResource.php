<?php

namespace App\Http\Resources;

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
            'gsc_connected' => $this->isGscConnected(),
            'ga4_connected' => $this->isGa4Connected(),
            'ga4_property_id' => $this->when($this->ga4_property_id, $this->ga4_property_id),
            'published_articles_count' => $this->whenCounted('articles', $this->published_articles_count),
            'pending_keywords_count' => $this->whenCounted('keywords', $this->pending_keywords_count),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
