<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ArticleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'content' => $this->when($request->routeIs('articles.show'), $this->content),
            'meta_title' => $this->meta_title,
            'meta_description' => $this->meta_description,
            'images' => $this->images,
            'status' => $this->status,
            'word_count' => $this->word_count,
            'llm_used' => $this->llm_used,
            'generation_cost' => $this->generation_cost,
            'generation_time_seconds' => $this->generation_time_seconds,
            'published_at' => $this->published_at,
            'published_url' => $this->published_url,
            'published_via' => $this->published_via,
            'error_message' => $this->when($this->status === 'failed', $this->error_message),
            'keyword' => new KeywordResource($this->whenLoaded('keyword')),
            'site' => new SiteResource($this->whenLoaded('site')),
            'analytics' => [
                'total_clicks' => $this->total_clicks,
                'total_impressions' => $this->total_impressions,
                'avg_position' => $this->average_position,
                'estimated_value' => $this->estimated_value,
                'roi' => $this->roi,
            ],
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
