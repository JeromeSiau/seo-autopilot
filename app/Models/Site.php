<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

class Site extends Model
{
    use HasFactory;

    public const MODE_EXTERNAL = 'external';
    public const MODE_HOSTED = 'hosted';

    public const MODES = [
        self::MODE_EXTERNAL,
        self::MODE_HOSTED,
    ];

    protected $fillable = [
        'team_id',
        'name',
        'domain',
        'mode',
        'language',
        'gsc_token',
        'gsc_refresh_token',
        'gsc_token_expires_at',
        'gsc_property_id',
        'ga4_token',
        'ga4_refresh_token',
        'ga4_token_expires_at',
        'ga4_property_id',
        'business_description',
        'target_audience',
        'topics',
        'last_crawled_at',
        'crawl_status',
        'crawl_pages_count',
        'onboarding_completed_at',
        'tone',
        'writing_style',
        'vocabulary',
        'brand_examples',
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
        'mode' => 'string',
        'last_crawled_at' => 'datetime',
        'crawl_pages_count' => 'integer',
        'onboarding_completed_at' => 'datetime',
        'vocabulary' => 'array',
        'brand_examples' => 'array',
    ];

    protected $appends = [
        'gsc_connected',
        'ga4_connected',
        'url',
    ];

    /**
     * Normalize domain on save: remove protocol and trailing slash.
     */
    protected function setDomainAttribute(string $value): void
    {
        $domain = preg_replace('#^https?://#', '', $value);
        $domain = rtrim($domain, '/');
        $this->attributes['domain'] = $domain;
    }

    /**
     * Get the full URL with protocol.
     */
    public function getUrlAttribute(): string
    {
        return "https://{$this->domain}";
    }

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

    public function activeIntegration(): HasOne
    {
        return $this->hasOne(Integration::class)
            ->where('is_active', true);
    }

    public function analytics(): HasManyThrough
    {
        return $this->hasManyThrough(ArticleAnalytic::class, Article::class);
    }

    public function settings(): HasOne
    {
        return $this->hasOne(SiteSetting::class);
    }

    public function hosting(): HasOne
    {
        return $this->hasOne(SiteHosting::class);
    }

    public function hostedPages(): HasMany
    {
        return $this->hasMany(HostedPage::class);
    }

    public function autopilotLogs(): HasMany
    {
        return $this->hasMany(AutopilotLog::class);
    }

    public function pages(): HasMany
    {
        return $this->hasMany(SitePage::class);
    }

    public function scheduledArticles(): HasMany
    {
        return $this->hasMany(ScheduledArticle::class);
    }

    public function contentPlanGenerations(): HasMany
    {
        return $this->hasMany(ContentPlanGeneration::class);
    }

    public function latestContentPlanGeneration(): HasOne
    {
        return $this->hasOne(ContentPlanGeneration::class)->latestOfMany();
    }

    public function isGscConnected(): bool
    {
        return !empty($this->gsc_token);
    }

    public function getGscConnectedAttribute(): bool
    {
        return $this->isGscConnected();
    }

    public function isGa4Connected(): bool
    {
        return !empty($this->ga4_token);
    }

    public function getGa4ConnectedAttribute(): bool
    {
        return $this->isGa4Connected();
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

    public function isHosted(): bool
    {
        return $this->mode === self::MODE_HOSTED;
    }

    public function isExternal(): bool
    {
        return !$this->isHosted();
    }

    public function hasActivePublishingIntegration(): bool
    {
        if ($this->relationLoaded('activeIntegration')) {
            return $this->activeIntegration !== null;
        }

        return $this->activeIntegration()->exists();
    }

    public function shouldAutoApproveGeneratedArticles(): bool
    {
        return (bool) ($this->settings?->auto_publish)
            && $this->hasActivePublishingIntegration();
    }

    public function getPrimaryHostedDomain(): ?string
    {
        if (!$this->hosting) {
            return null;
        }

        return $this->hosting->canonical_domain
            ?? $this->hosting->custom_domain
            ?? $this->hosting->staging_domain;
    }

    public function getPublicUrlAttribute(): string
    {
        if ($this->isHosted()) {
            $domain = $this->relationLoaded('hosting')
                ? $this->getPrimaryHostedDomain()
                : $this->hosting()?->first()?->canonical_domain
                    ?? $this->hosting()?->first()?->custom_domain
                    ?? $this->hosting()?->first()?->staging_domain;

            if ($domain) {
                return "https://{$domain}";
            }
        }

        return $this->url;
    }

    public function isOnboardingComplete(): bool
    {
        return $this->onboarding_completed_at !== null;
    }

    public function hasRecentAutopilotErrors(?Carbon $since = null): bool
    {
        $since ??= now()->subDay();

        return $this->articles()
            ->where('status', Article::STATUS_FAILED)
            ->where('created_at', '>=', $since)
            ->exists();
    }

    public function autopilotStatus(): string
    {
        if (!$this->isOnboardingComplete()) {
            return 'not_configured';
        }

        if (!$this->isAutopilotActive()) {
            return 'paused';
        }

        if ($this->hasRecentAutopilotErrors()) {
            return 'error';
        }

        return 'active';
    }

    public function toBrandVoiceContext(): string
    {
        if (!$this->tone && !$this->writing_style) {
            return 'Write in a professional, engaging tone.';
        }

        $context = '';

        if ($this->writing_style) {
            $context .= "Writing Style: {$this->writing_style}\n";
        }

        if ($this->tone) {
            $context .= "Tone: {$this->tone}\n";
        }

        if (!empty($this->vocabulary)) {
            $context .= "Vocabulary preferences:\n";
            if (!empty($this->vocabulary['use'])) {
                $context .= "- Words to use: " . implode(', ', $this->vocabulary['use']) . "\n";
            }
            if (!empty($this->vocabulary['avoid'])) {
                $context .= "- Words to avoid: " . implode(', ', $this->vocabulary['avoid']) . "\n";
            }
        }

        if (!empty($this->brand_examples)) {
            $context .= "\nExample excerpts from existing content:\n";
            foreach (array_slice($this->brand_examples, 0, 3) as $example) {
                $context .= "---\n{$example}\n";
            }
        }

        return $context ?: 'Write in a professional, engaging tone.';
    }
}
