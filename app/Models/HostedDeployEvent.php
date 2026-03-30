<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HostedDeployEvent extends Model
{
    use HasFactory;

    public const STATUS_INFO = 'info';
    public const STATUS_SUCCESS = 'success';
    public const STATUS_WARNING = 'warning';
    public const STATUS_ERROR = 'error';

    protected $fillable = [
        'site_hosting_id',
        'type',
        'status',
        'title',
        'message',
        'metadata',
        'occurred_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'occurred_at' => 'datetime',
    ];

    public function hosting(): BelongsTo
    {
        return $this->belongsTo(SiteHosting::class, 'site_hosting_id');
    }
}
