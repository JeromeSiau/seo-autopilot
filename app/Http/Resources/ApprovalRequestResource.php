<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ApprovalRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'decision_note' => $this->decision_note,
            'decided_at' => $this->decided_at,
            'created_at' => $this->created_at,
            'requested_by' => $this->requested_by,
            'requested_to' => $this->requested_to,
            'requested_by_user' => $this->whenLoaded('requestedBy', fn () => (new UserSummaryResource($this->requestedBy))->resolve()),
            'requested_to_user' => $this->whenLoaded('requestedTo', fn () => (new UserSummaryResource($this->requestedTo))->resolve()),
        ];
    }
}
