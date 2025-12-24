<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgentEvent extends Model
{
    protected $fillable = [
        'article_id',
        'agent_type',
        'event_type',
        'message',
        'reasoning',
        'metadata',
        'progress_current',
        'progress_total',
    ];

    protected $casts = [
        'metadata' => 'array',
        'progress_current' => 'integer',
        'progress_total' => 'integer',
    ];

    public const TYPE_RESEARCH = 'research';
    public const TYPE_COMPETITOR = 'competitor';
    public const TYPE_FACT_CHECKER = 'fact_checker';
    public const TYPE_INTERNAL_LINKING = 'internal_linking';
    public const TYPE_OUTLINE = 'outline';
    public const TYPE_WRITING = 'writing';
    public const TYPE_POLISH = 'polish';

    public const EVENT_STARTED = 'started';
    public const EVENT_PROGRESS = 'progress';
    public const EVENT_COMPLETED = 'completed';
    public const EVENT_ERROR = 'error';

    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }

    public function getProgressPercentAttribute(): ?int
    {
        if ($this->progress_total === null || $this->progress_total === 0) {
            return null;
        }
        return (int) round(($this->progress_current / $this->progress_total) * 100);
    }

    public function scopeForArticle($query, int $articleId)
    {
        return $query->where('article_id', $articleId);
    }

    public function scopeByAgent($query, string $agentType)
    {
        return $query->where('agent_type', $agentType);
    }
}
