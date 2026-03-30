<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AiPromptSet extends Model
{
    protected $fillable = [
        'site_id',
        'key',
        'name',
        'description',
        'is_active',
        'is_default',
        'metadata',
        'last_synced_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_default' => 'boolean',
        'metadata' => 'array',
        'last_synced_at' => 'datetime',
    ];

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function prompts(): HasMany
    {
        return $this->hasMany(AiPrompt::class);
    }
}
