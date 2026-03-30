<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HostedRedirect extends Model
{
    use HasFactory;

    public const STATUS_PERMANENT = 301;
    public const STATUS_TEMPORARY = 302;

    public const STATUSES = [
        self::STATUS_PERMANENT,
        self::STATUS_TEMPORARY,
    ];

    protected $fillable = [
        'site_id',
        'source_path',
        'destination_url',
        'http_status',
        'hit_count',
        'last_used_at',
    ];

    protected $casts = [
        'http_status' => 'integer',
        'hit_count' => 'integer',
        'last_used_at' => 'datetime',
    ];

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }
}
