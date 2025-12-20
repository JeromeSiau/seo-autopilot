<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Notification extends Model
{
    protected $fillable = [
        'user_id',
        'site_id',
        'type',
        'title',
        'message',
        'action_url',
        'read_at',
    ];

    protected $casts = [
        'read_at' => 'datetime',
    ];

    public const TYPE_REVIEW_NEEDED = 'review_needed';
    public const TYPE_PUBLISHED = 'published';
    public const TYPE_PUBLISH_FAILED = 'publish_failed';
    public const TYPE_QUOTA_WARNING = 'quota_warning';
    public const TYPE_KEYWORDS_FOUND = 'keywords_found';

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function markAsRead(): void
    {
        $this->update(['read_at' => now()]);
    }

    public function scopeUnread($query)
    {
        return $query->whereNull('read_at');
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public static function notify(
        int $userId,
        string $type,
        string $title,
        ?string $message = null,
        ?int $siteId = null,
        ?string $actionUrl = null
    ): self {
        return self::create([
            'user_id' => $userId,
            'site_id' => $siteId,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'action_url' => $actionUrl,
        ]);
    }
}
