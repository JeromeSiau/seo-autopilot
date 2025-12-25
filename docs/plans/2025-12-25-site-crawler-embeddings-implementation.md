# Site Crawler avec Embeddings - Plan d'implémentation

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Crawler le contenu existant d'un site et utiliser des embeddings vectoriels pour un linking agent intelligent.

**Architecture:** Agent Node.js (Playwright + Readability) qui crawle les sites, génère des embeddings via Voyage API, stocke dans SQLite avec sqlite-vec. Le linking agent utilise la recherche vectorielle pour trouver les pages pertinentes.

**Tech Stack:** Node.js (Playwright, @mozilla/readability, better-sqlite3, sqlite-vec), PHP (VoyageProvider, Jobs Laravel), Voyage 3.5-lite API.

---

## Task 1: VoyageProvider PHP

**Files:**
- Create: `app/Services/LLM/Providers/VoyageProvider.php`
- Modify: `config/services.php`
- Test: `tests/Unit/Services/VoyageProviderTest.php`

**Step 1: Ajouter la config Voyage**

```php
// config/services.php - ajouter après 'google'

'voyage' => [
    'api_key' => env('VOYAGE_API_KEY'),
],
```

**Step 2: Créer le provider**

```php
// app/Services/LLM/Providers/VoyageProvider.php
<?php

namespace App\Services\LLM\Providers;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class VoyageProvider
{
    private const API_URL = 'https://api.voyageai.com/v1/embeddings';
    private const MODEL = 'voyage-3.5-lite';
    private const DIMENSION = 1024;
    private const MAX_BATCH_SIZE = 128;

    public function __construct(
        private readonly string $apiKey,
    ) {}

    /**
     * Generate embedding for a single text.
     *
     * @return array<float>
     */
    public function embed(string $text, string $inputType = 'document'): array
    {
        $response = Http::withHeaders([
            'Authorization' => "Bearer {$this->apiKey}",
            'Content-Type' => 'application/json',
        ])->timeout(30)->post(self::API_URL, [
            'model' => self::MODEL,
            'input' => [$text],
            'input_type' => $inputType,
        ]);

        if (!$response->successful()) {
            Log::error('Voyage API error', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new \RuntimeException('Voyage API error: ' . $response->body());
        }

        return $response->json('data.0.embedding');
    }

    /**
     * Generate embeddings for multiple texts (batch).
     *
     * @param array<string> $texts
     * @return array<array<float>>
     */
    public function embedBatch(array $texts, string $inputType = 'document'): array
    {
        $results = [];

        foreach (array_chunk($texts, self::MAX_BATCH_SIZE) as $chunk) {
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$this->apiKey}",
                'Content-Type' => 'application/json',
            ])->timeout(60)->post(self::API_URL, [
                'model' => self::MODEL,
                'input' => $chunk,
                'input_type' => $inputType,
            ]);

            if (!$response->successful()) {
                Log::error('Voyage API error', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                throw new \RuntimeException('Voyage API error: ' . $response->body());
            }

            foreach ($response->json('data') as $item) {
                $results[] = $item['embedding'];
            }
        }

        return $results;
    }

    public function getDimension(): int
    {
        return self::DIMENSION;
    }
}
```

**Step 3: Créer le test unitaire**

```php
// tests/Unit/Services/VoyageProviderTest.php
<?php

namespace Tests\Unit\Services;

use App\Services\LLM\Providers\VoyageProvider;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class VoyageProviderTest extends TestCase
{
    public function test_embed_returns_vector(): void
    {
        Http::fake([
            'api.voyageai.com/*' => Http::response([
                'data' => [
                    ['embedding' => array_fill(0, 1024, 0.1)]
                ]
            ], 200),
        ]);

        $provider = new VoyageProvider('fake-key');
        $embedding = $provider->embed('Test text');

        $this->assertCount(1024, $embedding);
        Http::assertSent(fn($request) =>
            $request->url() === 'https://api.voyageai.com/v1/embeddings' &&
            $request['model'] === 'voyage-3.5-lite'
        );
    }

    public function test_embed_batch_chunks_large_requests(): void
    {
        Http::fake([
            'api.voyageai.com/*' => Http::response([
                'data' => array_map(
                    fn() => ['embedding' => array_fill(0, 1024, 0.1)],
                    range(1, 128)
                )
            ], 200),
        ]);

        $provider = new VoyageProvider('fake-key');
        $texts = array_fill(0, 200, 'Test text');
        $embeddings = $provider->embedBatch($texts);

        $this->assertCount(200, $embeddings);
        Http::assertSentCount(2); // 200 texts = 2 batches of 128
    }

    public function test_get_dimension_returns_1024(): void
    {
        $provider = new VoyageProvider('fake-key');
        $this->assertEquals(1024, $provider->getDimension());
    }
}
```

**Step 4: Run tests**

```bash
php artisan test tests/Unit/Services/VoyageProviderTest.php
```

**Step 5: Commit**

```bash
git add app/Services/LLM/Providers/VoyageProvider.php config/services.php tests/Unit/Services/VoyageProviderTest.php
git commit -m "feat: add VoyageProvider for embeddings API"
```

