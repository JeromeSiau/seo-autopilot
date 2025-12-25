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

    } catch (error) {
        console.error('Database error in loadPublishedArticles:', error.message);
        throw new Error(`Failed to load published articles: ${error.message}`);
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
