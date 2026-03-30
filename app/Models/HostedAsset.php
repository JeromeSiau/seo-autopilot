<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class HostedAsset extends Model
{
    use HasFactory;

    public const TYPE_LOGO = 'logo';
    public const TYPE_SOCIAL = 'social';
    public const TYPE_IMAGE = 'image';
    public const TYPE_DOCUMENT = 'document';

    public const TYPES = [
        self::TYPE_LOGO,
        self::TYPE_SOCIAL,
        self::TYPE_IMAGE,
        self::TYPE_DOCUMENT,
    ];

    protected $fillable = [
        'site_id',
        'type',
        'name',
        'disk',
        'path',
        'mime_type',
        'size_bytes',
        'alt_text',
        'source_url',
        'is_active',
    ];

    protected $casts = [
        'size_bytes' => 'integer',
        'is_active' => 'boolean',
    ];

    protected $appends = [
        'public_url',
        'export_path',
    ];

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function getPublicUrlAttribute(): string
    {
        return '/storage/' . ltrim($this->path, '/');
    }

    public function getExportPathAttribute(): string
    {
        return 'storage/' . ltrim($this->path, '/');
    }

    public function existsOnDisk(): bool
    {
        return Storage::disk($this->disk)->exists($this->path);
    }
}