---

## Task 2: Setup Node.js site-indexer agent

**Files:**
- Create: `agents/site-indexer/index.js`
- Create: `agents/site-indexer/navigator.js`
- Create: `agents/site-indexer/content-extractor.js`
- Create: `agents/site-indexer/embedder.js`
- Create: `agents/site-indexer/db.js`
- Modify: `agents/package.json`

**Step 1: Ajouter les dépendances**

```json
// agents/package.json - ajouter dans dependencies
"@mozilla/readability": "^0.5.0",
"better-sqlite3": "^11.0.0",
"jsdom": "^25.0.0",
"sqlite-vec": "^0.1.0"
```

**Step 2: Installer les dépendances**

```bash
cd agents && npm install
```

**Step 3: Créer le module embedder**

```javascript
// agents/site-indexer/embedder.js
import { config } from '../shared/config.js';

const VOYAGE_API_URL = 'https://api.voyageai.com/v1/embeddings';
const MODEL = 'voyage-3.5-lite';

export async function generateEmbedding(text, inputType = 'document') {
    const response = await fetch(VOYAGE_API_URL, {
        method: 'POST',
        headers: {
            'Authorization': `Bearer ${config.voyage?.apiKey || process.env.VOYAGE_API_KEY}`,
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            model: MODEL,
            input: [text],
            input_type: inputType,
        }),
    });

    if (!response.ok) {
        throw new Error(`Voyage API error: ${response.status} ${await response.text()}`);
    }

    const data = await response.json();
    return data.data[0].embedding;
}

export async function generateEmbeddingsBatch(texts, inputType = 'document') {
    const results = [];
    const BATCH_SIZE = 128;

    for (let i = 0; i < texts.length; i += BATCH_SIZE) {
        const batch = texts.slice(i, i + BATCH_SIZE);

        const response = await fetch(VOYAGE_API_URL, {
            method: 'POST',
            headers: {
                'Authorization': `Bearer ${config.voyage?.apiKey || process.env.VOYAGE_API_KEY}`,
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                model: MODEL,
                input: batch,
                input_type: inputType,
            }),
        });

        if (!response.ok) {
            throw new Error(`Voyage API error: ${response.status} ${await response.text()}`);
        }

        const data = await response.json();
        results.push(...data.data.map(d => d.embedding));
    }

    return results;
}
```

**Step 4: Créer le module content-extractor**

```javascript
// agents/site-indexer/content-extractor.js
import { Readability } from '@mozilla/readability';
import { JSDOM } from 'jsdom';

export function extractContent(html, url) {
    const dom = new JSDOM(html, { url });
    const document = dom.window.document;

    // Extract metadata
    const title = document.querySelector('title')?.textContent?.trim() || '';
    const h1 = document.querySelector('h1')?.textContent?.trim() || '';
    const metaDescription = document.querySelector('meta[name="description"]')?.getAttribute('content') || '';
    const canonical = document.querySelector('link[rel="canonical"]')?.getAttribute('href') || url;

    // Extract content with Readability
    const reader = new Readability(document.cloneNode(true));
    const article = reader.parse();

    // Extract internal links
    const internalLinks = [];
    const urlObj = new URL(url);
    document.querySelectorAll('a[href]').forEach(a => {
        try {
            const href = new URL(a.href, url);
            if (href.hostname === urlObj.hostname && href.pathname !== urlObj.pathname) {
                internalLinks.push(href.href);
            }
        } catch {}
    });

    // Detect category from URL or breadcrumbs
    const category = detectCategory(document, url);
    const tags = detectTags(document);

    return {
        title: cleanTitle(title),
        h1,
        metaDescription,
        canonical,
        contentText: article?.textContent?.trim() || '',
        contentLength: article?.textContent?.length || 0,
        internalLinks: [...new Set(internalLinks)],
        category,
        tags,
    };
}

function cleanTitle(title) {
    // Remove site name suffix (e.g., "Article Title | Site Name")
    return title.replace(/\s*[|\-–]\s*[^|\-–]+$/, '').trim();
}

function detectCategory(document, url) {
    // Try breadcrumbs
    const breadcrumb = document.querySelector('[class*="breadcrumb"], nav[aria-label="breadcrumb"]');
    if (breadcrumb) {
        const links = breadcrumb.querySelectorAll('a');
        if (links.length > 1) {
            return links[links.length - 2]?.textContent?.trim() || null;
        }
    }

    // Try schema.org
    const schema = document.querySelector('script[type="application/ld+json"]');
    if (schema) {
        try {
            const data = JSON.parse(schema.textContent);
            if (data.articleSection) return data.articleSection;
            if (data.breadcrumb?.itemListElement) {
                const items = data.breadcrumb.itemListElement;
                if (items.length > 1) return items[items.length - 2]?.name;
            }
        } catch {}
    }

    // Try URL pattern
    const urlMatch = url.match(/\/(category|categorie|cat|rubrique)\/([^\/]+)/i);
    if (urlMatch) return decodeURIComponent(urlMatch[2]).replace(/-/g, ' ');

    return null;
}

function detectTags(document) {
    const tags = [];

    // Try rel="tag" links
    document.querySelectorAll('a[rel="tag"]').forEach(a => {
        const tag = a.textContent?.trim();
        if (tag) tags.push(tag);
    });

    // Try common tag class patterns
    document.querySelectorAll('[class*="tag-"], [class*="tags"] a, .post-tags a').forEach(el => {
        const tag = el.textContent?.trim();
        if (tag && !tags.includes(tag)) tags.push(tag);
    });

    return tags;
}
```

