<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CampaignRun extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_DISPATCHED = 'dispatched';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'site_id',
        'created_by',
        'name',
        'status',
        'input_type',
        'payload',
        'processed_count',
        'succeeded_count',
        'failed_count',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'processed_count' => 'integer',
        'succeeded_count' => 'integer',
        'failed_count' => 'integer',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
