<?php

namespace App\Services\Publisher\Providers;

use App\Models\Integration;
use App\Services\Publisher\Contracts\PublisherInterface;
use App\Services\Publisher\DTOs\PublishRequest;
use App\Services\Publisher\DTOs\PublishResult;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ShopifyPublisher implements PublisherInterface
{
    private string $shopDomain;
    private string $accessToken;
    private ?string $blogId;

    public function __construct(Integration $integration)
    {
        $credentials = $integration->credentials;

        $this->shopDomain = rtrim($credentials['shop_domain'] ?? '', '/');
        $this->accessToken = $credentials['access_token'] ?? '';
        $this->blogId = $credentials['blog_id'] ?? null;

        if (!$this->shopDomain || !$this->accessToken) {
            throw new \InvalidArgumentException('Shopify credentials incomplete');
        }

        // Remove .myshopify.com if present and add it back
        $this->shopDomain = preg_replace('/\.myshopify\.com$/', '', $this->shopDomain);
        $this->shopDomain .= '.myshopify.com';
    }

    public function publish(PublishRequest $request): PublishResult
    {
        try {
            // Get or create blog
            $blogId = $this->blogId ?? $this->getDefaultBlogId();

            if (!$blogId) {
                return PublishResult::failure('No blog found in Shopify store');
            }

            // Create the article
            $articleData = [
                'article' => [
                    'title' => $request->title,
                    'body_html' => $request->content,
                    'handle' => $request->slug ?? $this->generateHandle($request->title),
                    'summary_html' => $request->excerpt,
                    'published' => $request->status === 'publish',
                ],
            ];

            // Add featured image
            if ($request->featuredImageUrl) {
                $articleData['article']['image'] = [
                    'src' => $request->featuredImageUrl,
                ];
            }

            // Add tags
            if (!empty($request->tags)) {
                $articleData['article']['tags'] = implode(', ', $request->tags);
            }

            // Add author
            if ($request->authorName) {
                $articleData['article']['author'] = $request->authorName;
            }

            $response = $this->request(
                'POST',
                "/admin/api/2024-01/blogs/{$blogId}/articles.json",
                $articleData
            );

            $article = $response['article'] ?? null;

            if (!$article) {
                return PublishResult::failure('Failed to create Shopify article');
            }

            $articleId = $article['id'];
            $handle = $article['handle'];

            // Set SEO metafields
            $this->setSeoMetafields($articleId, $request);

            // Build the URL
            $blogHandle = $this->getBlogHandle($blogId);
            $postUrl = "https://{$this->shopDomain}/blogs/{$blogHandle}/{$handle}";

            Log::info('Shopify article published', [
                'article_id' => $articleId,
                'url' => $postUrl,
            ]);

            return PublishResult::success($postUrl, (string) $articleId, [
                'blog_id' => $blogId,
            ]);
        } catch (\Exception $e) {
            Log::error('Shopify publish failed', [
                'error' => $e->getMessage(),
            ]);
            return PublishResult::failure($e->getMessage());
        }
    }

    public function update(string $remoteId, PublishRequest $request): PublishResult
    {
        try {
            $blogId = $this->blogId ?? $this->getDefaultBlogId();

            $articleData = [
                'article' => [
                    'id' => (int) $remoteId,
                    'title' => $request->title,
                    'body_html' => $request->content,
                    'summary_html' => $request->excerpt,
                ],
            ];

            $response = $this->request(
                'PUT',
                "/admin/api/2024-01/blogs/{$blogId}/articles/{$remoteId}.json",
                $articleData
            );

            $article = $response['article'] ?? null;
            $handle = $article['handle'] ?? '';
            $blogHandle = $this->getBlogHandle($blogId);
            $postUrl = "https://{$this->shopDomain}/blogs/{$blogHandle}/{$handle}";

            $this->setSeoMetafields((int) $remoteId, $request);

            return PublishResult::success($postUrl, $remoteId);
        } catch (\Exception $e) {
            return PublishResult::failure($e->getMessage());
        }
    }

    public function delete(string $remoteId): bool
    {
        try {
            $blogId = $this->blogId ?? $this->getDefaultBlogId();

            $this->request(
                'DELETE',
                "/admin/api/2024-01/blogs/{$blogId}/articles/{$remoteId}.json"
            );
            return true;
        } catch (\Exception $e) {
            Log::warning('Shopify delete failed', ['error' => $e->getMessage()]);
            return false;
        }
    }

    public function testConnection(): bool
    {
        try {
            $response = $this->request('GET', '/admin/api/2024-01/shop.json');
            return isset($response['shop']['id']);
        } catch (\Exception $e) {
            return false;
        }
    }

    public function getType(): string
    {
        return 'shopify';
    }

    public function getCategories(): array
    {
        // Return available blogs
        try {
            $response = $this->request('GET', '/admin/api/2024-01/blogs.json');

            return array_map(fn($blog) => [
                'id' => $blog['id'],
                'name' => $blog['title'],
                'handle' => $blog['handle'],
            ], $response['blogs'] ?? []);
        } catch (\Exception $e) {
            return [];
        }
    }

    private function getDefaultBlogId(): ?string
    {
        try {
            $response = $this->request('GET', '/admin/api/2024-01/blogs.json');
            $blogs = $response['blogs'] ?? [];

            return !empty($blogs) ? (string) $blogs[0]['id'] : null;
        } catch (\Exception $e) {
            return null;
        }
    }

    private function getBlogHandle(string $blogId): string
    {
        try {
            $response = $this->request('GET', "/admin/api/2024-01/blogs/{$blogId}.json");
            return $response['blog']['handle'] ?? 'news';
        } catch (\Exception $e) {
            return 'news';
        }
    }

    private function setSeoMetafields(int $articleId, PublishRequest $request): void
    {
        if (!$request->metaTitle && !$request->metaDescription) {
            return;
        }

        try {
            $metafields = [];

            if ($request->metaTitle) {
                $metafields[] = [
                    'namespace' => 'global',
                    'key' => 'title_tag',
                    'value' => $request->metaTitle,
                    'type' => 'single_line_text_field',
                ];
            }

            if ($request->metaDescription) {
                $metafields[] = [
                    'namespace' => 'global',
                    'key' => 'description_tag',
                    'value' => $request->metaDescription,
                    'type' => 'single_line_text_field',
                ];
            }

            foreach ($metafields as $metafield) {
                $this->request(
                    'POST',
                    '/admin/api/2024-01/metafields.json',
                    [
                        'metafield' => array_merge($metafield, [
                            'owner_resource' => 'article',
                            'owner_id' => $articleId,
                        ]),
                    ]
                );
            }
        } catch (\Exception $e) {
            Log::warning('Shopify SEO metafields failed', ['error' => $e->getMessage()]);
        }
    }

    private function request(string $method, string $endpoint, array $data = []): array
    {
        $url = "https://{$this->shopDomain}{$endpoint}";

        $request = Http::withHeaders([
            'X-Shopify-Access-Token' => $this->accessToken,
            'Content-Type' => 'application/json',
        ])->timeout(30);

        $response = match (strtoupper($method)) {
            'GET' => $request->get($url),
            'POST' => $request->post($url, $data),
            'PUT' => $request->put($url, $data),
            'DELETE' => $request->delete($url),
            default => throw new \InvalidArgumentException("Unsupported method: {$method}"),
        };

        if (!$response->successful()) {
            throw new \RuntimeException("Shopify API error: " . $response->body());
        }

        return $response->json() ?? [];
    }

    private function generateHandle(string $title): string
    {
        return strtolower(preg_replace('/[^a-z0-9]+/i', '-', $title));
    }
}