**Step 5: Créer le module navigator (crawler)**

```javascript
// agents/site-indexer/navigator.js
import { createBrowser, closeBrowser } from '../shared/browser.js';

export async function crawlSite(siteUrl, options = {}) {
    const {
        maxPages = 500,
        rateLimit = 500, // ms between requests
        respectRobots = true,
    } = options;

    const visited = new Set();
    const queue = [siteUrl];
    const pages = [];
    const outboundLinks = new Map(); // url -> [linked urls]

    const browser = await createBrowser();
    const page = await browser.newPage();

    // Set reasonable timeout
    page.setDefaultTimeout(30000);

    try {
        while (queue.length > 0 && pages.length < maxPages) {
            const url = queue.shift();

            // Normalize URL
            const normalizedUrl = normalizeUrl(url);
            if (visited.has(normalizedUrl)) continue;
            visited.add(normalizedUrl);

            try {
                // Rate limiting
                await sleep(rateLimit);

                // Navigate to page
                const response = await page.goto(url, {
                    waitUntil: 'domcontentloaded',
                    timeout: 30000
                });

                if (!response || !response.ok()) {
                    console.log(`Skip ${url}: ${response?.status() || 'no response'}`);
                    continue;
                }

                // Get HTML content
                const html = await page.content();

                // Collect internal links for queue
                const links = await page.evaluate((hostname) => {
                    const links = [];
                    document.querySelectorAll('a[href]').forEach(a => {
                        try {
                            const href = new URL(a.href, window.location.href);
                            if (href.hostname === hostname) {
                                links.push(href.href);
                            }
                        } catch {}
                    });
                    return links;
                }, new URL(siteUrl).hostname);

                // Store outbound links
                outboundLinks.set(normalizedUrl, links.map(normalizeUrl));

                // Add new links to queue
                for (const link of links) {
                    const normalizedLink = normalizeUrl(link);
                    if (!visited.has(normalizedLink) && !queue.includes(link)) {
                        queue.push(link);
                    }
                }

                pages.push({
                    url: normalizedUrl,
                    html,
                    source: visited.size === 1 ? 'homepage' : 'navigation',
                });

                console.log(`Crawled ${pages.length}/${maxPages}: ${normalizedUrl}`);

            } catch (error) {
                console.error(`Error crawling ${url}:`, error.message);
            }
        }
    } finally {
        await page.close();
        await closeBrowser(browser);
    }

    // Calculate inbound links count
    const inboundCounts = new Map();
    for (const [sourceUrl, targetUrls] of outboundLinks) {
        for (const targetUrl of targetUrls) {
            inboundCounts.set(targetUrl, (inboundCounts.get(targetUrl) || 0) + 1);
        }
    }

    return { pages, outboundLinks, inboundCounts };
}

export async function crawlSitemap(sitemapUrl) {
    const urls = [];

    try {
        const response = await fetch(sitemapUrl);
        if (!response.ok) return urls;

        const text = await response.text();

        // Check if it's a sitemap index
        if (text.includes('<sitemapindex')) {
            const sitemapMatches = text.matchAll(/<loc>([^<]+)<\/loc>/g);
            for (const match of sitemapMatches) {
                const childUrls = await crawlSitemap(match[1]);
                urls.push(...childUrls);
            }
        } else {
            const urlMatches = text.matchAll(/<loc>([^<]+)<\/loc>/g);
            for (const match of urlMatches) {
                urls.push(match[1]);
            }
        }
    } catch (error) {
        console.error(`Error fetching sitemap ${sitemapUrl}:`, error.message);
    }

    return urls;
}

function normalizeUrl(url) {
    try {
        const u = new URL(url);
        // Remove trailing slash, hash, and common tracking params
        u.hash = '';
        u.searchParams.delete('utm_source');
        u.searchParams.delete('utm_medium');
        u.searchParams.delete('utm_campaign');
        let path = u.pathname.replace(/\/$/, '') || '/';
        return `${u.origin}${path}${u.search}`;
    } catch {
        return url;
    }
}

function sleep(ms) {
    return new Promise(resolve => setTimeout(resolve, ms));
}
```

**Step 6: Créer le module db (SQLite)**

