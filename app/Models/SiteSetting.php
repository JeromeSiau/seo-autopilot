<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SiteSetting extends Model
{
    protected $fillable = [
        'site_id',
        'autopilot_enabled',
        'articles_per_week',
        'publish_days',
        'auto_publish',
    ];

    protected $casts = [
        'autopilot_enabled' => 'boolean',
        'articles_per_week' => 'integer',
        'publish_days' => 'array',
        'auto_publish' => 'boolean',
    ];

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function canPublishToday(): bool
    {
        $today = strtolower(now()->format('D'));
        return in_array($today, $this->publish_days ?? []);
    }

    public function getDefaultArticlesPerWeek(): int
    {
        $team = $this->site->team;
        $monthlyLimit = $team->articles_limit ?? 30;

        return match(true) {
            $monthlyLimit <= 10 => 2,
            $monthlyLimit <= 30 => 7,
            default => 25,
        };
    }
}
