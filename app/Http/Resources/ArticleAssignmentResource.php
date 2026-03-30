<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ArticleAssignmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'role' => $this->role,
            'assigned_at' => $this->assigned_at,
            'created_at' => $this->created_at,
            'user' => $this->whenLoaded('user', fn () => (new UserSummaryResource($this->user))->resolve()),
        ];
    }
}
