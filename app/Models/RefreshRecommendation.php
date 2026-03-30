<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RefreshRecommendation extends Model
{
    public const STATUS_OPEN = 'open';
    public const STATUS_ACCEPTED = 'accepted';
    public const STATUS_DISMISSED = 'dismissed';
    public const STATUS_EXECUTED = 'executed';

    public const TRIGGER_POSITION_DROP = 'position_drop';
    public const TRIGGER_TRAFFIC_DROP = 'traffic_drop';
    public const TRIGGER_CTR_DROP = 'ctr_drop';
    public const TRIGGER_AI_VISIBILITY_DROP = 'ai_visibility_drop';
    public const TRIGGER_CONTENT_DECAY = 'content_decay';
    public const TRIGGER_COMPETITOR_GAP = 'competitor_gap';

    protected $fillable = [
        'site_id',
        'article_id',
        'trigger_type',
        'severity',
        'reason',
        'recommended_actions',
        'metrics_snapshot',
        'status',
        'detected_at',
        'executed_at',
    ];

    protected $casts = [
        'recommended_actions' => 'array',
        'metrics_snapshot' => 'array',
        'detected_at' => 'datetime',
        'executed_at' => 'datetime',
    ];

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }

    public function runs(): HasMany
    {
        return $this->hasMany(ArticleRefreshRun::class);
    }
}
