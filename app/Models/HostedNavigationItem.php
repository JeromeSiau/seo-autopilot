<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HostedNavigationItem extends Model
{
    use HasFactory;

    public const PLACEMENT_HEADER = 'header';
    public const PLACEMENT_FOOTER = 'footer';

    public const PLACEMENTS = [
        self::PLACEMENT_HEADER,
        self::PLACEMENT_FOOTER,
    ];

    public const TYPE_PATH = 'path';
    public const TYPE_URL = 'url';

    public const TYPES = [
        self::TYPE_PATH,
        self::TYPE_URL,
    ];

    protected $fillable = [
        'site_id',
        'placement',
        'type',
        'label',
        'path',
        'url',
        'open_in_new_tab',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'open_in_new_tab' => 'boolean',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function target(): string
    {
        return $this->type === self::TYPE_URL
            ? (string) $this->url
            : (string) $this->path;
    }
}
