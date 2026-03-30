<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiVisibilitySource extends Model
{
    protected $fillable = [
        'ai_visibility_check_id',
        'source_domain',
        'source_url',
        'source_title',
        'position',
    ];

    protected $casts = [
        'position' => 'integer',
    ];

    public function check(): BelongsTo
    {
        return $this->belongsTo(AiVisibilityCheck::class, 'ai_visibility_check_id');
    }
}