```javascript
// agents/site-indexer/db.js
import Database from 'better-sqlite3';
import * as sqliteVec from 'sqlite-vec';
import { mkdirSync, existsSync } from 'fs';
import path from 'path';

const INDEXES_DIR = path.join(process.cwd(), '..', 'storage', 'indexes');

export function getDatabase(siteId) {
    // Ensure directory exists
    if (!existsSync(INDEXES_DIR)) {
        mkdirSync(INDEXES_DIR, { recursive: true });
    }

    const dbPath = path.join(INDEXES_DIR, `site_${siteId}.sqlite`);
    const db = new Database(dbPath);

    // Load sqlite-vec extension
    sqliteVec.load(db);

    // Create tables if not exist
    db.exec(`
        CREATE TABLE IF NOT EXISTS pages (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            url TEXT UNIQUE NOT NULL,
            title TEXT,
            h1 TEXT,
            meta_description TEXT,
            content_text TEXT,
            category TEXT,
            tags TEXT,
            inbound_links_count INTEGER DEFAULT 0,
            outbound_links TEXT,
            source TEXT,
            crawled_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );

        CREATE INDEX IF NOT EXISTS idx_pages_category ON pages(category);
        CREATE INDEX IF NOT EXISTS idx_pages_url ON pages(url);

        CREATE VIRTUAL TABLE IF NOT EXISTS pages_vec USING vec0(
            page_id INTEGER PRIMARY KEY,
            embedding float[1024]
        );
    `);

    return db;
}

export function upsertPage(db, pageData) {
    const stmt = db.prepare(`
        INSERT INTO pages (url, title, h1, meta_description, content_text, category, tags, inbound_links_count, outbound_links, source, crawled_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)
        ON CONFLICT(url) DO UPDATE SET
            title = excluded.title,
            h1 = excluded.h1,
            meta_description = excluded.meta_description,
            content_text = excluded.content_text,
            category = excluded.category,
            tags = excluded.tags,
            inbound_links_count = excluded.inbound_links_count,
            outbound_links = excluded.outbound_links,
            source = excluded.source,
            crawled_at = CURRENT_TIMESTAMP
    `);

    const result = stmt.run(
        pageData.url,
        pageData.title,
        pageData.h1,
        pageData.metaDescription,
        pageData.contentText,
        pageData.category,
        JSON.stringify(pageData.tags || []),
        pageData.inboundLinksCount || 0,
        JSON.stringify(pageData.outboundLinks || []),
        pageData.source
    );

    return result.lastInsertRowid || db.prepare('SELECT id FROM pages WHERE url = ?').get(pageData.url).id;
}

export function upsertEmbedding(db, pageId, embedding) {
    // Delete existing embedding if any
    db.prepare('DELETE FROM pages_vec WHERE page_id = ?').run(pageId);

    // Insert new embedding
    const stmt = db.prepare('INSERT INTO pages_vec (page_id, embedding) VALUES (?, ?)');
    stmt.run(pageId, new Float32Array(embedding));
}

export function searchSimilar(db, embedding, limit = 20) {
    const stmt = db.prepare(`
        SELECT
            p.*,
            vec_distance_cosine(pv.embedding, ?) as distance
        FROM pages_vec pv
        JOIN pages p ON p.id = pv.page_id
        ORDER BY distance ASC
        LIMIT ?
    `);

    return stmt.all(new Float32Array(embedding), limit);
}

export function getAllPages(db) {
    return db.prepare('SELECT * FROM pages ORDER BY crawled_at DESC').all();
}

export function getPageByUrl(db, url) {
    return db.prepare('SELECT * FROM pages WHERE url = ?').get(url);
}

export function getKnownUrls(db) {
    return db.prepare('SELECT url FROM pages').all().map(r => r.url);
}
```

**Step 7: Créer l'index principal du crawler**

