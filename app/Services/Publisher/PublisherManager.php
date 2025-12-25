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

    /**
     * Validate credentials for a publisher type.
     */
    public function validateCredentials(string $type, array $credentials): array
    {
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

        // Validate Ghost API key format
        if ($type === 'ghost' && !empty($credentials['admin_api_key'])) {
            if (!preg_match('/^[a-f0-9]{24}:[a-f0-9]{64}$/', $credentials['admin_api_key'])) {
                $errors['admin_api_key'] = 'Invalid API key format. Expected: {24 hex chars}:{64 hex chars}';
            }
        }

        return $errors;
    }
}
