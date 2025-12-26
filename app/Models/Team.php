<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Laravel\Cashier\Billable;

class Team extends Model
{
    use Billable, HasFactory;

    protected $fillable = [
        'name',
        'owner_id',
        'plan_id',
        'is_trial',
        'trial_ends_at',
        'plan',
        'articles_limit',
    ];

    protected $casts = [
        'is_trial' => 'boolean',
        'trial_ends_at' => 'datetime',
    ];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function sites(): HasMany
    {
        return $this->hasMany(Site::class);
    }

    public function integrations(): HasMany
    {
        return $this->hasMany(Integration::class);
    }

    public function billingPlan(): BelongsTo
    {
        return $this->belongsTo(Plan::class, 'plan_id');
    }

    public function isTrialExpired(): bool
    {
        if (! $this->is_trial) {
            return false;
        }

        return $this->trial_ends_at && $this->trial_ends_at->isPast();
    }

    public function canCreateSite(): bool
    {
        if (! $this->billingPlan) {
            return false;
        }

        if ($this->billingPlan->isUnlimitedSites()) {
            return true;
        }

        return $this->sites()->count() < $this->billingPlan->sites_limit;
    }

    public function canGenerateArticle(): bool
    {
        if (! $this->plan) {
            return $this->articles_used_this_month < ($this->articles_limit ?? 0);
        }

        return $this->articlesUsedThisMonth() < $this->articles_limit;
    }

    public function articlesUsedThisMonth(): int
    {
        return Article::whereIn('site_id', $this->sites->pluck('id'))
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->count();
    }

    public function getArticlesUsedThisMonthAttribute(): int
    {
        return $this->articlesUsedThisMonth();
    }

    public function getArticlesLimitAttribute(): int
    {
        // Use billing plan limit if available, otherwise fall back to the legacy articles_limit column
        return $this->billingPlan?->articles_per_month ?? $this->attributes['articles_limit'] ?? 0;
    }

    public function getPlanLimits(): array
    {
        return match ($this->plan) {
            'starter' => ['articles' => 10, 'sites' => 1],
            'pro' => ['articles' => 30, 'sites' => 3],
            'agency' => ['articles' => 100, 'sites' => -1], // -1 = unlimited
            default => ['articles' => 10, 'sites' => 1],
        };
    }
}
