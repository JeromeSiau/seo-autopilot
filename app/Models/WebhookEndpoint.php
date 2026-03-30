<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WebhookEndpoint extends Model
{
    protected $fillable = [
        'team_id',
        'url',
        'events',
        'secret',
        'is_active',
        'last_error',
        'last_delivered_at',
    ];

    protected $casts = [
        'events' => 'array',
        'secret' => 'encrypted',
        'is_active' => 'boolean',
        'last_delivered_at' => 'datetime',
    ];

    protected $hidden = [
        'secret',
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(WebhookDelivery::class);
    }
}