```javascript
// agents/site-indexer/index.js
import { program } from 'commander';
import { emitStarted, emitProgress, emitCompleted, emitError, closeRedis } from '../shared/event-emitter.js';
import { crawlSite, crawlSitemap } from './navigator.js';
import { extractContent } from './content-extractor.js';
import { generateEmbedding } from './embedder.js';
import { getDatabase, upsertPage, upsertEmbedding, getKnownUrls } from './db.js';
import { generateJSON } from '../shared/llm.js';

const AGENT_TYPE = 'site_indexer';

program
    .requiredOption('--siteId <id>', 'Site ID')
    .requiredOption('--siteUrl <url>', 'Site URL to crawl')
    .option('--maxPages <n>', 'Maximum pages to crawl', '500')
    .option('--delta', 'Only crawl new pages (delta mode)')
    .parse();

const options = program.opts();

async function main() {
    const { siteId, siteUrl, maxPages, delta } = options;
    const db = getDatabase(siteId);

    try {
        await emitStarted(siteId, AGENT_TYPE,
            'Indexation du site',
            'Je vais crawler votre site et indexer le contenu pour le maillage interne.'
        );

        // Step 1: Get sitemap URLs
        await emitProgress(siteId, AGENT_TYPE, 'Récupération du sitemap...');

        const sitemapUrls = await crawlSitemap(`${siteUrl}/sitemap.xml`);
        console.log(`Found ${sitemapUrls.length} URLs in sitemap`);

        // In delta mode, filter out already known URLs
        let urlsToCrawl = sitemapUrls;
        if (delta) {
            const knownUrls = new Set(getKnownUrls(db));
            urlsToCrawl = sitemapUrls.filter(url => !knownUrls.has(url));
            console.log(`Delta mode: ${urlsToCrawl.length} new URLs to crawl`);
        }

        await emitProgress(siteId, AGENT_TYPE,
            `Sitemap analysé: ${sitemapUrls.length} URLs (${urlsToCrawl.length} à crawler)`
        );

        // Step 2: Crawl by navigation
        await emitProgress(siteId, AGENT_TYPE, 'Crawl par navigation...');

        const { pages, inboundCounts } = await crawlSite(siteUrl, {
            maxPages: parseInt(maxPages),
            rateLimit: 500,
        });

        await emitProgress(siteId, AGENT_TYPE,
            `Crawl terminé: ${pages.length} pages découvertes`
        );

        // Step 3: Process each page
        let processed = 0;
        for (const pageData of pages) {
            try {
                await emitProgress(siteId, AGENT_TYPE,
                    `Traitement ${++processed}/${pages.length}: ${pageData.url.substring(0, 50)}...`
                );

                // Extract content
                const extracted = extractContent(pageData.html, pageData.url);

                // Skip pages with very little content
                if (extracted.contentLength < 200) {
                    console.log(`Skip ${pageData.url}: too little content`);
                    continue;
                }

                // Detect category with LLM if not found
                let category = extracted.category;
                if (!category && extracted.contentText) {
                    try {
                        const result = await generateJSON(`
                            Analyse ce contenu et détermine sa catégorie principale.

                            Titre: ${extracted.title}
                            Contenu (extrait): ${extracted.contentText.substring(0, 1000)}

                            Retourne un JSON: {"category": "nom de la catégorie"}
                            Si tu ne peux pas déterminer la catégorie, retourne {"category": null}
                        `, '', { model: 'gpt-4o-mini' });
                        category = result.category;
                    } catch (e) {
                        console.error('LLM category detection failed:', e.message);
                    }
                }

                // Generate embedding
                const textForEmbedding = [
                    extracted.title,
                    extracted.h1,
                    extracted.metaDescription,
                    extracted.contentText.substring(0, 8000)
                ].filter(Boolean).join('\n\n');

                const embedding = await generateEmbedding(textForEmbedding);

                // Store in database
                const pageId = upsertPage(db, {
                    url: pageData.url,
                    title: extracted.title,
                    h1: extracted.h1,
                    metaDescription: extracted.metaDescription,
                    contentText: extracted.contentText.substring(0, 50000),
                    category,
                    tags: extracted.tags,
                    inboundLinksCount: inboundCounts.get(pageData.url) || 0,
                    outboundLinks: extracted.internalLinks,
                    source: pageData.source,
                });

                upsertEmbedding(db, pageId, embedding);

            } catch (error) {
                console.error(`Error processing ${pageData.url}:`, error.message);
            }
        }

        await emitCompleted(siteId, AGENT_TYPE,
            `Indexation terminée: ${processed} pages indexées`,
            {
                reasoning: `Le site a été crawlé et indexé. ${pages.length} pages découvertes, ${processed} pages avec contenu indexées.`,
                metadata: {
                    pages_discovered: pages.length,
                    pages_indexed: processed,
                }
            }
        );

        console.log(JSON.stringify({
            success: true,
            pages_discovered: pages.length,
            pages_indexed: processed,
        }));

    } catch (error) {
        await emitError(siteId, AGENT_TYPE, `Erreur: ${error.message}`, error);
        console.log(JSON.stringify({ success: false, error: error.message }));
        process.exit(1);
    } finally {
        db.close();
        try {
            await closeRedis();
        } catch {}
    }
}

main();
```

**Step 8: Ajouter Voyage config dans shared/config.js**

```javascript
// agents/shared/config.js - ajouter après openai
voyage: {
    apiKey: process.env.VOYAGE_API_KEY,
},
```

**Step 9: Ajouter le script dans package.json**

```json
// agents/package.json - ajouter dans scripts
"site-indexer": "node site-indexer/index.js"
```

**Step 10: Commit**

```bash
git add agents/site-indexer/ agents/package.json agents/shared/config.js
git commit -m "feat: add site-indexer agent with Playwright crawler and embeddings"
```

---

## Task 3: SiteIndexService PHP

**Files:**
- Create: `app/Services/Crawler/SiteIndexService.php`
- Test: `tests/Unit/Services/SiteIndexServiceTest.php`

**Step 1: Créer le service**

