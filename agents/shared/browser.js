import { PuppeteerCrawler } from 'crawlee';
import puppeteer from 'puppeteer-extra';
import StealthPlugin from 'puppeteer-extra-plugin-stealth';

// Enable stealth mode
puppeteer.use(StealthPlugin());

export function createCrawler(options = {}) {
    return new PuppeteerCrawler({
        launchContext: {
            launcher: puppeteer,
            launchOptions: {
                headless: true,
                args: [
                    '--no-sandbox',
                    '--disable-setuid-sandbox',
                    '--disable-dev-shm-usage',
                    '--disable-accelerated-2d-canvas',
                    '--disable-gpu',
                ],
            },
        },
        maxConcurrency: options.maxConcurrency || 3,
        maxRequestRetries: options.maxRetries || 2,
        requestHandlerTimeoutSecs: options.timeout || 60,
        ...options,
    });
}

export async function launchBrowser() {
    return puppeteer.launch({
        headless: true,
        args: [
            '--no-sandbox',
            '--disable-setuid-sandbox',
            '--disable-dev-shm-usage',
        ],
    });
}

export function randomDelay(min = 1000, max = 3000) {
    return new Promise(resolve =>
        setTimeout(resolve, Math.floor(Math.random() * (max - min + 1) + min))
    );
}
