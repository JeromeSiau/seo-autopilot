<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiVisibilityMention extends Model
{
    protected $fillable = [
        'ai_visibility_check_id',
        'domain',
        'url',
        'brand_name',
        'mention_type',
        'position',
        'is_our_brand',
    ];

    protected $casts = [
        'position' => 'integer',
        'is_our_brand' => 'boolean',
    ];

    public function check(): BelongsTo
    {
        return $this->belongsTo(AiVisibilityCheck::class, 'ai_visibility_check_id');
    }
}
