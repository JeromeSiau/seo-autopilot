# Ghost Integration Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Add Ghost CMS as a publishing target, following existing publisher architecture.

**Architecture:** Implement `PublisherInterface` with JWT-based Admin API authentication. Tags use smart slug matching. Images are uploaded to Ghost before post creation.

**Tech Stack:** Laravel 12, PHP 8.3, Ghost Admin API v5, JWT (HS256)

---

## Task 1: Database Migration

**Files:**
- Create: `database/migrations/2025_12_25_000001_add_ghost_to_integrations_type.php`

**Step 1: Create migration file**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE integrations MODIFY COLUMN type ENUM('wordpress', 'webflow', 'shopify', 'ghost') NOT NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE integrations MODIFY COLUMN type ENUM('wordpress', 'webflow', 'shopify') NOT NULL");
    }
};
```

**Step 2: Run migration**

Run: `php artisan migrate`
Expected: Migration successful

**Step 3: Commit**

```bash
git add database/migrations/2025_12_25_000001_add_ghost_to_integrations_type.php
git commit -m "feat(ghost): add ghost to integrations type enum"
```

---

## Task 2: Integration Model Helper

**Files:**
- Modify: `app/Models/Integration.php:60-62`

**Step 1: Add isGhost() method**

After the `isShopify()` method (line 62), add:

```php
    public function isGhost(): bool
    {
        return $this->type === 'ghost';
    }
```

**Step 2: Verify syntax**

Run: `php -l app/Models/Integration.php`
Expected: No syntax errors detected

**Step 3: Commit**

```bash
git add app/Models/Integration.php
git commit -m "feat(ghost): add isGhost() helper to Integration model"
```

---

## Task 3: GhostPublisher - JWT Authentication

**Files:**
- Create: `app/Services/Publisher/Providers/GhostPublisher.php`

**Step 1: Create file with JWT generation**

```php
<?php

namespace App\Services\Publisher\Providers;

use App\Models\Integration;
use App\Services\Publisher\Contracts\PublisherInterface;
use App\Services\Publisher\DTOs\PublishRequest;
use App\Services\Publisher\DTOs\PublishResult;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GhostPublisher implements PublisherInterface
{
    private string $blogUrl;
    private string $keyId;
    private string $secret;

    public function __construct(Integration $integration)
    {
        $credentials = $integration->credentials;

        $this->blogUrl = rtrim($credentials['blog_url'] ?? '', '/');
        $adminApiKey = $credentials['admin_api_key'] ?? '';

        if (!$this->blogUrl || !$adminApiKey) {
            throw new \InvalidArgumentException('Ghost credentials incomplete');
        }

        // Parse admin API key: {id}:{secret}
        $parts = explode(':', $adminApiKey);
        if (count($parts) !== 2 || strlen($parts[0]) !== 24 || strlen($parts[1]) !== 64) {
            throw new \InvalidArgumentException('Invalid Ghost Admin API key format');
        }

        $this->keyId = $parts[0];
        $this->secret = $parts[1];
    }

    private function generateJwt(): string
    {
        $header = json_encode(['alg' => 'HS256', 'typ' => 'JWT', 'kid' => $this->keyId]);
        $now = time();
        $payload = json_encode([
            'iat' => $now,
            'exp' => $now + 300, // 5 minutes
            'aud' => '/admin/',
        ]);

        $base64Header = rtrim(strtr(base64_encode($header), '+/', '-_'), '=');
        $base64Payload = rtrim(strtr(base64_encode($payload), '+/', '-_'), '=');

        $signature = hash_hmac('sha256', "$base64Header.$base64Payload", hex2bin($this->secret), true);
        $base64Signature = rtrim(strtr(base64_encode($signature), '+/', '-_'), '=');

        return "$base64Header.$base64Payload.$base64Signature";
    }

    public function testConnection(): bool
    {
        try {
            $response = $this->request('GET', '/ghost/api/admin/site/');
            return isset($response['site']['title']);
        } catch (\Exception $e) {
            Log::warning('Ghost connection test failed', ['error' => $e->getMessage()]);
            return false;
        }
    }

    public function getType(): string
    {
        return 'ghost';
    }

    public function getCategories(): array
    {
        try {
            $response = $this->request('GET', '/ghost/api/admin/tags/', ['limit' => 'all']);
            return array_map(fn($tag) => [
                'id' => $tag['id'],
                'name' => $tag['name'],
                'slug' => $tag['slug'],
            ], $response['tags'] ?? []);
        } catch (\Exception $e) {
            return [];
        }
    }

    public function publish(PublishRequest $request): PublishResult
    {
        // Placeholder - implemented in Task 5
        return PublishResult::failure('Not implemented');
    }

    public function update(string $remoteId, PublishRequest $request): PublishResult
    {
        // Placeholder - implemented in Task 6
        return PublishResult::failure('Not implemented');
    }

    public function delete(string $remoteId): bool
    {
        // Placeholder - implemented in Task 6
        return false;
    }

    private function request(string $method, string $endpoint, array $data = []): array
    {
        $url = $this->blogUrl . $endpoint;
        $jwt = $this->generateJwt();

        $request = Http::withHeaders([
            'Authorization' => "Ghost {$jwt}",
        ])->timeout(30);

        $response = match (strtoupper($method)) {
            'GET' => $request->get($url, $data),
            'POST' => $request->post($url, $data),
            'PUT' => $request->put($url, $data),
            'DELETE' => $request->delete($url, $data),
            default => throw new \InvalidArgumentException("Unsupported method: {$method}"),
        };

        if (!$response->successful()) {
            throw new \RuntimeException("Ghost API error: " . $response->body());
        }

        return $response->json() ?? [];
    }
}
```

**Step 2: Verify syntax**

Run: `php -l app/Services/Publisher/Providers/GhostPublisher.php`
Expected: No syntax errors detected

**Step 3: Commit**

```bash
git add app/Services/Publisher/Providers/GhostPublisher.php
git commit -m "feat(ghost): add GhostPublisher with JWT authentication"
```

---

## Task 4: Update PublisherManager

**Files:**
- Modify: `app/Services/Publisher/PublisherManager.php`

**Step 1: Add import**

After line 9 (ShopifyPublisher import), add:

```php
use App\Services\Publisher\Providers\GhostPublisher;
```

**Step 2: Add Ghost to getPublisher()**

In the match expression (lines 18-22), add ghost case before default:

```php
            'ghost' => new GhostPublisher($integration),
