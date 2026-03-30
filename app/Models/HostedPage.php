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
    public const KIND_CUSTOM = 'custom';

    public const SYSTEM_KINDS = [
        self::KIND_HOME,
        self::KIND_ABOUT,
        self::KIND_LEGAL,
    ];

    public const KINDS = [
        ...self::SYSTEM_KINDS,
        self::KIND_CUSTOM,
    ];

    public const SECTION_RICH_TEXT = 'rich_text';
    public const SECTION_CALLOUT = 'callout';
    public const SECTION_FEATURE_GRID = 'feature_grid';
    public const SECTION_FAQ = 'faq';
    public const SECTION_HERO = 'hero';
    public const SECTION_TESTIMONIAL_GRID = 'testimonial_grid';
    public const SECTION_STAT_GRID = 'stat_grid';

    public const SECTION_TYPES = [
        self::SECTION_RICH_TEXT,
        self::SECTION_CALLOUT,
        self::SECTION_FEATURE_GRID,
        self::SECTION_FAQ,
        self::SECTION_HERO,
        self::SECTION_TESTIMONIAL_GRID,
        self::SECTION_STAT_GRID,
    ];

    protected $fillable = [
        'site_id',
        'kind',
        'slug',
        'title',
        'navigation_label',
        'body_html',
        'sections',
        'meta_title',
        'meta_description',
        'canonical_url',
        'social_title',
        'social_description',
        'social_image_asset_id',
        'social_image_url',
        'robots_noindex',
        'schema_enabled',
        'show_in_sitemap',
        'show_in_feed',
        'breadcrumbs_enabled',
        'show_in_navigation',
        'sort_order',
        'is_published',
    ];

    protected $casts = [
        'sections' => 'array',
        'show_in_navigation' => 'boolean',
        'sort_order' => 'integer',
        'is_published' => 'boolean',
        'robots_noindex' => 'boolean',
        'schema_enabled' => 'boolean',
        'show_in_sitemap' => 'boolean',
        'show_in_feed' => 'boolean',
        'breadcrumbs_enabled' => 'boolean',
    ];

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function socialImageAsset(): BelongsTo
    {
        return $this->belongsTo(HostedAsset::class, 'social_image_asset_id');
    }

    public function isCustom(): bool
    {
        return $this->kind === self::KIND_CUSTOM;
    }

    public function isSystemPage(): bool
    {
        return in_array($this->kind, self::SYSTEM_KINDS, true);
    }

    public function path(): string
    {
        return match ($this->kind) {
            self::KIND_HOME => '/',
            self::KIND_ABOUT => '/about',
            self::KIND_LEGAL => '/legal',
            default => '/' . ltrim((string) $this->slug, '/'),
        };
    }

    public function exportPath(): ?string
    {
        return match ($this->kind) {
            self::KIND_HOME => null,
            self::KIND_ABOUT => 'about/index.html',
            self::KIND_LEGAL => 'legal/index.html',
            default => blank($this->slug) ? null : trim((string) $this->slug, '/') . '/index.html',
        };
    }

    public function navigationLabel(): string
    {
        return $this->navigation_label ?: $this->title;
    }
}
