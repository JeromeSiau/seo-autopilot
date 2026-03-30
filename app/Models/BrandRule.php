<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BrandRule extends Model
{
    use HasFactory;

    public const CATEGORY_MUST_INCLUDE = 'must_include';
    public const CATEGORY_MUST_AVOID = 'must_avoid';
    public const CATEGORY_TONE = 'tone';
    public const CATEGORY_PERSONA = 'persona';
    public const CATEGORY_CTA = 'cta';
    public const CATEGORY_COMPLIANCE = 'compliance';

    public const CATEGORIES = [
        self::CATEGORY_MUST_INCLUDE,
        self::CATEGORY_MUST_AVOID,
        self::CATEGORY_TONE,
        self::CATEGORY_PERSONA,
        self::CATEGORY_CTA,
        self::CATEGORY_COMPLIANCE,
    ];

    protected $fillable = [
        'site_id',
        'category',
        'label',
        'value',
        'priority',
        'is_active',
    ];

    protected $casts = [
        'priority' => 'integer',
        'is_active' => 'boolean',
    ];

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
