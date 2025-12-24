import { launchBrowser, randomDelay } from '../shared/browser.js';

export async function searchGoogle(query, maxResults = 10) {
    const browser = await launchBrowser();
    const urls = [];

    try {
        const page = await browser.newPage();

        // Set realistic viewport and user agent
        await page.setViewport({ width: 1920, height: 1080 });

        // Navigate to Google
        const searchUrl = `https://www.google.com/search?q=${encodeURIComponent(query)}&num=${maxResults}`;
        await page.goto(searchUrl, { waitUntil: 'networkidle2', timeout: 30000 });

        // Wait for results
        await page.waitForSelector('#search', { timeout: 10000 });

        // Extract organic result URLs
        const results = await page.evaluate(() => {
            const links = [];
            const resultElements = document.querySelectorAll('#search .g a[href^="http"]');

            resultElements.forEach(el => {
                const href = el.getAttribute('href');
                // Filter out Google's own URLs and ads
                if (href &&
                    !href.includes('google.com') &&
                    !href.includes('youtube.com') &&
                    !href.includes('webcache') &&
                    !href.includes('translate.google')) {
                    links.push(href);
                }
            });

            return [...new Set(links)]; // Dedupe
        });

        urls.push(...results.slice(0, maxResults));

        await randomDelay(1000, 2000);

    } catch (error) {
        console.error(`Google search failed for "${query}":`, error.message);
    } finally {
        await browser.close();
    }

    return urls;
}
