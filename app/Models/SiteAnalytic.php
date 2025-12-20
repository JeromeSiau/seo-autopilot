<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SiteAnalytic extends Model
{
    protected $fillable = [
        'site_id',
        'date',
        'impressions',
        'clicks',
        'position',
        'ctr',
    ];

    protected $casts = [
        'date' => 'date',
        'impressions' => 'integer',
        'clicks' => 'integer',
        'position' => 'decimal:2',
        'ctr' => 'decimal:2',
    ];

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }
}