```

**Step 3: Add Ghost to getSupportedTypes()**

Modify line 31:

```php
        return ['wordpress', 'webflow', 'shopify', 'ghost'];
```

**Step 4: Add Ghost credentials**

In getRequiredCredentials() match expression (lines 39-43), add:

```php
            'ghost' => ['blog_url', 'admin_api_key'],
```

**Step 5: Add Ghost validation**

In validateCredentials() method, after line 66 (before return), add specific validation:

```php
        // Validate Ghost API key format
        if ($type === 'ghost' && !empty($credentials['admin_api_key'])) {
            if (!preg_match('/^[a-f0-9]{24}:[a-f0-9]{64}$/', $credentials['admin_api_key'])) {
                $errors['admin_api_key'] = 'Invalid API key format. Expected: {24 hex chars}:{64 hex chars}';
            }
        }
```

**Step 6: Verify syntax**

Run: `php -l app/Services/Publisher/PublisherManager.php`
Expected: No syntax errors detected

**Step 7: Commit**

```bash
git add app/Services/Publisher/PublisherManager.php
git commit -m "feat(ghost): register Ghost in PublisherManager"
```

---

## Task 5: GhostPublisher - Publish Method with Tags

**Files:**
- Modify: `app/Services/Publisher/Providers/GhostPublisher.php`

**Step 1: Add tag matching helper**

After the `getCategories()` method, add:

```php
    private function matchTags(array $requestTags): array
    {
        if (empty($requestTags)) {
            return [];
        }

        // Fetch existing tags from Ghost
        $existingTags = $this->getCategories();
        $existingBySlug = [];
        foreach ($existingTags as $tag) {
            $existingBySlug[$tag['slug']] = $tag;
        }

        $matchedTags = [];
        foreach ($requestTags as $tagName) {
            $slug = \Illuminate\Support\Str::slug($tagName);

            if (isset($existingBySlug[$slug])) {
                // Use existing tag
                $matchedTags[] = ['id' => $existingBySlug[$slug]['id']];
            } else {
                // Create new tag
                try {
                    $response = $this->request('POST', '/ghost/api/admin/tags/', [
                        'tags' => [['name' => $tagName, 'slug' => $slug]],
                    ]);
                    if (!empty($response['tags'][0]['id'])) {
                        $matchedTags[] = ['id' => $response['tags'][0]['id']];
                        // Add to cache for subsequent tags
                        $existingBySlug[$slug] = $response['tags'][0];
                    }
                } catch (\Exception $e) {
                    Log::warning('Ghost tag creation failed', ['tag' => $tagName, 'error' => $e->getMessage()]);
                }
            }
        }

        return $matchedTags;
    }
