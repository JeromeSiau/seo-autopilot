<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HostedCategory extends Model
{
    use HasFactory;

    protected $fillable = [
        'site_id',
        'name',
        'slug',
        'description',
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
        return $this->hasMany(Article::class, 'hosted_category_id');
    }

    public function archivePath(): string
    {
        return '/categories/' . $this->slug;
    }

    public function exportPath(): string
    {
        return 'categories/' . $this->slug . '/index.html';
    }
}
