<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class KeywordResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'keyword' => $this->keyword,
            'volume' => $this->volume,
            'difficulty' => $this->difficulty,
            'cpc' => $this->cpc,
            'score' => $this->score,
            'source' => $this->source,
            'status' => $this->status,
            'cluster_id' => $this->cluster_id,
            'is_quick_win' => $this->isQuickWin(),
            'article' => new ArticleResource($this->whenLoaded('article')),
            'created_at' => $this->created_at,
        ];
    }
}