```

**Step 2: Add image upload helper**

After `matchTags()`, add:

```php
    private function uploadImage(PublishRequest $request): ?string
    {
        try {
            $imageUrl = $request->featuredImageUrl;
            $imagePath = $request->featuredImagePath;

            if (!$imageUrl && !$imagePath) {
                return null;
            }

            // Get image content
            if ($imagePath && \Illuminate\Support\Facades\Storage::disk('public')->exists($imagePath)) {
                $imageContent = \Illuminate\Support\Facades\Storage::disk('public')->get($imagePath);
                $filename = basename($imagePath);
            } elseif ($imageUrl) {
                $response = Http::timeout(30)->get($imageUrl);
                if (!$response->successful()) {
                    return null;
                }
                $imageContent = $response->body();
                $filename = basename(parse_url($imageUrl, PHP_URL_PATH)) ?: 'image.jpg';
            } else {
                return null;
            }

            // Upload to Ghost
            $jwt = $this->generateJwt();
            $response = Http::withHeaders([
                'Authorization' => "Ghost {$jwt}",
            ])->attach('file', $imageContent, $filename)
              ->post("{$this->blogUrl}/ghost/api/admin/images/upload/");

            if (!$response->successful()) {
                Log::warning('Ghost image upload failed', ['status' => $response->status()]);
                return null;
            }

            return $response->json()['images'][0]['url'] ?? null;
        } catch (\Exception $e) {
            Log::warning('Ghost image upload error', ['error' => $e->getMessage()]);
            return null;
        }
    }
```

**Step 3: Implement publish() method**

Replace the placeholder `publish()` method with:

```php
    public function publish(PublishRequest $request): PublishResult
    {
        try {
            // Upload featured image first
            $featureImage = $this->uploadImage($request);

            // Match/create tags
            $tags = $this->matchTags($request->tags);

            // Create post
            $postData = [
                'posts' => [[
                    'title' => $request->title,
                    'html' => $request->content,
                    'slug' => $request->slug,
                    'custom_excerpt' => $request->excerpt,
                    'meta_title' => $request->metaTitle,
                    'meta_description' => $request->metaDescription,
                    'status' => $request->status === 'publish' ? 'published' : 'draft',
                    'tags' => $tags,
                    'feature_image' => $featureImage,
                ]],
            ];

            $response = $this->request('POST', '/ghost/api/admin/posts/', $postData);

            $post = $response['posts'][0] ?? null;
            if (!$post || !isset($post['id'])) {
                return PublishResult::failure('Failed to create Ghost post');
            }

            Log::info('Ghost article published', [
                'post_id' => $post['id'],
                'url' => $post['url'] ?? null,
            ]);

            return PublishResult::success($post['url'] ?? '', $post['id'], [
                'feature_image' => $featureImage,
                'tags_count' => count($tags),
            ]);
        } catch (\Exception $e) {
            Log::error('Ghost publish failed', ['error' => $e->getMessage()]);
            return PublishResult::failure($e->getMessage());
        }
    }
```

**Step 4: Verify syntax**

Run: `php -l app/Services/Publisher/Providers/GhostPublisher.php`
Expected: No syntax errors detected

**Step 5: Commit**

```bash
git add app/Services/Publisher/Providers/GhostPublisher.php
git commit -m "feat(ghost): implement publish with tag matching and image upload"
```

---

## Task 6: GhostPublisher - Update and Delete

**Files:**
- Modify: `app/Services/Publisher/Providers/GhostPublisher.php`

**Step 1: Implement update() method**

Replace the placeholder `update()` method with:

```php
    public function update(string $remoteId, PublishRequest $request): PublishResult
    {
        try {
            // Upload new featured image if provided
            $featureImage = $this->uploadImage($request);

            // Match/create tags
            $tags = $this->matchTags($request->tags);

            $postData = [
                'posts' => [[
                    'title' => $request->title,
                    'html' => $request->content,
                    'slug' => $request->slug,
                    'custom_excerpt' => $request->excerpt,
                    'meta_title' => $request->metaTitle,
                    'meta_description' => $request->metaDescription,
                    'tags' => $tags,
                    'updated_at' => now()->toIso8601String(),
                ]],
            ];

            // Only update feature_image if we have a new one
            if ($featureImage) {
                $postData['posts'][0]['feature_image'] = $featureImage;
            }

            $response = $this->request('PUT', "/ghost/api/admin/posts/{$remoteId}/", $postData);

            $post = $response['posts'][0] ?? null;
            if (!$post) {
                return PublishResult::failure('Failed to update Ghost post');
            }

            return PublishResult::success($post['url'] ?? '', $remoteId);
        } catch (\Exception $e) {
            Log::error('Ghost update failed', ['error' => $e->getMessage()]);
            return PublishResult::failure($e->getMessage());
        }
    }
