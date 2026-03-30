<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ArticleScore extends Model
{
    use HasFactory;

    protected $fillable = [
        'article_id',
        'readiness_score',
        'brand_fit_score',
        'seo_score',
        'citation_score',
        'internal_link_score',
        'fact_confidence_score',
        'warnings',
        'checklist',
    ];

    protected $casts = [
        'readiness_score' => 'integer',
        'brand_fit_score' => 'integer',
        'seo_score' => 'integer',
        'citation_score' => 'integer',
        'internal_link_score' => 'integer',
        'fact_confidence_score' => 'integer',
        'warnings' => 'array',
        'checklist' => 'array',
    ];

    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }
}
