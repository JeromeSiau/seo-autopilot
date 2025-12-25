import { launchBrowser } from '../shared/browser.js';

const RATE_LIMIT_MS = 500;

/**
 * Normalizes a URL by removing trailing slashes and UTM parameters.
 * @param {string} url - The URL to normalize
 * @returns {string} Normalized URL
 */
export function normalizeUrl(url) {
    try {
        const urlObj = new URL(url);

        // Remove UTM and common tracking parameters
        const paramsToRemove = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term', 'fbclid', 'gclid'];
        paramsToRemove.forEach(param => urlObj.searchParams.delete(param));

        // Remove trailing slash from pathname (except for root)
        if (urlObj.pathname.length > 1 && urlObj.pathname.endsWith('/')) {
            urlObj.pathname = urlObj.pathname.slice(0, -1);
        }

        // Remove fragment
        urlObj.hash = '';

        return urlObj.href;
    } catch (error) {
        return url;
    }
}

/**
 * Crawls a sitemap XML file recursively.
 * @param {string} sitemapUrl - The sitemap URL
 * @param {number} depth - Current recursion depth
 * @param {number} maxDepth - Maximum recursion depth
 * @returns {Promise<string[]>} Array of URLs found in sitemap
 */
export async function crawlSitemap(sitemapUrl, depth = 0, maxDepth = 5) {
    if (depth >= maxDepth) {
        console.log(`Max sitemap depth reached: ${sitemapUrl}`);
        return [];
    }

    const urls = new Set();

    try {
        const response = await fetch(sitemapUrl);
        if (!response.ok) {
            throw new Error(`Failed to fetch sitemap: ${response.status}`);
        }

        const xml = await response.text();

        // Extract URLs from <loc> tags
        const locRegex = /<loc>(.*?)<\/loc>/g;
        let match;

        while ((match = locRegex.exec(xml)) !== null) {
            const url = match[1].trim();

            // Check if this is a nested sitemap
            if (url.endsWith('.xml')) {
                console.log(`Found nested sitemap: ${url}`);
                const nestedUrls = await crawlSitemap(url, depth + 1, maxDepth);
                nestedUrls.forEach(u => urls.add(normalizeUrl(u)));
            } else {
                urls.add(normalizeUrl(url));
            }
        }

        console.log(`Found ${urls.size} URLs in sitemap: ${sitemapUrl}`);
    } catch (error) {
        console.error(`Error crawling sitemap ${sitemapUrl}:`, error.message);
    }

    return Array.from(urls);
}

/**
 * Crawls a site starting from the homepage, discovering pages through navigation.
 * @param {string} siteUrl - The site's base URL
 * @param {Object} options - Crawl options
 * @param {number} [options.maxPages] - Maximum number of pages to crawl
 * @param {number} [options.rateLimit=500] - Delay between requests in ms
 * @returns {Promise<Array>} Array of page objects with {url, html}
 */
export async function crawlSite(siteUrl, options = {}) {
    const maxPages = options.maxPages || Infinity;
    const rateLimit = options.rateLimit || RATE_LIMIT_MS;

    const baseUrl = new URL(siteUrl);
    const visited = new Set();
    const queue = [normalizeUrl(siteUrl)];
    const pages = [];

    const browser = await launchBrowser();

    try {
        while (queue.length > 0 && visited.size < maxPages) {
            const url = queue.shift();

            // Skip if already visited
            if (visited.has(url)) {
                continue;
            }

            visited.add(url);

            try {
                console.log(`Crawling [${visited.size}/${maxPages}]: ${url}`);

                const page = await browser.newPage();

                // Set viewport and user agent
                await page.setViewport({ width: 1920, height: 1080 });
                await page.setUserAgent('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');

                // Navigate with timeout
                await page.goto(url, {
                    waitUntil: 'networkidle2',
                    timeout: 30000,
                });

                // Get HTML content
                const html = await page.content();

                // Extract links for further crawling
                const links = await page.evaluate(() => {
                    return Array.from(document.querySelectorAll('a[href]'))
                        .map(a => a.href)
                        .filter(href => href && href.startsWith('http'));
                });

                await page.close();

                // Store page data
                pages.push({ url, html });

                // Add new links to queue
                for (const link of links) {
                    const normalizedLink = normalizeUrl(link);
                    const linkUrl = new URL(normalizedLink);

                    // Only crawl same-domain links
                    if (linkUrl.hostname === baseUrl.hostname && !visited.has(normalizedLink)) {
                        queue.push(normalizedLink);
                    }
                }

                // Rate limiting
                await new Promise(resolve => setTimeout(resolve, rateLimit));

            } catch (error) {
                console.error(`Error crawling ${url}:`, error.message);
                // Continue with next URL
            }
        }

        console.log(`Crawl complete. Visited ${visited.size} pages.`);
    } finally {
        await browser.close();
    }

    return pages;
}