```

**Step 2: Implement delete() method**

Replace the placeholder `delete()` method with:

```php
    public function delete(string $remoteId): bool
    {
        try {
            $this->request('DELETE', "/ghost/api/admin/posts/{$remoteId}/");
            return true;
        } catch (\Exception $e) {
            Log::warning('Ghost delete failed', ['error' => $e->getMessage()]);
            return false;
        }
    }
```

**Step 3: Verify syntax**

Run: `php -l app/Services/Publisher/Providers/GhostPublisher.php`
Expected: No syntax errors detected

**Step 4: Commit**

```bash
git add app/Services/Publisher/Providers/GhostPublisher.php
git commit -m "feat(ghost): implement update and delete methods"
```

---

## Task 7: Frontend - IntegrationForm

**Files:**
- Modify: `resources/js/Components/Integration/IntegrationForm.tsx`

**Step 1: Update PlatformType**

On line 6, update:

```tsx
type PlatformType = 'wordpress' | 'webflow' | 'shopify' | 'ghost';
```

**Step 2: Add Ghost platform**

After the Shopify platform object (after line 63), add:

```tsx
    {
        id: 'ghost',
        name: 'Ghost',
        description: 'Plateforme de publication open-source',
        icon: (
            <svg className="h-6 w-6" viewBox="0 0 24 24" fill="currentColor">
                <path d="M12 2C6.477 2 2 6.477 2 12c0 4.418 2.865 8.166 6.839 9.489.5.092.682-.217.682-.482 0-.237-.009-.866-.013-1.7-2.782.603-3.369-1.34-3.369-1.34-.454-1.156-1.11-1.463-1.11-1.463-.908-.62.069-.608.069-.608 1.003.07 1.531 1.03 1.531 1.03.892 1.529 2.341 1.087 2.91.831.092-.646.35-1.086.636-1.336-2.22-.253-4.555-1.11-4.555-4.943 0-1.091.39-1.984 1.029-2.683-.103-.253-.446-1.27.098-2.647 0 0 .84-.269 2.75 1.025A9.564 9.564 0 0112 6.844c.85.004 1.705.114 2.504.336 1.909-1.294 2.747-1.025 2.747-1.025.546 1.377.203 2.394.1 2.647.64.699 1.028 1.592 1.028 2.683 0 3.842-2.339 4.687-4.566 4.935.359.309.678.919.678 1.852 0 1.336-.012 2.415-.012 2.743 0 .267.18.578.688.48C19.138 20.163 22 16.418 22 12c0-5.523-4.477-10-10-10z"/>
            </svg>
        ),
        color: 'bg-[#15171a]',
    },
