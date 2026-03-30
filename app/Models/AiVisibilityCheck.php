<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AiVisibilityCheck extends Model
{
    public const ENGINE_AI_OVERVIEWS = 'ai_overviews';
    public const ENGINE_CHATGPT = 'chatgpt';
    public const ENGINE_PERPLEXITY = 'perplexity';
    public const ENGINE_GEMINI = 'gemini';

    public const ENGINES = [
        self::ENGINE_AI_OVERVIEWS,
        self::ENGINE_CHATGPT,
        self::ENGINE_PERPLEXITY,
        self::ENGINE_GEMINI,
    ];

    protected $fillable = [
        'site_id',
        'ai_prompt_id',
        'engine',
        'provider',
        'status',
        'visibility_score',
        'appears',
        'rank_bucket',
        'raw_response',
        'metadata',
        'checked_at',
    ];

    protected $casts = [
        'visibility_score' => 'integer',
        'appears' => 'boolean',
        'raw_response' => 'array',
        'metadata' => 'array',
        'checked_at' => 'datetime',
    ];

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function prompt(): BelongsTo
    {
        return $this->belongsTo(AiPrompt::class, 'ai_prompt_id');
    }

    public function mentions(): HasMany
    {
        return $this->hasMany(AiVisibilityMention::class);
    }

    public function sources(): HasMany
    {
        return $this->hasMany(AiVisibilitySource::class);
    }

    public function alerts(): HasMany
    {
        return $this->hasMany(AiVisibilityAlert::class, 'ai_visibility_check_id');
    }
}
