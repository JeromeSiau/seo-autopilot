<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HostedExportRun extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';
    public const STATUS_RUNNING = 'running';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'site_hosting_id',
        'status',
        'target_path',
        'size_bytes',
        'error_message',
        'metadata',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'size_bytes' => 'integer',
        'metadata' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function hosting(): BelongsTo
    {
        return $this->belongsTo(SiteHosting::class, 'site_hosting_id');
    }
}