```php
// app/Services/Crawler/SiteIndexService.php
<?php

namespace App\Services\Crawler;

use App\Models\Site;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Log;

class SiteIndexService
{
    private const AGENT_PATH = 'agents/site-indexer/index.js';

    public function indexSite(Site $site, bool $delta = false): array
    {
        $command = [
            'node',
            base_path(self::AGENT_PATH),
            '--siteId', (string) $site->id,
            '--siteUrl', "https://{$site->domain}",
            '--maxPages', '500',
        ];

        if ($delta) {
            $command[] = '--delta';
        }

        Log::info('SiteIndexService: Starting indexation', [
            'site_id' => $site->id,
            'domain' => $site->domain,
            'delta' => $delta,
        ]);

        $result = Process::path(base_path('agents'))
            ->timeout(600) // 10 minutes
            ->run(implode(' ', $command));

        if (!$result->successful()) {
            Log::error('SiteIndexService: Indexation failed', [
                'site_id' => $site->id,
                'error' => $result->errorOutput(),
            ]);
            throw new \RuntimeException('Site indexation failed: ' . $result->errorOutput());
        }

        $output = json_decode($result->output(), true);

        $site->update(['last_crawled_at' => now()]);

        Log::info('SiteIndexService: Indexation completed', [
            'site_id' => $site->id,
            'pages_indexed' => $output['pages_indexed'] ?? 0,
        ]);

        return $output;
    }

    public function getIndexPath(Site $site): string
    {
        return storage_path("indexes/site_{$site->id}.sqlite");
    }

    public function hasIndex(Site $site): bool
    {
        return file_exists($this->getIndexPath($site));
    }

    public function deleteIndex(Site $site): bool
    {
        $path = $this->getIndexPath($site);
        if (file_exists($path)) {
            return unlink($path);
        }
        return true;
    }
}
```

**Step 2: Créer le test**

```php
// tests/Unit/Services/SiteIndexServiceTest.php
<?php

namespace Tests\Unit\Services;

use App\Models\Site;
use App\Models\Team;
use App\Models\User;
use App\Services\Crawler\SiteIndexService;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class SiteIndexServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_get_index_path_returns_correct_path(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(['owner_id' => $user->id]);
        $site = Site::factory()->create(['team_id' => $team->id]);

        $service = new SiteIndexService();
        $path = $service->getIndexPath($site);

        $this->assertStringContainsString("site_{$site->id}.sqlite", $path);
        $this->assertStringContainsString('storage/indexes', $path);
    }

    public function test_has_index_returns_false_when_no_index(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(['owner_id' => $user->id]);
        $site = Site::factory()->create(['team_id' => $team->id]);

        $service = new SiteIndexService();

        $this->assertFalse($service->hasIndex($site));
    }
}
```

**Step 3: Run tests**

```bash
php artisan test tests/Unit/Services/SiteIndexServiceTest.php
```

**Step 4: Commit**

```bash
git add app/Services/Crawler/SiteIndexService.php tests/Unit/Services/SiteIndexServiceTest.php
git commit -m "feat: add SiteIndexService for managing site indexes"
```

---

## Task 4: WeeklySiteCrawlJob

**Files:**
- Create: `app/Jobs/WeeklySiteCrawlJob.php`
- Create: `app/Jobs/SiteIndexJob.php`
- Create: `app/Console/Commands/ScheduleSiteCrawls.php`

**Step 1: Créer SiteIndexJob (job individuel par site)**

```php
// app/Jobs/SiteIndexJob.php
<?php

namespace App\Jobs;

use App\Models\Site;
use App\Services\Crawler\SiteIndexService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SiteIndexJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $timeout = 900; // 15 minutes
    public int $backoff = 120;

    public function __construct(
        public readonly Site $site,
        public readonly bool $delta = true,
    ) {}

    public function handle(SiteIndexService $indexService): void
    {
        Log::info('SiteIndexJob: Starting', [
            'site_id' => $this->site->id,
            'domain' => $this->site->domain,
            'delta' => $this->delta,
        ]);

        try {
            $result = $indexService->indexSite($this->site, $this->delta);

            Log::info('SiteIndexJob: Completed', [
                'site_id' => $this->site->id,
                'pages_indexed' => $result['pages_indexed'] ?? 0,
            ]);
        } catch (\Exception $e) {
            Log::error('SiteIndexJob: Failed', [
                'site_id' => $this->site->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    public function tags(): array
    {
        return [
            'site-indexer',
            'site:' . $this->site->id,
        ];
    }
}
```

**Step 2: Créer le command pour le scheduling chunked**

```php
// app/Console/Commands/ScheduleSiteCrawls.php
<?php

namespace App\Console\Commands;

use App\Jobs\SiteIndexJob;
use App\Models\Site;
use Illuminate\Console\Command;

class ScheduleSiteCrawls extends Command
{
    protected $signature = 'sites:schedule-crawls {--full : Run full crawl instead of delta}';
    protected $description = 'Schedule site crawl jobs with chunked delays';

    public function handle(): int
    {
        $delta = !$this->option('full');
        $delay = 0;
        $batchSize = 50;
        $delayBetweenBatches = 30; // minutes

        $totalSites = Site::whereNotNull('domain')->count();
        $this->info("Scheduling crawls for {$totalSites} sites...");

        Site::whereNotNull('domain')
            ->chunk($batchSize, function ($sites) use (&$delay, $delayBetweenBatches, $delta) {
                foreach ($sites as $site) {
                    SiteIndexJob::dispatch($site, $delta)
                        ->onQueue('crawl')
                        ->delay(now()->addMinutes($delay));
                }

                $delay += $delayBetweenBatches;
                $this->info("Scheduled batch, next batch in {$delay} minutes");
            });

        $this->info("All crawls scheduled. Last batch will run in ~{$delay} minutes.");

        return Command::SUCCESS;
    }
}
```

