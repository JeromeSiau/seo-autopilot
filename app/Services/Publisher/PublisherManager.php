<?php

namespace App\Services\Publisher;

use App\Models\Integration;
use App\Services\Publisher\Contracts\PublisherInterface;
use App\Services\Publisher\Providers\ShopifyPublisher;
use App\Services\Publisher\Providers\WebflowPublisher;
use App\Services\Publisher\Providers\WordPressPublisher;
use App\Services\Publisher\Providers\GhostPublisher;

class PublisherManager
{
    private const OPTIONAL_FIELDS = [
        'shopify' => ['blog_id'],
    ];

    private const SECRET_FIELDS = [
        'wordpress' => ['app_password'],
        'webflow' => ['api_token'],
        'shopify' => ['access_token'],
        'ghost' => ['admin_api_key'],
    ];

    /**
     * Get a publisher instance for an integration.
     */
    public function getPublisher(Integration $integration): PublisherInterface
    {
        return match ($integration->type) {
            'wordpress' => new WordPressPublisher($integration),
            'webflow' => new WebflowPublisher($integration),
            'shopify' => new ShopifyPublisher($integration),
            'ghost' => new GhostPublisher($integration),
            default => throw new \InvalidArgumentException("Unknown integration type: {$integration->type}"),
        };
    }

    /**
     * Get supported publisher types.
     */
    public function getSupportedTypes(): array
    {
        return ['wordpress', 'webflow', 'shopify', 'ghost'];
    }

    /**
     * Get required credentials for a publisher type.
     */
    public function getRequiredCredentials(string $type): array
    {
        return match ($type) {
            'wordpress' => ['site_url', 'username', 'app_password'],
            'webflow' => ['api_token', 'site_id', 'collection_id'],
            'shopify' => ['shop_domain', 'access_token', 'blog_id'],
            'ghost' => ['blog_url', 'admin_api_key'],
            default => [],
        };
    }

    public function getAllowedCredentials(string $type): array
    {
        return $this->getRequiredCredentials($type);
    }

    public function getSecretFields(string $type): array
    {
        return self::SECRET_FIELDS[$type] ?? [];
    }

    public function normalizeCredentials(string $type, array $credentials): array
    {
        $credentials = match ($type) {
            'wordpress' => [
                'site_url' => $credentials['site_url'] ?? $credentials['url'] ?? null,
                'username' => $credentials['username'] ?? null,
                'app_password' => $credentials['app_password'] ?? $credentials['password'] ?? null,
            ],
            'webflow' => [
                'api_token' => $credentials['api_token'] ?? null,
                'site_id' => $credentials['site_id'] ?? null,
                'collection_id' => $credentials['collection_id'] ?? null,
            ],
            'shopify' => [
                'shop_domain' => $credentials['shop_domain'] ?? null,
                'access_token' => $credentials['access_token'] ?? $credentials['api_token'] ?? null,
                'blog_id' => $credentials['blog_id'] ?? null,
            ],
            'ghost' => [
                'blog_url' => $credentials['blog_url'] ?? $credentials['url'] ?? null,
                'admin_api_key' => $credentials['admin_api_key'] ?? null,
            ],
            default => [],
        };

        return collect($credentials)
            ->map(function ($value, string $key) use ($type) {
                if (!is_string($value)) {
                    return $value;
                }

                $value = trim($value);

                if ($value === '') {
                    if (in_array($key, self::OPTIONAL_FIELDS[$type] ?? [], true)) {
                        return null;
                    }

                    return null;
                }

                if (in_array($key, ['site_url', 'blog_url'], true) && $value !== '') {
                    return rtrim($value, '/');
                }

                if ($key === 'shop_domain' && $value !== '') {
                    $value = preg_replace('#^https?://#', '', $value);
                    return rtrim($value, '/');
                }

                return $value;
            })
            ->filter(fn ($value, string $key) => $value !== null || in_array($key, self::OPTIONAL_FIELDS[$type] ?? [], true))
            ->all();
    }

    public function mergeCredentials(string $type, array $existing, array $incoming): array
    {
        $allowed = $this->getAllowedCredentials($type);
        $secrets = $this->getSecretFields($type);
        $optional = self::OPTIONAL_FIELDS[$type] ?? [];
        $existing = $this->normalizeCredentials($type, $existing);
        $incoming = $this->normalizeCredentials($type, $incoming);
        $merged = [];

        foreach ($allowed as $field) {
            if (in_array($field, $secrets, true)) {
                $merged[$field] = $incoming[$field] ?? $existing[$field] ?? null;
                continue;
            }

            if (array_key_exists($field, $incoming)) {
                $merged[$field] = $incoming[$field];
                continue;
            }

            $merged[$field] = $existing[$field] ?? null;
        }

        return collect($merged)
            ->filter(fn ($value, string $key) => $value !== null || in_array($key, $optional, true))
            ->all();
    }

    public function getEditableCredentials(string $type, array $credentials): array
    {
        $credentials = $this->normalizeCredentials($type, $credentials);

        return match ($type) {
            'wordpress' => [
                'site_url' => $credentials['site_url'] ?? '',
                'username' => $credentials['username'] ?? '',
            ],
            'webflow' => [
                'site_id' => $credentials['site_id'] ?? '',
                'collection_id' => $credentials['collection_id'] ?? '',
            ],
            'shopify' => [
                'shop_domain' => $credentials['shop_domain'] ?? '',
                'blog_id' => $credentials['blog_id'] ?? '',
            ],
            'ghost' => [
                'blog_url' => $credentials['blog_url'] ?? '',
            ],
            default => [],
        };
    }

    public function getCredentialPresence(string $type, array $credentials): array
    {
        $credentials = $this->normalizeCredentials($type, $credentials);

        return collect($this->getSecretFields($type))
            ->mapWithKeys(fn (string $field) => ["has_{$field}" => !empty($credentials[$field])])
            ->all();
    }

    /**
     * Validate credentials for a publisher type.
     */
    public function validateCredentials(string $type, array $credentials): array
    {
        $credentials = $this->normalizeCredentials($type, $credentials);
        $required = $this->getRequiredCredentials($type);
        $errors = [];

        foreach ($required as $field) {
            // blog_id is optional for Shopify
            if ($field === 'blog_id' && $type === 'shopify') {
                continue;
            }

            if (empty($credentials[$field])) {
                $errors[$field] = "The {$field} field is required.";
            }
        }

        if (!empty($credentials['site_url']) && filter_var($credentials['site_url'], FILTER_VALIDATE_URL) === false) {
            $errors['site_url'] = 'The site URL must be a valid URL.';
        }

        if (!empty($credentials['blog_url']) && filter_var($credentials['blog_url'], FILTER_VALIDATE_URL) === false) {
            $errors['blog_url'] = 'The blog URL must be a valid URL.';
        }

        if (!empty($credentials['shop_domain']) && str_contains($credentials['shop_domain'], '/')) {
            $errors['shop_domain'] = 'The shop domain must not include a path.';
        }

        // Validate Ghost API key format
        if ($type === 'ghost' && !empty($credentials['admin_api_key'])) {
            if (!preg_match('/^[a-f0-9]{24}:[a-f0-9]{64}$/', $credentials['admin_api_key'])) {
                $errors['admin_api_key'] = 'Invalid API key format. Expected: {24 hex chars}:{64 hex chars}';
            }
        }

        return $errors;
    }
}
