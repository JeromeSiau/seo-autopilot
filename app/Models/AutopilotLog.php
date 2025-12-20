<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AutopilotLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'site_id',
        'event_type',
        'payload',
        'created_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'created_at' => 'datetime',
    ];

    public const TYPE_KEYWORD_DISCOVERED = 'keyword_discovered';
    public const TYPE_ARTICLE_GENERATED = 'article_generated';
    public const TYPE_ARTICLE_PUBLISHED = 'article_published';
    public const TYPE_PUBLISH_FAILED = 'publish_failed';
    public const TYPE_KEYWORDS_IMPORTED = 'keywords_imported';

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public static function log(int $siteId, string $eventType, array $payload = []): self
    {
        return self::create([
            'site_id' => $siteId,
            'event_type' => $eventType,
            'payload' => $payload,
            'created_at' => now(),
        ]);
    }

    public function scopeRecent($query, int $limit = 20)
    {
        return $query->orderByDesc('created_at')->limit($limit);
    }

    public function scopeForSite($query, int $siteId)
    {
        return $query->where('site_id', $siteId);
    }
}
