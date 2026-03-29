<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HostedPage extends Model
{
    use HasFactory;

    public const KIND_HOME = 'home';
    public const KIND_ABOUT = 'about';
    public const KIND_LEGAL = 'legal';

    public const KINDS = [
        self::KIND_HOME,
        self::KIND_ABOUT,
        self::KIND_LEGAL,
    ];

    protected $fillable = [
        'site_id',
        'kind',
        'title',
        'body_html',
        'meta_title',
        'meta_description',
        'is_published',
    ];

    protected $casts = [
        'is_published' => 'boolean',
    ];

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }
}
