import { PuppeteerCrawler } from 'crawlee';
import puppeteer from 'puppeteer-extra';
import StealthPlugin from 'puppeteer-extra-plugin-stealth';

// Enable stealth mode
puppeteer.use(StealthPlugin());

/** Shared browser launch arguments for consistency */
const BROWSER_ARGS = [
    '--no-sandbox',
    '--disable-setuid-sandbox',
    '--disable-dev-shm-usage',
    '--disable-accelerated-2d-canvas',
    '--disable-gpu',
];

/**
 * Creates a Puppeteer crawler with stealth mode enabled.
 * The crawler automatically manages browser lifecycle.
 * @param {Object} options - Crawler configuration options
 * @param {number} [options.maxConcurrency=3] - Maximum concurrent requests
 * @param {number} [options.maxRetries=2] - Maximum retry attempts
 * @param {number} [options.timeout=60] - Request timeout in seconds
 * @returns {PuppeteerCrawler} Configured crawler instance
 */
export function createCrawler(options = {}) {
    return new PuppeteerCrawler({
        launchContext: {
            launcher: puppeteer,
            launchOptions: {
                headless: true,
                args: BROWSER_ARGS,
            },
        },
        maxConcurrency: options.maxConcurrency || 3,
        maxRequestRetries: options.maxRetries || 2,
        requestHandlerTimeoutSecs: options.timeout || 60,
        ...options,
    });
}

/**
 * Launches a standalone Puppeteer browser with stealth mode.
 * IMPORTANT: Caller is responsible for closing the browser with browser.close()
 * @returns {Promise<Browser>} Puppeteer browser instance
 * @throws {Error} If browser fails to launch
 */
export async function launchBrowser() {
    try {
        return await puppeteer.launch({
            headless: true,
            args: BROWSER_ARGS,
        });
    } catch (error) {
        console.error('Failed to launch browser:', error.message);
        throw new Error(`Browser launch failed: ${error.message}`);
    }
}

/**
 * Returns a promise that resolves after a random delay.
 * Useful for adding human-like behavior to avoid bot detection.
 * @param {number} [min=1000] - Minimum delay in milliseconds
 * @param {number} [max=3000] - Maximum delay in milliseconds
 * @returns {Promise<void>}
 */
export function randomDelay(min = 1000, max = 3000) {
    return new Promise(resolve =>
        setTimeout(resolve, Math.floor(Math.random() * (max - min + 1) + min))
    );
}
