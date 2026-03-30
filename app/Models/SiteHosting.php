<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SiteHosting extends Model
{
    use HasFactory;

    public const DOMAIN_STATUS_NONE = 'none';
    public const DOMAIN_STATUS_DNS_PENDING = 'dns_pending';
    public const DOMAIN_STATUS_TENANT_PENDING = 'tenant_pending';
    public const DOMAIN_STATUS_SSL_PENDING = 'ssl_pending';
    public const DOMAIN_STATUS_ACTIVE = 'active';
    public const DOMAIN_STATUS_ERROR = 'error';

    public const SSL_STATUS_NONE = 'none';
    public const SSL_STATUS_PENDING = 'pending';
    public const SSL_STATUS_ACTIVE = 'active';
    public const SSL_STATUS_ERROR = 'error';

    public const TEMPLATE_EDITORIAL = 'editorial';
    public const TEMPLATE_MAGAZINE = 'magazine';
    public const TEMPLATE_MINIMAL = 'minimal';

    protected $fillable = [
        'site_id',
        'staging_domain',
        'custom_domain',
        'canonical_domain',
        'domain_status',
        'ssl_status',
        'template_key',
        'theme_settings',
        'ploi_tenant_id',
        'staging_certificate_requested_at',
        'custom_domain_verified_at',
        'custom_certificate_requested_at',
        'last_error',
        'last_exported_at',
    ];

    protected $casts = [
        'theme_settings' => 'array',
        'staging_certificate_requested_at' => 'datetime',
        'custom_domain_verified_at' => 'datetime',
        'custom_certificate_requested_at' => 'datetime',
        'last_exported_at' => 'datetime',
    ];

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function exportRuns(): HasMany
    {
        return $this->hasMany(HostedExportRun::class, 'site_hosting_id')->latest('created_at');
    }

    public function deployEvents(): HasMany
    {
        return $this->hasMany(HostedDeployEvent::class, 'site_hosting_id')->latest('occurred_at');
    }

    public function getEffectiveDomainAttribute(): ?string
    {
        return $this->canonical_domain
            ?? $this->custom_domain
            ?? $this->staging_domain;
    }
}
