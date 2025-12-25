# Ghost Integration Design

## Overview

Add Ghost CMS as a publishing target for SEO Autopilot, following the existing publisher architecture.

## Scope

**Supported:**
- Ghost Pro (hosted) and self-hosted instances
- Admin API Key authentication (JWT-based)
- Article publishing: title, HTML content, slug, status (draft/published)
- SEO fields: meta title, meta description, excerpt
- Featured image upload to Ghost
- Tags with smart matching (reuse existing, create only if needed)

**Out of scope:**
- Staff Access Token authentication
- Custom author assignment
- Code injection (header/footer)
- Scheduled publishing

## Architecture

### Files to Create

- `app/Services/Publisher/Providers/GhostPublisher.php`

### Files to Modify

- `app/Services/Publisher/PublisherManager.php` - Add Ghost to factory
- `app/Models/Integration.php` - Add `isGhost()` helper
- `database/migrations/..._add_ghost_to_integrations.php` - Extend type enum
- `resources/js/Components/Integration/IntegrationForm.tsx` - Ghost form fields
- `resources/js/Pages/Integrations/Index.tsx` - Ghost platform config

### Credentials

```php
[
    'blog_url'      => 'https://mysite.ghost.io',  // or self-hosted URL
    'admin_api_key' => '645b...f3a:8e2c...1d9b'    // format {id}:{secret}
]
```

### Authentication Flow

1. Extract `key_id` (first 24 chars) and `secret` (last 64 chars) from API key
2. Generate JWT signed with HS256 using hex-decoded secret
3. Token valid for 5 minutes, regenerated per request
4. Header: `Authorization: Ghost {jwt_token}`

## GhostPublisher Implementation

### Interface Methods

```php
class GhostPublisher implements PublisherInterface
{
    public function publish(PublishRequest $request): PublishResult;
    public function update(string $remoteId, PublishRequest $request): PublishResult;
    public function delete(string $remoteId): bool;
    public function testConnection(): bool;
    public function getType(): string; // returns 'ghost'
    public function getCategories(): array; // returns existing Ghost tags
}
```

### Ghost Admin API v5 Endpoints

| Method | Endpoint | Usage |
|--------|----------|-------|
| `GET` | `/ghost/api/admin/site/` | Test connection |
| `GET` | `/ghost/api/admin/tags/?limit=all` | Fetch existing tags |
| `POST` | `/ghost/api/admin/tags/` | Create tag (if no match) |
| `POST` | `/ghost/api/admin/images/upload/` | Upload featured image |
| `POST` | `/ghost/api/admin/posts/` | Create post |
| `PUT` | `/ghost/api/admin/posts/{id}/` | Update post |
| `DELETE` | `/ghost/api/admin/posts/{id}/` | Delete post |

### Field Mapping

| PublishRequest | Ghost API |
|----------------|-----------|
| `title` | `title` |
| `content` | `html` |
| `slug` | `slug` |
| `excerpt` | `custom_excerpt` |
| `metaTitle` | `meta_title` |
| `metaDescription` | `meta_description` |
| `featuredImageUrl` | `feature_image` (after upload) |
| `status` | `status` ('draft' or 'published') |
| `tags` | `tags` (array of `{name, slug}` objects) |

## Tag Matching Logic

### Algorithm

```
For each tag to publish:
  1. Normalize to slug (lowercase, dashes, no accents)
  2. Search existing Ghost tags by exact slug match
  3. If match → use existing tag (id + name)
  4. If no match → create tag via API
  5. Collect all tags (existing + created)
  6. Pass array to post creation
```

### Example

Tags from crawl: `["SEO", "Marketing Digital", "Café"]`

Existing Ghost tags:
- `{ id: "1", name: "Seo", slug: "seo" }`
- `{ id: "2", name: "E-commerce", slug: "e-commerce" }`

Result:
- `"SEO"` → slug `seo` → **match id:1**
- `"Marketing Digital"` → slug `marketing-digital` → **created**
- `"Café"` → slug `cafe` → **created**

### Optimizations

- Cache existing tags at start of `publish()` (single API call)
- Batch create new tags if possible (Ghost supports this)

### Error Handling

- If tag creation fails → log warning, continue without that tag
- Article is published even if some tags fail

## Image Upload

### Flow

1. Download image from source URL (Replicate/storage)
2. Upload to Ghost via `POST /ghost/api/admin/images/upload/` (multipart form)
3. Ghost returns hosted URL
4. Use Ghost URL as `feature_image` in post creation

### Why Upload?

- Replicate URLs may expire
- Ghost-hosted images are permanent and optimized
- Consistent with Ghost's content delivery

## UI Configuration

### Form Fields

| Field | Type | Placeholder | Validation |
|-------|------|-------------|------------|
| Blog URL | `url` | `https://myblog.ghost.io` | Valid URL, no trailing slash |
| Admin API Key | `password` | `645b...f3a:8e2c...1d9b` | Format `{id}:{secret}` (hex:hex) |

### Server-side Validation

```php
// PublisherManager::validateCredentials
'blog_url'      => 'required|url',
'admin_api_key' => 'required|regex:/^[a-f0-9]{24}:[a-f0-9]{64}$/'
```

### Connection Test

1. Generate JWT from key
2. Call `GET /ghost/api/admin/site/`
3. Verify 200 response with `site.title` present

### Platform Config (UI)

```tsx
ghost: {
    name: 'Ghost',
    color: 'bg-[#15171a]',  // Ghost black
    icon: GhostIcon,
    description: 'Open-source publishing platform'
}
```

## Database Migration

Add `'ghost'` to the `type` enum in `integrations` table.

```php
// Migration
Schema::table('integrations', function (Blueprint $table) {
    DB::statement("ALTER TABLE integrations MODIFY COLUMN type ENUM('wordpress', 'webflow', 'shopify', 'ghost') NOT NULL");
});
```

## Error Handling

| Scenario | Behavior |
|----------|----------|
| Invalid API key format | Validation error before save |
| Connection test fails | Show error, don't save integration |
| Image upload fails | Log warning, publish without image |
| Tag creation fails | Log warning, continue with matched tags |
| Post creation fails | Return `PublishResult::failed()` with error |
| Post update fails | Return `PublishResult::failed()` with error |

## Testing

- Unit tests for JWT generation
- Unit tests for tag slug matching
- Integration tests with Ghost API (mocked)
- Feature test for full publish flow
