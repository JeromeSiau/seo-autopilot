<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ArticleCitation extends Model
{
    use HasFactory;

    protected $fillable = [
        'article_id',
        'source_type',
        'title',
        'url',
        'domain',
        'excerpt',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }
}
