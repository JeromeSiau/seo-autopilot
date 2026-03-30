<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebhookDelivery extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_RETRYING = 'retrying';
    public const STATUS_SUCCESS = 'success';
    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'webhook_endpoint_id',
        'event_name',
        'payload',
        'status',
        'attempt_number',
        'max_attempts',
        'next_retry_at',
        'response_code',
        'response_body',
        'error_message',
        'attempted_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'attempt_number' => 'integer',
        'max_attempts' => 'integer',
        'next_retry_at' => 'datetime',
        'attempted_at' => 'datetime',
    ];

    public function endpoint(): BelongsTo
    {
        return $this->belongsTo(WebhookEndpoint::class, 'webhook_endpoint_id');
    }
}
