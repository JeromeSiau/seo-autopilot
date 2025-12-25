import { Readability } from '@mozilla/readability';
import { JSDOM } from 'jsdom';
import { generateJSON } from '../shared/llm.js';

/**
 * Extracts clean content from HTML using Readability.
 * @param {string} html - The HTML content
 * @param {string} url - The page URL
 * @returns {Object} Extracted content with title, h1, meta, content, and internal links
 */
export function extractContent(html, url) {
    const dom = new JSDOM(html, { url });
    const document = dom.window.document;

    // Extract with Readability
    const reader = new Readability(document);
    const article = reader.parse();

    // Extract title
    const title = article?.title || document.querySelector('title')?.textContent || '';

    // Extract H1
    const h1 = document.querySelector('h1')?.textContent?.trim() || '';

    // Extract meta description
    const metaDescription = document.querySelector('meta[name="description"]')?.getAttribute('content') || '';

    // Extract content (from Readability or fallback to body text)
    const content = article?.textContent || document.body?.textContent || '';

    // Extract internal links (same domain)
    const internalLinks = [];
    const baseUrl = new URL(url);
    const links = document.querySelectorAll('a[href]');

    for (const link of links) {
        try {
            const href = link.getAttribute('href');
            if (!href) continue;

            const absoluteUrl = new URL(href, url);

            // Only include same-domain links
            if (absoluteUrl.hostname === baseUrl.hostname) {
                internalLinks.push({
                    url: absoluteUrl.href,
                    text: link.textContent?.trim() || '',
                });
            }
        } catch (error) {
            // Ignore invalid URLs
        }
    }

    return {
        title: title.trim(),
        h1: h1.trim(),
        metaDescription: metaDescription.trim(),
        content: content.trim(),
        internalLinks,
    };
}

/**
 * Detects the category of a page from breadcrumbs, schema.org, or URL patterns.
 * Falls back to LLM if automatic detection fails.
 * @param {Document} document - The JSDOM document
 * @param {string} url - The page URL
 * @param {string} title - The page title
 * @param {string} content - The page content excerpt
 * @returns {Promise<string|null>} The detected category or null
 */
export async function detectCategory(document, url, title, content) {
    // Try breadcrumbs first
    const breadcrumbCategory = extractCategoryFromBreadcrumbs(document);
    if (breadcrumbCategory) {
        return breadcrumbCategory;
    }

    // Try schema.org
    const schemaCategory = extractCategoryFromSchema(document);
    if (schemaCategory) {
        return schemaCategory;
    }

    // Try URL patterns
    const urlCategory = extractCategoryFromUrl(url);
    if (urlCategory) {
        return urlCategory;
    }

    // Fallback to LLM
    try {
        const excerpt = content.substring(0, 500);
        const prompt = `Determine the main category for this webpage.

Title: ${title}
Content excerpt: ${excerpt}

Respond with JSON containing a single category name, or null if no clear category can be determined.
Example: {"category": "Technology"} or {"category": null}`;

        const result = await generateJSON(prompt, 'You are a content categorization assistant.');
        return result.category || null;
    } catch (error) {
        console.error('LLM category detection failed:', error.message);
        return null;
    }
}

/**
 * Extracts category from breadcrumb navigation.
 * @param {Document} document - The JSDOM document
 * @returns {string|null} The category or null
 */
function extractCategoryFromBreadcrumbs(document) {
    // Look for common breadcrumb selectors
    const selectors = [
        '[itemtype*="BreadcrumbList"] [itemprop="name"]',
        '.breadcrumb a',
        '.breadcrumbs a',
        '[aria-label="breadcrumb"] a',
        '[aria-label="Breadcrumb"] a',
    ];

    for (const selector of selectors) {
        const items = document.querySelectorAll(selector);
        if (items.length >= 2) {
            // Get the second-to-last item (usually the category, last is current page)
            const categoryItem = items[items.length - 2];
            const category = categoryItem?.textContent?.trim();
            if (category && category.length > 0) {
                return category;
            }
        }
    }

    return null;
}

/**
 * Extracts category from schema.org markup.
 * @param {Document} document - The JSDOM document
 * @returns {string|null} The category or null
 */
function extractCategoryFromSchema(document) {
    const scripts = document.querySelectorAll('script[type="application/ld+json"]');

    for (const script of scripts) {
        try {
            const data = JSON.parse(script.textContent);

            // Handle arrays of schema objects
            const schemas = Array.isArray(data) ? data : [data];

            for (const schema of schemas) {
                // Look for article category
                if (schema.articleSection) {
                    return schema.articleSection;
                }

                // Look for breadcrumb list
                if (schema['@type'] === 'BreadcrumbList' && schema.itemListElement) {
                    const items = schema.itemListElement;
                    if (items.length >= 2) {
                        const categoryItem = items[items.length - 2];
                        if (categoryItem.name) {
                            return categoryItem.name;
                        }
                    }
                }
            }
        } catch (error) {
            // Ignore invalid JSON
        }
    }

    return null;
}

/**
 * Extracts category from URL patterns.
 * @param {string} url - The page URL
 * @returns {string|null} The category or null
 */
function extractCategoryFromUrl(url) {
    try {
        const urlObj = new URL(url);
        const pathParts = urlObj.pathname.split('/').filter(p => p.length > 0);

        // Common URL patterns: /category/post-slug or /blog/category/post-slug
        if (pathParts.length >= 2) {
            // Skip common prefixes like 'blog', 'post', 'article'
            const skipPrefixes = ['blog', 'post', 'article', 'news', 'posts', 'articles'];
            const firstPart = pathParts[0].toLowerCase();

            if (skipPrefixes.includes(firstPart) && pathParts.length >= 3) {
                // Format: /blog/category/slug -> return category
                return capitalizeWords(pathParts[1]);
            } else if (!skipPrefixes.includes(firstPart)) {
                // Format: /category/slug -> return category
                return capitalizeWords(pathParts[0]);
            }
        }
    } catch (error) {
        // Ignore invalid URLs
    }

    return null;
}

/**
 * Detects tags from the HTML document.
 * @param {Document} document - The JSDOM document
 * @returns {string[]} Array of detected tags
 */
export function detectTags(document) {
    const tags = new Set();

    // Look for rel="tag" links
    const tagLinks = document.querySelectorAll('a[rel="tag"]');
    for (const link of tagLinks) {
        const tag = link.textContent?.trim();
        if (tag) {
            tags.add(tag);
        }
    }

    // Look for common tag class names
    const tagSelectors = [
        '.tag',
        '.tags a',
        '.post-tags a',
        '.article-tags a',
        '[class*="tag"] a',
    ];

    for (const selector of tagSelectors) {
        const elements = document.querySelectorAll(selector);
        for (const el of elements) {
            const tag = el.textContent?.trim();
            if (tag && tag.length > 0) {
                tags.add(tag);
            }
        }
    }

    return Array.from(tags);
}

/**
 * Capitalizes the first letter of each word.
 * @param {string} str - The string to capitalize
 * @returns {string} Capitalized string
 */
function capitalizeWords(str) {
    return str
        .split(/[-_\s]+/)
        .map(word => word.charAt(0).toUpperCase() + word.slice(1).toLowerCase())
        .join(' ');
}
