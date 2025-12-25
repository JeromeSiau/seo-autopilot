<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Integration extends Model
{
    protected $fillable = [
        'team_id',
        'site_id',
        'type',
        'name',
        'credentials',
        'is_active',
    ];

    protected $casts = [
        'credentials' => 'encrypted:array',
        'is_active' => 'boolean',
        'last_used_at' => 'datetime',
    ];

    protected $hidden = [
        'credentials',
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function markAsUsed(): void
    {
        $this->update(['last_used_at' => now()]);
    }

    public function getCredential(string $key): ?string
    {
        return $this->credentials[$key] ?? null;
    }

    public function isWordPress(): bool
    {
        return $this->type === 'wordpress';
    }

    public function isWebflow(): bool
    {
        return $this->type === 'webflow';
    }

    public function isShopify(): bool
    {
        return $this->type === 'shopify';
    }

    public function isGhost(): bool
    {
        return $this->type === 'ghost';
    }
}
