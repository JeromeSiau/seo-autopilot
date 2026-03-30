<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiVisibilityAlert extends Model
{
    public const STATUS_OPEN = 'open';
    public const STATUS_RESOLVED = 'resolved';

    protected $fillable = [
        'site_id',
        'ai_prompt_id',
        'ai_visibility_check_id',
        'article_id',
        'fingerprint',
        'type',
        'severity',
        'title',
        'reason',
        'engine',
        'visibility_delta',
        'related_domains',
        'status',
        'metadata',
        'first_detected_at',
        'last_detected_at',
        'resolved_at',
    ];

    protected $casts = [
        'visibility_delta' => 'float',
        'related_domains' => 'array',
        'metadata' => 'array',
        'first_detected_at' => 'datetime',
        'last_detected_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function prompt(): BelongsTo
    {
        return $this->belongsTo(AiPrompt::class, 'ai_prompt_id');
    }

    public function check(): BelongsTo
    {
        return $this->belongsTo(AiVisibilityCheck::class, 'ai_visibility_check_id');
    }

    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }
}
