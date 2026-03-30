<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ArticleRefreshRunResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'article_id' => $this->article_id,
            'refresh_recommendation_id' => $this->refresh_recommendation_id,
            'old_score_snapshot' => $this->old_score_snapshot ?? [],
            'new_score_snapshot' => $this->new_score_snapshot ?? [],
            'status' => $this->status,
            'summary' => $this->summary,
            'metadata' => $this->metadata ?? [],
            'created_at' => $this->created_at,
        ];
    }
}
