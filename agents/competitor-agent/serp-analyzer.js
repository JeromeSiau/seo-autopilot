import { launchBrowser, randomDelay } from '../shared/browser.js';

export async function analyzeCompetitors(urls, onProgress = null) {
    const results = [];

    for (let i = 0; i < urls.length; i++) {
        const url = urls[i];

        if (onProgress) {
            onProgress(i + 1, urls.length, url, null);
        }

        const analysis = await analyzePage(url);
        results.push(analysis);

        if (onProgress && analysis.wordCount > 0) {
            onProgress(i + 1, urls.length, url, analysis);
        }

        await randomDelay(1000, 2000);
    }

    return results;
}

async function analyzePage(url) {
    const browser = await launchBrowser();

    try {
        const page = await browser.newPage();
        await page.goto(url, { waitUntil: 'networkidle2', timeout: 30000 });

        const data = await page.evaluate(() => {
            // Remove non-content elements
            ['nav', 'header', 'footer', 'aside', '.sidebar', '.menu',
             '.comments', '.ad', 'script', 'style'].forEach(sel => {
                document.querySelectorAll(sel).forEach(el => el.remove());
            });

            // Get all headings
            const headings = { h1: 0, h2: 0, h3: 0 };
            const allHeadings = [];

            document.querySelectorAll('h1, h2, h3').forEach(h => {
                const level = h.tagName.toLowerCase();
                headings[level]++;
                allHeadings.push({ level, text: h.textContent?.trim() || '' });
            });

            // Get word count from main content
            const contentSelectors = ['article', 'main', '.content', '.post-content', '.entry-content'];
            let content = '';

            for (const sel of contentSelectors) {
                const el = document.querySelector(sel);
                if (el) {
                    content = el.textContent || '';
                    break;
                }
            }

            if (!content) {
                content = document.body.textContent || '';
            }

            const wordCount = content.trim().split(/\s+/).filter(w => w.length > 0).length;

            // Count media
            const images = document.querySelectorAll('article img, main img, .content img').length;
            const videos = document.querySelectorAll('iframe[src*="youtube"], iframe[src*="vimeo"], video').length;
            const tables = document.querySelectorAll('table').length;
            const lists = document.querySelectorAll('ul, ol').length;

            return {
                headings,
                allHeadings,
                wordCount,
                media: { images, videos, tables, lists }
            };
        });

        return {
            url,
            ...data,
        };

    } catch (error) {
        console.error(`Failed to analyze ${url}:`, error.message);
        return { url, wordCount: 0, headings: {}, allHeadings: [], media: {} };
    } finally {
        await browser.close();
    }
}
