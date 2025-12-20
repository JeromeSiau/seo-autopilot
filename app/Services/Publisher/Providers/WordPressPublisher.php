<?php

namespace App\Services\Publisher\Providers;

use App\Models\Integration;
use App\Services\Publisher\Contracts\PublisherInterface;
use App\Services\Publisher\DTOs\PublishRequest;
use App\Services\Publisher\DTOs\PublishResult;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class WordPressPublisher implements PublisherInterface
{
    private string $siteUrl;
    private string $username;
    private string $appPassword;

    public function __construct(Integration $integration)
    {
        $credentials = $integration->credentials;

        $this->siteUrl = rtrim($credentials['site_url'] ?? '', '/');
        $this->username = $credentials['username'] ?? '';
        $this->appPassword = $credentials['app_password'] ?? '';

        if (!$this->siteUrl || !$this->username || !$this->appPassword) {
            throw new \InvalidArgumentException('WordPress credentials incomplete');
        }
    }

    public function publish(PublishRequest $request): PublishResult
    {
        try {
            // Upload featured image first if provided
            $featuredMediaId = null;
            if ($request->featuredImagePath || $request->featuredImageUrl) {
                $featuredMediaId = $this->uploadFeaturedImage($request);
            }

            // Create the post
            $postData = [
                'title' => $request->title,
                'content' => $request->content,
                'status' => $request->status,
                'slug' => $request->slug,
                'excerpt' => $request->excerpt,
            ];

            if ($featuredMediaId) {
                $postData['featured_media'] = $featuredMediaId;
            }

            if (!empty($request->categories)) {
                $postData['categories'] = $request->categories;
            }

            if (!empty($request->tags)) {
                $postData['tags'] = $request->tags;
            }

            $response = $this->request('POST', '/wp-json/wp/v2/posts', $postData);

            $postId = $response['id'] ?? null;
            $postUrl = $response['link'] ?? null;

            if (!$postId) {
                return PublishResult::failure('Failed to create post');
            }

            // Set SEO meta if Yoast/RankMath available
            $this->setSeoMeta($postId, $request);

            Log::info('WordPress article published', [
                'post_id' => $postId,
                'url' => $postUrl,
            ]);

            return PublishResult::success($postUrl, (string) $postId, [
                'featured_media_id' => $featuredMediaId,
            ]);
        } catch (\Exception $e) {
            Log::error('WordPress publish failed', [
                'error' => $e->getMessage(),
            ]);
            return PublishResult::failure($e->getMessage());
        }
    }

    public function update(string $remoteId, PublishRequest $request): PublishResult
    {
        try {
            $postData = [
                'title' => $request->title,
                'content' => $request->content,
                'slug' => $request->slug,
                'excerpt' => $request->excerpt,
            ];

            $response = $this->request('PUT', "/wp-json/wp/v2/posts/{$remoteId}", $postData);

            $postUrl = $response['link'] ?? null;

            $this->setSeoMeta((int) $remoteId, $request);

            return PublishResult::success($postUrl, $remoteId);
        } catch (\Exception $e) {
            return PublishResult::failure($e->getMessage());
        }
    }

    public function delete(string $remoteId): bool
    {
        try {
            $this->request('DELETE', "/wp-json/wp/v2/posts/{$remoteId}", [
                'force' => true,
            ]);
            return true;
        } catch (\Exception $e) {
            Log::warning('WordPress delete failed', ['error' => $e->getMessage()]);
            return false;
        }
    }

    public function testConnection(): bool
    {
        try {
            $response = $this->request('GET', '/wp-json/wp/v2/users/me');
            return isset($response['id']);
        } catch (\Exception $e) {
            return false;
        }
    }

    public function getType(): string
    {
        return 'wordpress';
    }

    public function getCategories(): array
    {
        try {
            $response = $this->request('GET', '/wp-json/wp/v2/categories', [
                'per_page' => 100,
            ]);

            return array_map(fn($cat) => [
                'id' => $cat['id'],
                'name' => $cat['name'],
                'slug' => $cat['slug'],
            ], $response);
        } catch (\Exception $e) {
            return [];
        }
    }

    private function uploadFeaturedImage(PublishRequest $request): ?int
    {
        try {
            // Get image content
            if ($request->featuredImagePath && Storage::disk('public')->exists($request->featuredImagePath)) {
                $imageContent = Storage::disk('public')->get($request->featuredImagePath);
                $filename = basename($request->featuredImagePath);
            } elseif ($request->featuredImageUrl) {
                $response = Http::timeout(30)->get($request->featuredImageUrl);
                if (!$response->successful()) {
                    return null;
                }
                $imageContent = $response->body();
                $filename = basename(parse_url($request->featuredImageUrl, PHP_URL_PATH)) ?: 'image.jpg';
            } else {
                return null;
            }

            // Upload to WordPress
            $response = Http::withBasicAuth($this->username, $this->appPassword)
                ->withHeaders([
                    'Content-Disposition' => "attachment; filename=\"{$filename}\"",
                    'Content-Type' => $this->getMimeType($filename),
                ])
                ->withBody($imageContent, $this->getMimeType($filename))
                ->post("{$this->siteUrl}/wp-json/wp/v2/media");

            if (!$response->successful()) {
                Log::warning('WordPress image upload failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return null;
            }

            return $response->json()['id'] ?? null;
        } catch (\Exception $e) {
            Log::warning('WordPress image upload error', ['error' => $e->getMessage()]);
            return null;
        }
    }

    private function setSeoMeta(int $postId, PublishRequest $request): void
    {
        if (!$request->metaTitle && !$request->metaDescription) {
            return;
        }

        try {
            // Try Yoast SEO meta
            $this->request('PUT', "/wp-json/wp/v2/posts/{$postId}", [
                'meta' => [
                    '_yoast_wpseo_title' => $request->metaTitle,
                    '_yoast_wpseo_metadesc' => $request->metaDescription,
                ],
            ]);
        } catch (\Exception $e) {
            // Yoast not available, try RankMath
            try {
                $this->request('PUT', "/wp-json/wp/v2/posts/{$postId}", [
                    'meta' => [
                        'rank_math_title' => $request->metaTitle,
                        'rank_math_description' => $request->metaDescription,
                    ],
                ]);
            } catch (\Exception $e) {
                // SEO plugin not available, skip
            }
        }
    }

    private function request(string $method, string $endpoint, array $data = []): array
    {
        $url = $this->siteUrl . $endpoint;

        $request = Http::withBasicAuth($this->username, $this->appPassword)
            ->timeout(30);

        $response = match (strtoupper($method)) {
            'GET' => $request->get($url, $data),
            'POST' => $request->post($url, $data),
            'PUT' => $request->put($url, $data),
            'DELETE' => $request->delete($url, $data),
            default => throw new \InvalidArgumentException("Unsupported method: {$method}"),
        };

        if (!$response->successful()) {
            throw new \RuntimeException("WordPress API error: " . $response->body());
        }

        return $response->json() ?? [];
    }

    private function getMimeType(string $filename): string
    {
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        return match ($extension) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            default => 'application/octet-stream',
        };
    }
}
