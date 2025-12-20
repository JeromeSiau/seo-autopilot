<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ArticleAnalytic extends Model
{
    protected $fillable = [
        'article_id',
        'date',
        'impressions',
        'clicks',
        'position',
        'ctr',
        'sessions',
        'page_views',
        'avg_time_on_page',
        'bounce_rate',
    ];

    protected $casts = [
        'date' => 'date',
        'impressions' => 'integer',
        'clicks' => 'integer',
        'position' => 'decimal:2',
        'ctr' => 'decimal:2',
        'sessions' => 'integer',
        'page_views' => 'integer',
        'avg_time_on_page' => 'decimal:2',
        'bounce_rate' => 'decimal:2',
    ];

    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }

    public static function recordFromSearchConsole(
        int $articleId,
        string $date,
        array $data
    ): self {
        return static::updateOrCreate(
            [
                'article_id' => $articleId,
                'date' => $date,
            ],
            [
                'impressions' => $data['impressions'] ?? 0,
                'clicks' => $data['clicks'] ?? 0,
                'position' => $data['position'] ?? null,
                'ctr' => $data['ctr'] ?? null,
            ]
        );
    }

    public static function recordFromGA4(
        int $articleId,
        string $date,
        array $data
    ): self {
        return static::updateOrCreate(
            [
                'article_id' => $articleId,
                'date' => $date,
            ],
            [
                'sessions' => $data['sessions'] ?? null,
                'page_views' => $data['pageViews'] ?? null,
                'avg_time_on_page' => $data['avgTimeOnPage'] ?? null,
                'bounce_rate' => $data['bounceRate'] ?? null,
            ]
        );
    }

    public function scopeForPeriod($query, string $start, string $end)
    {
        return $query->whereBetween('date', [$start, $end]);
    }

    public function scopeLast30Days($query)
    {
        return $query->where('date', '>=', now()->subDays(30));
    }

    public function scopeLast7Days($query)
    {
        return $query->where('date', '>=', now()->subDays(7));
    }
}
