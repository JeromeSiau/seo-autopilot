<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HostedAuthor extends Model
{
    use HasFactory;

    protected $fillable = [
        'site_id',
        'name',
        'slug',
        'bio',
        'avatar_url',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function articles(): HasMany
    {
        return $this->hasMany(Article::class, 'hosted_author_id');
    }

    public function archivePath(): string
    {
        return '/authors/' . $this->slug;
    }

    public function exportPath(): string
    {
        return 'authors/' . $this->slug . '/index.html';
    }
}
