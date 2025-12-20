<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Keyword extends Model
{
    protected $fillable = [
        'site_id',
        'keyword',
        'volume',
        'difficulty',
        'cpc',
        'status',
        'cluster_id',
        'source',
        'current_position',
        'impressions',
        'score',
        'scheduled_for',
        'queued_at',
        'processed_at',
        'priority',
    ];

    protected $casts = [
        'volume' => 'integer',
        'difficulty' => 'integer',
        'cpc' => 'decimal:2',
        'current_position' => 'integer',
        'impressions' => 'integer',
        'score' => 'decimal:2',
        'scheduled_for' => 'date',
        'queued_at' => 'datetime',
        'processed_at' => 'datetime',
        'priority' => 'integer',
    ];

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function article(): HasOne
    {
        return $this->hasOne(Article::class);
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeScheduled($query)
    {
        return $query->where('status', 'scheduled');
    }

    public function scopeQuickWins($query)
    {
        return $query->whereBetween('current_position', [5, 30])
            ->where('difficulty', '<=', 30);
    }

    public function scopeByCluster($query, string $clusterId)
    {
        return $query->where('cluster_id', $clusterId);
    }

    public function isQuickWin(): bool
    {
        return $this->current_position >= 5
            && $this->current_position <= 30
            && $this->difficulty <= 30;
    }

    public function calculateScore(): float
    {
        $volumeScore = min(($this->volume ?? 0) / 1000, 100) * 0.3;
        $difficultyScore = (100 - ($this->difficulty ?? 50)) * 0.3;
        $quickWinBonus = $this->isQuickWin() ? 20 : 0;
        $positionBonus = $this->current_position ? (100 - min($this->current_position, 100)) * 0.15 : 0;

        return round($volumeScore + $difficultyScore + $quickWinBonus + $positionBonus, 2);
    }

    public function updateScore(): void
    {
        $this->update(['score' => $this->calculateScore()]);
    }

    public function markAsScheduled(?string $date = null): void
    {
        $this->update([
            'status' => 'scheduled',
            'scheduled_for' => $date ?? now()->addDay(),
        ]);
    }

    public function markAsGenerating(): void
    {
        $this->update(['status' => 'generating']);
    }

    public function markAsCompleted(): void
    {
        $this->update(['status' => 'completed']);
    }

    public function scopeQueued($query)
    {
        return $query->where('status', 'queued')
            ->orderByDesc('priority')
            ->orderBy('queued_at');
    }

    public function addToQueue(): void
    {
        $this->update([
            'status' => 'queued',
            'queued_at' => now(),
            'priority' => (int) $this->calculateScore(),
        ]);
    }
}
