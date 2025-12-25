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
        'plan',
        'articles_limit',
    ];

    protected $casts = [
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

    public function brandVoices(): HasMany
    {
        return $this->hasMany(BrandVoice::class);
    }

    public function getArticlesUsedThisMonthAttribute(): int
    {
        return Article::whereIn('site_id', $this->sites->pluck('id'))
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->count();
    }

    public function canGenerateArticle(): bool
    {
        return $this->articles_used_this_month < $this->articles_limit;
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
