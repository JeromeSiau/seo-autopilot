#!/usr/bin/env node

import { Command } from 'commander';
import { JSDOM } from 'jsdom';
import { crawlSite, crawlSitemap, normalizeUrl } from './navigator.js';
import { extractContent, detectCategory, detectTags } from './content-extractor.js';
import { generateEmbedding } from './embedder.js';
import { getDatabase, upsertPage, upsertEmbedding, getKnownUrls } from './db.js';
import { emitStarted, emitProgress, emitCompleted, emitError, closeRedis } from '../shared/event-emitter.js';

const AGENT_TYPE = 'site-indexer';
const MAX_CONTENT_LENGTH = 8000;

/**
 * Validates URL format.
 * @param {string} url - The URL to validate
 * @returns {string} Validated URL
 */
function validateUrl(url) {
    try {
        const parsed = new URL(url);
        if (!['http:', 'https:'].includes(parsed.protocol)) {
            throw new Error('URL must use http or https protocol');
        }
        return parsed.href;
    } catch {
        throw new Error('Invalid URL format');
    }
}

/**
 * Main indexing function.
 */
async function indexSite(siteId, siteUrl, options) {
    const { maxPages, delta } = options;

    // Validate siteUrl
    const validatedUrl = validateUrl(siteUrl);

    let db;
    try {
        // Emit started event
        await emitStarted(siteId, AGENT_TYPE, 'Starting site indexing', `Indexing ${validatedUrl}`);

        // Get database
        db = getDatabase(siteId);

        // Get known URLs if in delta mode
        let knownUrls = new Set();
        if (delta) {
            console.log('Delta mode enabled - only indexing new pages');
            const urls = getKnownUrls(db);
            knownUrls = new Set(urls.map(u => normalizeUrl(u)));
            console.log(`Found ${knownUrls.size} known URLs in database`);
        }

        // Step 1: Discover URLs
        await emitProgress(siteId, AGENT_TYPE, 'Discovering URLs from sitemap');
        const discoveredUrls = new Set();

        // Try sitemap first
        const sitemapUrl = new URL('/sitemap.xml', validatedUrl).href;
        console.log(`Checking for sitemap at: ${sitemapUrl}`);
        const sitemapUrls = await crawlSitemap(sitemapUrl);

        if (sitemapUrls.length > 0) {
            console.log(`Found ${sitemapUrls.length} URLs from sitemap`);
            sitemapUrls.forEach(url => discoveredUrls.add(url));
        } else {
            console.log('No sitemap found or sitemap is empty');
        }

        // Crawl site navigation to discover more URLs
        await emitProgress(siteId, AGENT_TYPE, 'Crawling site navigation for additional URLs');
        console.log('Crawling site for additional URLs...');

        const crawlLimit = maxPages || 100;
        const crawledPages = await crawlSite(validatedUrl, {
            maxPages: crawlLimit,
            rateLimit: 500,
        });

        // Add crawled URLs to discovered set
        crawledPages.forEach(page => discoveredUrls.add(page.url));

        console.log(`Total discovered URLs: ${discoveredUrls.size}`);

        // Filter out known URLs in delta mode
        let urlsToIndex = Array.from(discoveredUrls);
        if (delta) {
            urlsToIndex = urlsToIndex.filter(url => !knownUrls.has(normalizeUrl(url)));
            console.log(`URLs to index after delta filtering: ${urlsToIndex.length}`);
        }

        // Apply maxPages limit
        if (maxPages && urlsToIndex.length > maxPages) {
            urlsToIndex = urlsToIndex.slice(0, maxPages);
        }

        // Step 2: Process each page
        let indexedCount = 0;
        let errorCount = 0;

        for (let i = 0; i < urlsToIndex.length; i++) {
            const url = urlsToIndex[i];

            try {
                await emitProgress(siteId, AGENT_TYPE, `Processing page ${i + 1}/${urlsToIndex.length}`, {
                    progressCurrent: i + 1,
                    progressTotal: urlsToIndex.length,
                    metadata: { url },
                });

                console.log(`\n[${i + 1}/${urlsToIndex.length}] Processing: ${url}`);

                // Find the page HTML from crawled pages
                const crawledPage = crawledPages.find(p => p.url === url);
                let html;

                if (crawledPage) {
                    html = crawledPage.html;
                } else {
                    // If not in crawled pages (from sitemap), fetch it
                    console.log('  Fetching HTML...');
                    const response = await fetch(url);
                    if (!response.ok) {
                        throw new Error(`HTTP ${response.status}`);
                    }
                    html = await response.text();
                }

                // Extract content
                console.log('  Extracting content...');
                const extracted = extractContent(html, url);

                // Create JSDOM for category/tag detection
                const dom = new JSDOM(html, { url });
                const document = dom.window.document;

                // Detect category
                console.log('  Detecting category...');
                const category = await detectCategory(
                    document,
                    url,
                    extracted.title,
                    extracted.content
                );

                // Detect tags
                console.log('  Detecting tags...');
                const tags = detectTags(document);

                // Store in database first (before embedding, so page is saved even if embedding fails)
                console.log('  Storing in database...');
                const pageId = upsertPage(db, {
                    url,
                    title: extracted.title,
                    h1: extracted.h1,
                    metaDescription: extracted.metaDescription,
                    content: extracted.content,
                    category,
                    tags,
                    internalLinks: extracted.internalLinks,
                });

                indexedCount++;
                console.log(`  ✓ Page saved (ID: ${pageId})`);

                // Try to generate embedding (optional - don't fail if it doesn't work)
                try {
                    const contentForEmbedding = [
                        extracted.title,
                        extracted.h1,
                        extracted.metaDescription,
                        extracted.content,
                    ]
                        .filter(Boolean)
                        .join('\n\n')
                        .substring(0, MAX_CONTENT_LENGTH);

                    console.log('  Generating embedding...');
                    const embedding = await generateEmbedding(contentForEmbedding, 'document');
                    upsertEmbedding(db, pageId, embedding);
                    console.log('  ✓ Embedding saved');
                } catch (embeddingError) {
                    console.log(`  ⚠ Embedding skipped: ${embeddingError.message}`);
                }

                // Rate limiting between pages
                await new Promise(resolve => setTimeout(resolve, 500));

            } catch (error) {
                errorCount++;
                console.error(`  ✗ Error: ${error.message}`);
                // Continue with next page
            }
        }

        // Final summary
        const summary = {
            discovered: discoveredUrls.size,
            indexed: indexedCount,
            errors: errorCount,
        };

        console.log('\n=== Indexing Complete ===');
        console.log(`Discovered: ${summary.discovered} pages`);
        console.log(`Indexed: ${summary.indexed} pages`);
        console.log(`Errors: ${summary.errors} pages`);

        await emitCompleted(siteId, AGENT_TYPE, 'Site indexing completed', {
            metadata: summary,
        });

    } catch (error) {
        console.error('Fatal error:', error);
        await emitError(siteId, AGENT_TYPE, 'Site indexing failed', error);
        throw error;
    } finally {
        if (db) db.close();
        await closeRedis();
    }
}

/**
 * CLI setup
 */
const program = new Command();

program
    .name('site-indexer')
    .description('Indexes a website using Playwright crawler and generates embeddings')
    .requiredOption('--siteId <siteId>', 'The site ID')
    .requiredOption('--siteUrl <siteUrl>', 'The site URL to index')
    .option('--maxPages <number>', 'Maximum number of pages to index', parseInt)
    .option('--delta', 'Only index new pages not already in database', false)
    .action(async (options) => {
        try {
            await indexSite(options.siteId, options.siteUrl, {
                maxPages: options.maxPages,
                delta: options.delta,
            });
            process.exit(0);
        } catch (error) {
            console.error('Indexing failed:', error.message);
            process.exit(1);
        }
    });

program.parse();
