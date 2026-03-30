<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class HostedExportRunResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'target_path' => $this->target_path,
            'size_bytes' => $this->size_bytes,
            'error_message' => $this->error_message,
            'metadata' => $this->metadata,
            'started_at' => $this->started_at,
            'completed_at' => $this->completed_at,
            'created_at' => $this->created_at,
        ];
    }
}
