<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class CostLog extends Model
{
    protected $fillable = [
        'costable_type',
        'costable_id',
        'team_id',
        'type',
        'provider',
        'model',
        'operation',
        'cost',
        'input_tokens',
        'output_tokens',
        'metadata',
    ];

    protected $casts = [
        'cost' => 'decimal:6',
        'input_tokens' => 'integer',
        'output_tokens' => 'integer',
        'metadata' => 'array',
    ];

    public function costable(): MorphTo
    {
        return $this->morphTo();
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('type', $type);
    }

    public function scopeByProvider($query, string $provider)
    {
        return $query->where('provider', $provider);
    }

    public function scopeThisMonth($query)
    {
        return $query->whereMonth('created_at', now()->month)
                     ->whereYear('created_at', now()->year);
    }

    public function scopeForTeam($query, int $teamId)
    {
        return $query->where('team_id', $teamId);
    }

    public function getFormattedCostAttribute(): string
    {
        return '€' . number_format($this->cost, 4);
    }
}
