<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AiPrompt extends Model
{
    protected $fillable = [
        'site_id',
        'ai_prompt_set_id',
        'prompt',
        'topic',
        'intent',
        'priority',
        'locale',
        'country',
        'is_active',
        'metadata',
        'last_generated_at',
    ];

    protected $casts = [
        'priority' => 'integer',
        'is_active' => 'boolean',
        'metadata' => 'array',
        'last_generated_at' => 'datetime',
    ];

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function promptSet(): BelongsTo
    {
        return $this->belongsTo(AiPromptSet::class, 'ai_prompt_set_id');
    }

    public function checks(): HasMany
    {
        return $this->hasMany(AiVisibilityCheck::class);
    }
}