**Step 3: Ajouter au scheduler dans app/Console/Kernel.php**

```php
// Dans la méthode schedule()
$schedule->command('sites:schedule-crawls')->weekly()->sundays()->at('03:00');
```

**Step 4: Commit**

```bash
git add app/Jobs/SiteIndexJob.php app/Console/Commands/ScheduleSiteCrawls.php
git commit -m "feat: add weekly site crawl jobs with chunked dispatch"
```

---

## Task 5: Modifier le linking agent

**Files:**
- Modify: `agents/internal-linking-agent/site-scanner.js`
- Create: `agents/internal-linking-agent/index-loader.js`

**Step 1: Créer le loader d'index SQLite**

```javascript
// agents/internal-linking-agent/index-loader.js
import Database from 'better-sqlite3';
import * as sqliteVec from 'sqlite-vec';
import path from 'path';
import { existsSync } from 'fs';

const INDEXES_DIR = path.join(process.cwd(), '..', 'storage', 'indexes');

export function loadSiteIndex(siteId) {
    const dbPath = path.join(INDEXES_DIR, `site_${siteId}.sqlite`);

    if (!existsSync(dbPath)) {
        return { pages: [], hasIndex: false };
    }

    const db = new Database(dbPath, { readonly: true });
    sqliteVec.load(db);

    const pages = db.prepare(`
        SELECT
            id,
            url,
            title,
            meta_description as description,
            h1,
            category,
            tags,
            inbound_links_count as inbound_links
        FROM pages
        WHERE content_text IS NOT NULL
        ORDER BY inbound_links_count ASC
    `).all();

    db.close();

    return {
        pages: pages.map(p => ({
            ...p,
            tags: JSON.parse(p.tags || '[]'),
        })),
        hasIndex: true,
    };
}

export function searchSimilarPages(siteId, embedding, limit = 20) {
    const dbPath = path.join(INDEXES_DIR, `site_${siteId}.sqlite`);

    if (!existsSync(dbPath)) {
        return [];
    }

    const db = new Database(dbPath, { readonly: true });
    sqliteVec.load(db);

    const results = db.prepare(`
        SELECT
            p.id,
            p.url,
            p.title,
            p.meta_description as description,
            p.h1,
            p.category,
            p.inbound_links_count as inbound_links,
            vec_distance_cosine(pv.embedding, ?) as distance
        FROM pages_vec pv
        JOIN pages p ON p.id = pv.page_id
        WHERE p.content_text IS NOT NULL
        ORDER BY distance ASC
        LIMIT ?
    `).all(new Float32Array(embedding), limit);

    db.close();

    return results;
}
```

**Step 2: Modifier site-scanner.js pour fusionner les sources**

```javascript
// agents/internal-linking-agent/site-scanner.js
import mysql from 'mysql2/promise';
import { config } from '../shared/config.js';
import { loadSiteIndex } from './index-loader.js';

export async function loadSitePages(siteId) {
    // Load from SQLite index (crawled pages)
    const { pages: indexedPages, hasIndex } = loadSiteIndex(siteId);

    // Load from MySQL (articles created by SEO Autopilot)
    const articles = await loadPublishedArticles(siteId);

    // Merge and deduplicate
    const urlSet = new Set();
    const allPages = [];

    // Add indexed pages first (external content)
    for (const page of indexedPages) {
        if (!urlSet.has(page.url)) {
            urlSet.add(page.url);
            allPages.push({
                id: `idx_${page.id}`,
                url: page.url,
                title: page.title || '',
                description: page.description || '',
                h1: page.h1 || '',
                keywords: [],
                inbound_links: page.inbound_links || 0,
                source: 'crawled',
            });
        }
    }

    // Add SEO Autopilot articles
    for (const article of articles) {
        if (!urlSet.has(article.url)) {
            urlSet.add(article.url);
            allPages.push({
                id: `art_${article.id}`,
                url: article.url,
                title: article.title || '',
                description: article.description || '',
                h1: article.h1 || '',
                keywords: article.keywords || [],
                inbound_links: article.inbound_links || 0,
                source: 'autopilot',
            });
        }
    }

    console.log(`Loaded ${allPages.length} pages (${indexedPages.length} crawled, ${articles.length} autopilot)`);

    return allPages;
}

async function loadPublishedArticles(siteId) {
    const parsedSiteId = parseInt(siteId, 10);
    if (!parsedSiteId || isNaN(parsedSiteId) || parsedSiteId <= 0) {
        throw new Error('Invalid siteId parameter');
    }

    let connection;

    try {
        connection = await mysql.createConnection({
            host: config.database.host,
            port: config.database.port,
            user: config.database.user,
            password: config.database.password,
            database: config.database.database,
        });

        const [rows] = await connection.execute(`
            SELECT
                id,
                published_url as url,
                title,
                meta_description,
                slug as h1,
                NULL as keywords,
                0 as inbound_links_count
            FROM articles
            WHERE site_id = ?
            AND status = 'published'
            AND published_url IS NOT NULL
            ORDER BY created_at DESC
        `, [siteId]);

        return rows.map(row => ({
            id: row.id,
            url: row.url,
            title: row.title || '',
            description: row.meta_description || '',
            h1: row.h1 || '',
            keywords: parseKeywordsSafely(row.keywords),
            inbound_links: row.inbound_links_count || 0,
        }));

    } finally {
        if (connection) {
            await connection.end();
        }
    }
}

function parseKeywordsSafely(keywords) {
    if (!keywords) return [];
    try {
        const parsed = JSON.parse(keywords);
        return Array.isArray(parsed) ? parsed : [];
    } catch {
        return [];
    }
}

// Keep backward compatibility
export { loadSitePages as loadSiteIndex };
```

