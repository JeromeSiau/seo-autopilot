import { createCrawler, randomDelay } from '../shared/browser.js';
import { RequestQueue } from 'crawlee';

export async function scrapeUrls(urls, onProgress = null) {
    const results = [];
    let processed = 0;
    const total = urls.length;

    const requestQueue = await RequestQueue.open();

    for (const url of urls) {
        await requestQueue.addRequest({ url });
    }

    const crawler = createCrawler({
        maxConcurrency: 2,
        requestHandler: async ({ page, request }) => {
            try {
                // Wait for main content
                await page.waitForSelector('body', { timeout: 15000 });
                await randomDelay(500, 1500);

                // Extract content
                const data = await page.evaluate(() => {
                    // Remove unwanted elements
                    const selectorsToRemove = [
                        'nav', 'header', 'footer', 'aside',
                        '.sidebar', '.menu', '.navigation',
                        '.comments', '.advertisement', '.ad',
                        'script', 'style', 'noscript'
                    ];
                    selectorsToRemove.forEach(sel => {
                        document.querySelectorAll(sel).forEach(el => el.remove());
                    });

                    // Get title
                    const title = document.querySelector('h1')?.textContent?.trim() ||
                                  document.querySelector('title')?.textContent?.trim() ||
                                  '';

                    // Get headings
                    const headings = [];
                    document.querySelectorAll('h2, h3').forEach(h => {
                        headings.push({
                            level: h.tagName.toLowerCase(),
                            text: h.textContent?.trim() || ''
                        });
                    });

                    // Get main content
                    const contentSelectors = [
                        'article', 'main', '.content', '.post-content',
                        '.entry-content', '.article-content', '#content'
                    ];

                    let content = '';
                    for (const sel of contentSelectors) {
                        const el = document.querySelector(sel);
                        if (el) {
                            content = el.textContent?.trim() || '';
                            break;
                        }
                    }

                    // Fallback to body
                    if (!content || content.length < 200) {
                        content = document.body.textContent?.trim() || '';
                    }

                    // Clean up whitespace
                    content = content.replace(/\s+/g, ' ').trim();

                    return { title, headings, content };
                });

                results.push({
                    url: request.url,
                    title: data.title,
                    headings: data.headings,
                    content: data.content.substring(0, 10000), // Limit content size
                });

            } catch (error) {
                console.error(`Failed to scrape ${request.url}:`, error.message);
            }

            processed++;
            if (onProgress) {
                onProgress(processed, total);
            }
        },
        failedRequestHandler: async ({ request }) => {
            console.error(`Request failed: ${request.url}`);
            processed++;
            if (onProgress) {
                onProgress(processed, total);
            }
        },
    });

    await crawler.run();

    return results;
}
