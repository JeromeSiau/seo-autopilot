<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ArticleCitationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'source_type' => $this->source_type,
            'title' => $this->title,
            'url' => $this->url,
            'domain' => $this->domain,
            'excerpt' => $this->excerpt,
            'metadata' => $this->metadata,
        ];
    }
}
