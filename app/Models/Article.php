<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Article extends Model
{
    protected $fillable = [
        'site_id',
        'keyword_id',
        'brand_voice_id',
        'title',
        'slug',
        'content',
        'meta_title',
        'meta_description',
        'images',
        'status',
        'error_message',
        'published_at',
        'published_url',
        'published_remote_id',
        'published_via',
        'llm_used',
        'generation_cost',
        'word_count',
        'generation_time_seconds',
    ];

    protected $casts = [
        'images' => 'array',
        'published_at' => 'datetime',
        'generation_cost' => 'decimal:4',
        'word_count' => 'integer',
        'generation_time_seconds' => 'integer',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($article) {
            if (empty($article->slug)) {
                $article->slug = Str::slug($article->title);
            }
        });

        static::saving(function ($article) {
            if ($article->isDirty('content') && $article->content) {
                $article->word_count = str_word_count(strip_tags($article->content));
            }
        });
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function keyword(): BelongsTo
    {
        return $this->belongsTo(Keyword::class);
    }

    public function brandVoice(): BelongsTo
    {
        return $this->belongsTo(BrandVoice::class);
    }

    public function analytics(): HasMany
    {
        return $this->hasMany(ArticleAnalytic::class);
    }

    public function agentEvents(): HasMany
    {
        return $this->hasMany(AgentEvent::class);
    }

    public function scopeDraft($query)
    {
        return $query->where('status', 'draft');
    }

    public function scopeReady($query)
    {
        return $query->where('status', 'ready');
    }

    public function scopePublished($query)
    {
        return $query->where('status', 'published');
    }

    public function scopeGenerating($query)
    {
        return $query->where('status', 'generating');
    }

    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    public function isPublished(): bool
    {
        return $this->status === 'published';
    }

    public function isReady(): bool
    {
        return $this->status === 'ready';
    }

    public function markAsGenerating(): void
    {
        $this->update(['status' => 'generating']);
    }

    public function markAsReady(): void
    {
        $this->update(['status' => 'ready']);
    }

    public function markAsPublished(string $url): void
    {
        $this->update([
            'status' => 'published',
            'published_at' => now(),
            'published_url' => $url,
        ]);
    }

    public function markAsFailed(string $errorMessage): void
    {
        $this->update([
            'status' => 'failed',
            'error_message' => $errorMessage,
        ]);
    }

    public function getLatestAnalyticsAttribute(): ?ArticleAnalytic
    {
        return $this->analytics()->latest('date')->first();
    }

    public function getTotalClicksAttribute(): int
    {
        return $this->analytics()->sum('clicks');
    }

    public function getTotalImpressionsAttribute(): int
    {
        return $this->analytics()->sum('impressions');
    }

    public function getAveragePositionAttribute(): ?float
    {
        $avg = $this->analytics()->avg('position');
        return $avg ? round($avg, 1) : null;
    }

    public function getEstimatedValueAttribute(): float
    {
        $cpc = $this->keyword?->cpc ?? 0.5;
        return round($this->total_clicks * $cpc, 2);
    }

    public function getRoiAttribute(): ?float
    {
        if (!$this->generation_cost || $this->generation_cost == 0) {
            return null;
        }
        return round(($this->estimated_value / $this->generation_cost) * 100, 2);
    }
}
