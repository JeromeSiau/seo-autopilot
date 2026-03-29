<?php

namespace App\Services\Hosted;

use App\Models\Site;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

class HostedSiteResolver
{
    public function resolve(Request|string|null $requestOrHost): ?Site
    {
        $host = $requestOrHost instanceof Request
            ? $requestOrHost->getHost()
            : (string) $requestOrHost;

        $host = $this->normalizeHost($host);

        if ($host === '' || $this->isPrimaryDomain($host)) {
            return null;
        }

        return Site::query()
            ->where('mode', Site::MODE_HOSTED)
            ->whereHas('hosting', fn ($query) => $query
                ->where('staging_domain', $host)
                ->orWhere('custom_domain', $host)
            )
            ->with(['hosting', 'hostedPages'])
            ->first();
    }

    public function isPrimaryDomain(string $host): bool
    {
        $host = $this->normalizeHost($host);
        $domains = array_filter(array_map(
            fn (string $value) => $this->normalizeHost($value),
            Arr::wrap(config('services.hosted.primary_domains', []))
        ));

        return in_array($host, $domains, true);
    }

    private function normalizeHost(string $host): string
    {
        $host = strtolower(trim($host));

        if (str_contains($host, ':')) {
            $host = explode(':', $host, 2)[0];
        }

        return $host;
    }
}
