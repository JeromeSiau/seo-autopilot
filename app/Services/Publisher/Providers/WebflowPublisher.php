<?php

namespace App\Services\Publisher\Providers;

use App\Models\Integration;
use App\Services\Publisher\Contracts\PublisherInterface;
use App\Services\Publisher\DTOs\PublishRequest;
use App\Services\Publisher\DTOs\PublishResult;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WebflowPublisher implements PublisherInterface
{
    private const API_URL = 'https://api.webflow.com/v2';

    private string $apiToken;
    private string $siteId;
    private string $collectionId;

    public function __construct(Integration $integration)
    {
        $credentials = $integration->credentials;

        $this->apiToken = $credentials['api_token'] ?? '';
        $this->siteId = $credentials['site_id'] ?? '';
        $this->collectionId = $credentials['collection_id'] ?? '';

        if (!$this->apiToken || !$this->siteId || !$this->collectionId) {
            throw new \InvalidArgumentException('Webflow credentials incomplete');
        }
    }

    public function publish(PublishRequest $request): PublishResult
    {
        try {
            // Create the collection item
            $itemData = [
                'isArchived' => false,
                'isDraft' => $request->status !== 'publish',
                'fieldData' => [
                    'name' => $request->title,
                    'slug' => $request->slug ?? $this->generateSlug($request->title),
                    'post-body' => $request->content,
                    'post-summary' => $request->excerpt,
                ],
            ];

            // Add featured image if provided
            if ($request->featuredImageUrl) {
                $itemData['fieldData']['main-image'] = [
                    'url' => $request->featuredImageUrl,
                ];
            }

            // Add SEO fields
            if ($request->metaTitle) {
                $itemData['fieldData']['meta-title'] = $request->metaTitle;
            }
            if ($request->metaDescription) {
                $itemData['fieldData']['meta-description'] = $request->metaDescription;
            }

            $response = $this->request(
                'POST',
                "/collections/{$this->collectionId}/items",
                $itemData
            );

            $itemId = $response['id'] ?? null;

            if (!$itemId) {
                return PublishResult::failure('Failed to create Webflow item');
            }

            // Publish the item if status is publish
            if ($request->status === 'publish') {
                $this->publishItem($itemId);
            }

            // Get the published URL
            $slug = $response['fieldData']['slug'] ?? $request->slug;
            $siteUrl = $this->getSiteUrl();
            $postUrl = "{$siteUrl}/blog/{$slug}";

            Log::info('Webflow article published', [
                'item_id' => $itemId,
                'url' => $postUrl,
            ]);

            return PublishResult::success($postUrl, $itemId);
        } catch (\Exception $e) {
            Log::error('Webflow publish failed', [
                'error' => $e->getMessage(),
            ]);
            return PublishResult::failure($e->getMessage());
        }
    }

    public function update(string $remoteId, PublishRequest $request): PublishResult
    {
        try {
            $itemData = [
                'fieldData' => [
                    'name' => $request->title,
                    'post-body' => $request->content,
                    'post-summary' => $request->excerpt,
                ],
            ];

            if ($request->metaTitle) {
                $itemData['fieldData']['meta-title'] = $request->metaTitle;
            }
            if ($request->metaDescription) {
                $itemData['fieldData']['meta-description'] = $request->metaDescription;
            }

            $response = $this->request(
                'PATCH',
                "/collections/{$this->collectionId}/items/{$remoteId}",
                $itemData
            );

            $slug = $response['fieldData']['slug'] ?? '';
            $siteUrl = $this->getSiteUrl();
            $postUrl = "{$siteUrl}/blog/{$slug}";

            // Re-publish
            $this->publishItem($remoteId);

            return PublishResult::success($postUrl, $remoteId);
        } catch (\Exception $e) {
            return PublishResult::failure($e->getMessage());
        }
    }

    public function delete(string $remoteId): bool
    {
        try {
            $this->request(
                'DELETE',
                "/collections/{$this->collectionId}/items/{$remoteId}"
            );
            return true;
        } catch (\Exception $e) {
            Log::warning('Webflow delete failed', ['error' => $e->getMessage()]);
            return false;
        }
    }

    public function testConnection(): bool
    {
        try {
            $response = $this->request('GET', "/sites/{$this->siteId}");
            return isset($response['id']);
        } catch (\Exception $e) {
            return false;
        }
    }

    public function getType(): string
    {
        return 'webflow';
    }

    public function getCategories(): array
    {
        // Webflow uses collections, return available collections
        try {
            $response = $this->request('GET', "/sites/{$this->siteId}/collections");

            return array_map(fn($col) => [
                'id' => $col['id'],
                'name' => $col['displayName'],
                'slug' => $col['slug'],
            ], $response['collections'] ?? []);
        } catch (\Exception $e) {
            return [];
        }
    }

    private function publishItem(string $itemId): void
    {
        $this->request(
            'POST',
            "/collections/{$this->collectionId}/items/publish",
            ['itemIds' => [$itemId]]
        );
    }

    private function getSiteUrl(): string
    {
        try {
            $response = $this->request('GET', "/sites/{$this->siteId}");
            $domains = $response['customDomains'] ?? [];

            if (!empty($domains)) {
                return 'https://' . $domains[0]['url'];
            }

            return 'https://' . ($response['shortName'] ?? '') . '.webflow.io';
        } catch (\Exception $e) {
            return '';
        }
    }

    private function request(string $method, string $endpoint, array $data = []): array
    {
        $url = self::API_URL . $endpoint;

        $request = Http::withToken($this->apiToken)
            ->timeout(30);

        $response = match (strtoupper($method)) {
            'GET' => $request->get($url),
            'POST' => $request->post($url, $data),
            'PATCH' => $request->patch($url, $data),
            'DELETE' => $request->delete($url),
            default => throw new \InvalidArgumentException("Unsupported method: {$method}"),
        };

        if (!$response->successful()) {
            throw new \RuntimeException("Webflow API error: " . $response->body());
        }

        return $response->json() ?? [];
    }

    private function generateSlug(string $title): string
    {
        return strtolower(preg_replace('/[^a-z0-9]+/i', '-', $title));
    }
}