**Step 3: Mettre à jour l'index.js du linking agent**

```javascript
// agents/internal-linking-agent/index.js - modifier l'import
import { loadSitePages } from './site-scanner.js';

// Remplacer loadSiteIndex par loadSitePages dans le code
const sitePages = await loadSitePages(siteId);
```

**Step 4: Commit**

```bash
git add agents/internal-linking-agent/
git commit -m "feat: integrate SQLite index into linking agent"
```

---

## Task 6: Intégration onboarding

**Files:**
- Modify: `app/Services/ContentPlan/ContentPlanGeneratorService.php`
- Modify: Ou le controller d'onboarding si distinct

**Step 1: Trouver où l'onboarding crawle le site**

Le crawl initial se fait déjà dans `ContentPlanGeneratorService::generate()`. On va y ajouter l'appel au site-indexer.

**Step 2: Modifier ContentPlanGeneratorService**

```php
// app/Services/ContentPlan/ContentPlanGeneratorService.php
// Ajouter l'import
use App\Services\Crawler\SiteIndexService;

// Ajouter dans le constructeur
public function __construct(
    private readonly SiteCrawlerService $crawler,
    private readonly SiteIndexService $indexer, // NEW
    // ... autres dépendances
) {}

// Dans la méthode generate(), après le crawl existant
public function generate(Site $site): ContentPlan
{
    // Crawl léger existant (sitemap + GSC)
    if (!$site->last_crawled_at) {
        $this->crawler->crawl($site);
        $this->crawler->extractTitlesForPages($site, 30);
    }

    // NEW: Indexation profonde avec embeddings
    dispatch(new \App\Jobs\SiteIndexJob($site, delta: false))
        ->onQueue('crawl');

    // ... reste du code existant
}
```

**Step 3: Commit**

```bash
git add app/Services/ContentPlan/ContentPlanGeneratorService.php
git commit -m "feat: trigger site indexation at onboarding"
```

---

## Task 7: Tests d'intégration

**Files:**
- Create: `tests/Feature/SiteIndexerTest.php`

**Step 1: Créer le test d'intégration**

```php
// tests/Feature/SiteIndexerTest.php
<?php

namespace Tests\Feature;

use App\Jobs\SiteIndexJob;
use App\Models\Site;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SiteIndexerTest extends TestCase
{
    use RefreshDatabase;

    public function test_site_index_job_is_queued_at_onboarding(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $team = Team::factory()->create(['owner_id' => $user->id]);
        $site = Site::factory()->create([
            'team_id' => $team->id,
            'domain' => 'example.com',
        ]);

        // Simulate onboarding trigger
        SiteIndexJob::dispatch($site, delta: false)->onQueue('crawl');

        Queue::assertPushedOn('crawl', SiteIndexJob::class, function ($job) use ($site) {
            return $job->site->id === $site->id && $job->delta === false;
        });
    }

    public function test_weekly_crawl_uses_delta_mode(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $team = Team::factory()->create(['owner_id' => $user->id]);
        $site = Site::factory()->create([
            'team_id' => $team->id,
            'domain' => 'example.com',
            'last_crawled_at' => now()->subDays(7),
        ]);

        // Simulate weekly trigger
        SiteIndexJob::dispatch($site, delta: true)->onQueue('crawl');

        Queue::assertPushedOn('crawl', SiteIndexJob::class, function ($job) {
            return $job->delta === true;
        });
    }
}
```

**Step 2: Run tests**

```bash
php artisan test tests/Feature/SiteIndexerTest.php
```

**Step 3: Commit final**

```bash
git add tests/Feature/SiteIndexerTest.php
git commit -m "test: add site indexer integration tests"
```

---

## Récapitulatif

| Task | Description | Fichiers clés |
|------|-------------|---------------|
| 1 | VoyageProvider PHP | `app/Services/LLM/Providers/VoyageProvider.php` |
| 2 | Agent site-indexer Node.js | `agents/site-indexer/*` |
| 3 | SiteIndexService PHP | `app/Services/Crawler/SiteIndexService.php` |
| 4 | Jobs crawl hebdo | `app/Jobs/SiteIndexJob.php`, `ScheduleSiteCrawls.php` |
| 5 | Linking agent modifié | `agents/internal-linking-agent/site-scanner.js` |
| 6 | Intégration onboarding | `ContentPlanGeneratorService.php` |
| 7 | Tests | `tests/Feature/SiteIndexerTest.php` |

**Ordre d'exécution :** 1 → 2 → 3 → 4 → 5 → 6 → 7
