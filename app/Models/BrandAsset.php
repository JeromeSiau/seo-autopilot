<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BrandAsset extends Model
{
    use HasFactory;

    public const TYPE_PILLAR_PAGE = 'pillar_page';
    public const TYPE_OFFER = 'offer';
    public const TYPE_FAQ = 'faq';
    public const TYPE_PROOF = 'proof';
    public const TYPE_CASE_STUDY = 'case_study';
    public const TYPE_STYLE_SAMPLE = 'style_sample';
    public const TYPE_CLAIM = 'claim';
    public const TYPE_POLICY = 'policy';

    public const TYPES = [
        self::TYPE_PILLAR_PAGE,
        self::TYPE_OFFER,
        self::TYPE_FAQ,
        self::TYPE_PROOF,
        self::TYPE_CASE_STUDY,
        self::TYPE_STYLE_SAMPLE,
        self::TYPE_CLAIM,
        self::TYPE_POLICY,
    ];

    protected $fillable = [
        'site_id',
        'type',
        'title',
        'source_url',
        'content',
        'priority',
        'is_active',
        'metadata',
    ];

    protected $casts = [
        'priority' => 'integer',
        'is_active' => 'boolean',
        'metadata' => 'array',
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
