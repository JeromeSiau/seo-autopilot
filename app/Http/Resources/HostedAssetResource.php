<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class HostedAssetResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'site_id' => $this->site_id,
            'type' => $this->type,
            'name' => $this->name,
            'disk' => $this->disk,
            'path' => $this->path,
            'public_url' => $this->public_url,
            'export_path' => $this->export_path,
            'mime_type' => $this->mime_type,
            'size_bytes' => $this->size_bytes,
            'alt_text' => $this->alt_text,
            'source_url' => $this->source_url,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
