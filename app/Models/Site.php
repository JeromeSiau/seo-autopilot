<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Site extends Model
{
    protected $fillable = [
        'team_id',
        'name',
        'domain',
        'language',
        'gsc_token',
        'gsc_refresh_token',
        'gsc_token_expires_at',
        'ga4_token',
        'ga4_refresh_token',
        'ga4_token_expires_at',
        'ga4_property_id',
        'business_description',
        'target_audience',
        'topics',
        'last_crawled_at',
        'onboarding_completed_at',
    ];

    protected $hidden = [
        'gsc_token',
        'gsc_refresh_token',
        'ga4_token',
        'ga4_refresh_token',
    ];

    protected $casts = [
        'gsc_token' => 'encrypted',
        'gsc_refresh_token' => 'encrypted',
        'gsc_token_expires_at' => 'datetime',
        'ga4_token' => 'encrypted',
        'ga4_refresh_token' => 'encrypted',
        'ga4_token_expires_at' => 'datetime',
        'topics' => 'array',
        'last_crawled_at' => 'datetime',
        'onboarding_completed_at' => 'datetime',
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function keywords(): HasMany
    {
        return $this->hasMany(Keyword::class);
    }

    public function articles(): HasMany
    {
        return $this->hasMany(Article::class);
    }

    public function integrations(): HasMany
    {
        return $this->hasMany(Integration::class);
    }

    public function analytics(): HasManyThrough
    {
        return $this->hasManyThrough(ArticleAnalytic::class, Article::class);
    }

    public function settings(): HasOne
    {
        return $this->hasOne(SiteSetting::class);
    }

    public function autopilotLogs(): HasMany
    {
        return $this->hasMany(AutopilotLog::class);
    }

    public function isGscConnected(): bool
    {
        return !empty($this->gsc_token);
    }

    public function isGa4Connected(): bool
    {
        return !empty($this->ga4_token);
    }

    public function getPublishedArticlesCountAttribute(): int
    {
        return $this->articles()->where('status', 'published')->count();
    }

    public function getPendingKeywordsCountAttribute(): int
    {
        return $this->keywords()->where('status', 'pending')->count();
    }

    public function getOrCreateSettings(): SiteSetting
    {
        if ($this->settings) {
            return $this->settings;
        }

        return SiteSetting::create([
            'site_id' => $this->id,
            'articles_per_week' => 5,
        ]);
    }

    public function isAutopilotActive(): bool
    {
        return $this->settings?->autopilot_enabled ?? false;
    }

    public function isOnboardingComplete(): bool
    {
        return $this->onboarding_completed_at !== null;
    }
}
