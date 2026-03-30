<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Article extends Model
{
    use HasFactory;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_GENERATING = 'generating';
    public const STATUS_REVIEW = 'review';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_PUBLISHED = 'published';
    public const STATUS_FAILED = 'failed';

    public const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_GENERATING,
        self::STATUS_REVIEW,
        self::STATUS_APPROVED,
        self::STATUS_PUBLISHED,
        self::STATUS_FAILED,
    ];

    protected $fillable = [
        'site_id',
        'hosted_author_id',
        'hosted_category_id',
        'keyword_id',
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

        static::saving(function ($article) {
            if (empty($article->slug) && $article->title) {
                $article->slug = Str::slug($article->title);
            }

            if ($article->site_id && (
                !$article->slug
                || $article->isDirty('slug')
                || $article->isDirty('title')
                || $article->isDirty('site_id')
            )) {
                $article->slug = static::generateUniqueSlug(
                    $article->site_id,
                    $article->slug ?: $article->title,
                    $article->exists ? $article->id : null,
                );
            }

            if ($article->isDirty('content') && $article->content) {
                $article->word_count = str_word_count(strip_tags($article->content));
            }
        });

        static::created(function (Article $article) {
            $article->site->team->clearArticlesMonthCache();
        });

        static::deleted(function (Article $article) {
            $article->site->team->clearArticlesMonthCache();
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

    public function hostedAuthor(): BelongsTo
    {
        return $this->belongsTo(HostedAuthor::class, 'hosted_author_id');
    }

    public function hostedCategory(): BelongsTo
    {
        return $this->belongsTo(HostedCategory::class, 'hosted_category_id');
    }

    public function hostedTags(): BelongsToMany
    {
        return $this->belongsToMany(HostedTag::class, 'article_hosted_tag')
            ->withTimestamps();
    }

    public function analytics(): HasMany
    {
        return $this->hasMany(ArticleAnalytic::class);
    }

    public function agentEvents(): HasMany
    {
        return $this->hasMany(AgentEvent::class);
    }

    public function citations(): HasMany
    {
        return $this->hasMany(ArticleCitation::class);
    }

    public function score(): HasOne
    {
        return $this->hasOne(ArticleScore::class);
    }

    public function editorialComments(): HasMany
    {
        return $this->hasMany(EditorialComment::class)->latest();
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(ArticleAssignment::class);
    }

    public function approvalRequests(): HasMany
    {
        return $this->hasMany(ApprovalRequest::class)->latest();
    }

    public function refreshRecommendations(): HasMany
    {
        return $this->hasMany(RefreshRecommendation::class)->latest();
    }

    public function refreshRuns(): HasMany
    {
        return $this->hasMany(ArticleRefreshRun::class)->latest();
    }

    public function scopeDraft($query)
    {
        return $query->where('status', self::STATUS_DRAFT);
    }

    public function scopeReady($query)
    {
        return $query->where('status', self::STATUS_APPROVED);
    }

    public function scopeApproved($query)
    {
        return $query->where('status', self::STATUS_APPROVED);
    }

    public function scopeReview($query)
    {
        return $query->where('status', self::STATUS_REVIEW);
    }

    public function scopePublished($query)
    {
        return $query->where('status', self::STATUS_PUBLISHED);
    }

    public function scopeGenerating($query)
    {
        return $query->where('status', self::STATUS_GENERATING);
    }

    public function scopeFailed($query)
    {
        return $query->where('status', self::STATUS_FAILED);
    }

    public function isPublished(): bool
    {
        return $this->status === self::STATUS_PUBLISHED;
    }

    public function isReady(): bool
    {
        return $this->isApproved();
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    public function markAsGenerating(): void
    {
        $this->update(['status' => self::STATUS_GENERATING]);
    }

    public function markAsReady(): void
    {
        $this->markAsApproved();
    }

    public function markAsReview(): void
    {
        $this->update(['status' => self::STATUS_REVIEW]);
    }

    public function markAsApproved(): void
    {
        $this->update(['status' => self::STATUS_APPROVED]);
    }

    public function markAsPublished(string $url): void
    {
        $this->update([
            'status' => self::STATUS_PUBLISHED,
            'published_at' => now(),
            'published_url' => $url,
        ]);
    }

    public function markAsFailed(string $errorMessage): void
    {
        $this->update([
            'status' => self::STATUS_FAILED,
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

    public function getTotalSessionsAttribute(): int
    {
        return (int) $this->analytics()->sum('sessions');
    }

    public function getTotalPageViewsAttribute(): int
    {
        return (int) $this->analytics()->sum('page_views');
    }

    public function getTotalConversionsAttribute(): int
    {
        return (int) $this->analytics()->sum('conversions');
    }

    public function getEstimatedConversionsAttribute(): float
    {
        if ($this->total_conversions > 0) {
            return (float) $this->total_conversions;
        }

        $baseline = $this->total_sessions > 0 ? $this->total_sessions : $this->total_clicks;

        return round($baseline * 0.02, 1);
    }

    public function getConversionSourceAttribute(): string
    {
        return $this->total_conversions > 0 ? 'tracked' : 'modeled';
    }

    public function getConversionRateAttribute(): ?float
    {
        $baseline = $this->total_sessions > 0 ? $this->total_sessions : $this->total_clicks;

        if ($baseline <= 0) {
            return null;
        }

        return round(($this->estimated_conversions / $baseline) * 100, 2);
    }

    public function latestRefreshRun(): ?ArticleRefreshRun
    {
        return $this->relationLoaded('refreshRuns')
            ? $this->refreshRuns->first()
            : $this->refreshRuns()->latest()->first();
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

    public function getFeaturedImageUrlAttribute(): ?string
    {
        $featured = $this->images['featured'] ?? null;

        if (!is_array($featured)) {
            return null;
        }

        if (!empty($featured['url'])) {
            return $featured['url'];
        }

        if (!empty($featured['local_path'])) {
            return Storage::disk('public')->url($featured['local_path']);
        }

        return null;
    }

    protected static function generateUniqueSlug(int $siteId, string $source, ?int $ignoreId = null): string
    {
        $baseSlug = Str::slug($source);
        $baseSlug = $baseSlug !== '' ? $baseSlug : 'article';
        $slug = $baseSlug;
        $suffix = 2;

        while (static::query()
            ->where('site_id', $siteId)
            ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
            ->where('slug', $slug)
            ->exists()) {
            $slug = "{$baseSlug}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }
}