```

**Step 3: Add Ghost form fields**

After the Shopify fields section (after line 402), add:

```tsx
                {/* Ghost Fields */}
                {selectedPlatform === 'ghost' && (
                    <>
                        <div>
                            <label className="block text-sm font-medium text-surface-700 dark:text-surface-300 mb-1.5">
                                URL Ghost
                            </label>
                            <input
                                type="url"
                                value={credentials.blog_url || ''}
                                onChange={(e) => handleCredentialChange('blog_url', e.target.value)}
                                placeholder="https://monblog.ghost.io"
                                required
                                className="w-full px-4 py-2.5 rounded-xl border border-surface-200 dark:border-surface-700 bg-white dark:bg-surface-800 text-surface-900 dark:text-white placeholder-surface-400 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent text-sm"
                            />
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-surface-700 dark:text-surface-300 mb-1.5">
                                Admin API Key
                            </label>
                            <div className="relative">
                                <input
                                    type={showPassword ? 'text' : 'password'}
                                    value={credentials.admin_api_key || ''}
                                    onChange={(e) => handleCredentialChange('admin_api_key', e.target.value)}
                                    placeholder="645b...f3a:8e2c...1d9b"
                                    required
                                    className="w-full px-4 py-2.5 pr-10 rounded-xl border border-surface-200 dark:border-surface-700 bg-white dark:bg-surface-800 text-surface-900 dark:text-white placeholder-surface-400 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent text-sm font-mono"
                                />
                                <button
                                    type="button"
                                    onClick={() => setShowPassword(!showPassword)}
                                    className="absolute right-3 top-1/2 -translate-y-1/2 text-surface-400 hover:text-surface-600 dark:hover:text-surface-300"
                                >
                                    {showPassword ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
                                </button>
                            </div>
                            <p className="mt-1.5 text-xs text-surface-500 dark:text-surface-400">
                                Ghost Admin → Settings → Integrations → Custom Integration
                            </p>
                        </div>
                    </>
                )}
```

**Step 4: Verify TypeScript**

Run: `npm run typecheck`
Expected: No type errors

**Step 5: Commit**

```bash
git add resources/js/Components/Integration/IntegrationForm.tsx
git commit -m "feat(ghost): add Ghost form fields to IntegrationForm"
```

---

## Task 8: Frontend - Integrations Index

**Files:**
- Modify: `resources/js/Pages/Integrations/Index.tsx`

**Step 1: Find PLATFORM_CONFIG and add Ghost**

Locate the `PLATFORM_CONFIG` object and add:

```tsx
    ghost: {
        name: 'Ghost',
        color: 'bg-[#15171a]',
        icon: (
            <svg className="h-5 w-5" viewBox="0 0 24 24" fill="currentColor">
                <path d="M12 2C6.477 2 2 6.477 2 12c0 4.418 2.865 8.166 6.839 9.489.5.092.682-.217.682-.482 0-.237-.009-.866-.013-1.7-2.782.603-3.369-1.34-3.369-1.34-.454-1.156-1.11-1.463-1.11-1.463-.908-.62.069-.608.069-.608 1.003.07 1.531 1.03 1.531 1.03.892 1.529 2.341 1.087 2.91.831.092-.646.35-1.086.636-1.336-2.22-.253-4.555-1.11-4.555-4.943 0-1.091.39-1.984 1.029-2.683-.103-.253-.446-1.27.098-2.647 0 0 .84-.269 2.75 1.025A9.564 9.564 0 0112 6.844c.85.004 1.705.114 2.504.336 1.909-1.294 2.747-1.025 2.747-1.025.546 1.377.203 2.394.1 2.647.64.699 1.028 1.592 1.028 2.683 0 3.842-2.339 4.687-4.566 4.935.359.309.678.919.678 1.852 0 1.336-.012 2.415-.012 2.743 0 .267.18.578.688.48C19.138 20.163 22 16.418 22 12c0-5.523-4.477-10-10-10z"/>
            </svg>
        ),
        description: 'Plateforme de publication open-source',
    },
```

**Step 2: Verify TypeScript**

Run: `npm run typecheck`
Expected: No type errors

**Step 3: Verify build**

Run: `npm run build`
Expected: Build successful

**Step 4: Commit**

```bash
git add resources/js/Pages/Integrations/Index.tsx
git commit -m "feat(ghost): add Ghost to Integrations Index page"
```

---

## Task 9: Final Verification

**Step 1: Run full syntax check**

Run: `php artisan route:list --path=integration`
Expected: Routes listed without errors

**Step 2: Run migrations fresh (dev only)**

Run: `php artisan migrate:fresh --seed`
Expected: Migrations run successfully

**Step 3: Run build**

Run: `npm run build`
Expected: Build successful

**Step 4: Final commit**

```bash
git add -A
git commit -m "feat(ghost): complete Ghost CMS integration

- Add GhostPublisher with JWT authentication
- Smart tag matching via slug (reuse existing, create if needed)
- Image upload to Ghost before post creation
- Admin API Key validation
- Frontend form and platform config"
```

---

## Summary

| Task | Description | Files |
|------|-------------|-------|
| 1 | Database migration | 1 new |
| 2 | Integration model helper | 1 modified |
| 3 | GhostPublisher base + JWT | 1 new |
| 4 | PublisherManager | 1 modified |
| 5 | Publish with tags | 1 modified |
| 6 | Update and delete | 1 modified |
| 7 | IntegrationForm | 1 modified |
| 8 | Integrations Index | 1 modified |
| 9 | Final verification | - |

**Total: 2 new files, 4 modified files**
