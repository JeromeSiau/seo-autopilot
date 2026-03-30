<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ArticleRefreshRun extends Model
{
    protected $fillable = [
        'article_id',
        'refresh_recommendation_id',
        'old_score_snapshot',
        'new_score_snapshot',
        'status',
        'summary',
        'metadata',
    ];

    protected $casts = [
        'old_score_snapshot' => 'array',
        'new_score_snapshot' => 'array',
        'metadata' => 'array',
    ];

    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }

    public function recommendation(): BelongsTo
    {
        return $this->belongsTo(RefreshRecommendation::class, 'refresh_recommendation_id');
    }
}
