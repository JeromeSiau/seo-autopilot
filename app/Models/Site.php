<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Site extends Model
{
    protected $fillable = [
        'team_id',
        'name',
        'domain',
        'language',
        'gsc_token',
        'gsc_refresh_token',
        'ga4_token',
        'ga4_refresh_token',
        'ga4_property_id',
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
        'ga4_token' => 'encrypted',
        'ga4_refresh_token' => 'encrypted',
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

    public function isGscConnected(): bool
    {
        return !empty($this->gsc_token);
    }

    public function isGa4Connected(): bool
    {
        return !empty($this->ga4_token) && !empty($this->ga4_property_id);
    }

    public function getPublishedArticlesCountAttribute(): int
    {
        return $this->articles()->where('status', 'published')->count();
    }

    public function getPendingKeywordsCountAttribute(): int
    {
        return $this->keywords()->where('status', 'pending')->count();
    }
}
