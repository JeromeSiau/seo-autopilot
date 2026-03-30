<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class HostedTag extends Model
{
    use HasFactory;

    protected $fillable = [
        'site_id',
        'name',
        'slug',
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

    public function articles(): BelongsToMany
    {
        return $this->belongsToMany(Article::class, 'article_hosted_tag')
            ->withTimestamps();
    }

    public function archivePath(): string
    {
        return '/tags/' . $this->slug;
    }

    public function exportPath(): string
    {
        return 'tags/' . $this->slug . '/index.html';
    }
}
