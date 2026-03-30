<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ArticleScoreResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'readiness_score' => $this->readiness_score,
            'brand_fit_score' => $this->brand_fit_score,
            'seo_score' => $this->seo_score,
            'citation_score' => $this->citation_score,
            'internal_link_score' => $this->internal_link_score,
            'fact_confidence_score' => $this->fact_confidence_score,
            'warnings' => $this->warnings ?? [],
            'checklist' => $this->checklist ?? [],
            'updated_at' => $this->updated_at,
        ];
    }
}
