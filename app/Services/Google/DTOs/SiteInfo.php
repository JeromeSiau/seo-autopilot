<?php

namespace App\Services\Google\DTOs;

readonly class SiteInfo
{
    public function __construct(
        public string $siteUrl,
        public string $permissionLevel,
    ) {}

    public function toArray(): array
    {
        return [
            'site_url' => $this->siteUrl,
            'permission_level' => $this->permissionLevel,
        ];
    }

    public static function fromApi(array $data): self
    {
        return new self(
            siteUrl: $data['siteUrl'],
            permissionLevel: $data['permissionLevel'],
        );
    }

    /**
     * Get the domain from the site URL.
     */
    public function getDomain(): string
    {
        $url = $this->siteUrl;

        // Handle domain properties (sc-domain:example.com)
        if (str_starts_with($url, 'sc-domain:')) {
            return substr($url, 10);
        }

        // Handle URL properties (https://example.com/)
        return parse_url($url, PHP_URL_HOST) ?? $url;
    }

    /**
     * Check if this is a domain property.
     */
    public function isDomainProperty(): bool
    {
        return str_starts_with($this->siteUrl, 'sc-domain:');
    }
}
