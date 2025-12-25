import Database from 'better-sqlite3';
import * as sqliteVec from 'sqlite-vec';
import path from 'path';
import fs from 'fs';

/**
 * Gets or creates a SQLite database for the given site.
 * @param {string} siteId - The site ID
 * @returns {Database} The database instance
 */
export function getDatabase(siteId) {
    // Sanitize siteId to prevent directory traversal
    const sanitizedSiteId = String(siteId).replace(/[^a-zA-Z0-9_-]/g, '');
    if (!sanitizedSiteId || sanitizedSiteId !== String(siteId)) {
        throw new Error('Invalid siteId: must be alphanumeric');
    }

    // Database path: storage/indexes/site_{siteId}.sqlite
    const dbDir = path.join(process.cwd(), '..', 'storage', 'indexes');

    // Create directory if it doesn't exist
    if (!fs.existsSync(dbDir)) {
        fs.mkdirSync(dbDir, { recursive: true });
    }

    const dbPath = path.join(dbDir, `site_${sanitizedSiteId}.sqlite`);
    const db = new Database(dbPath);

    // Load sqlite-vec extension
    sqliteVec.load(db);

    // Enable WAL mode for better concurrency
    db.pragma('journal_mode = WAL');

    // Create tables if they don't exist
    db.exec(`
        CREATE TABLE IF NOT EXISTS pages (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            url TEXT UNIQUE NOT NULL,
            title TEXT,
            h1 TEXT,
            meta_description TEXT,
            content TEXT,
            category TEXT,
            tags TEXT,
            internal_links TEXT,
            created_at INTEGER DEFAULT (unixepoch()),
            updated_at INTEGER DEFAULT (unixepoch())
        );

        CREATE INDEX IF NOT EXISTS idx_pages_url ON pages(url);
        CREATE INDEX IF NOT EXISTS idx_pages_category ON pages(category);

        CREATE VIRTUAL TABLE IF NOT EXISTS embeddings USING vec0(
            page_id INTEGER PRIMARY KEY,
            embedding FLOAT[1024]
        );
    `);

    return db;
}

/**
 * Inserts or updates a page in the database.
 * @param {Database} db - The database instance
 * @param {Object} pageData - The page data
 * @returns {number} The page ID
 */
export function upsertPage(db, pageData) {
    const stmt = db.prepare(`
        INSERT INTO pages (url, title, h1, meta_description, content, category, tags, internal_links, updated_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, unixepoch())
        ON CONFLICT(url) DO UPDATE SET
            title = excluded.title,
            h1 = excluded.h1,
            meta_description = excluded.meta_description,
            content = excluded.content,
            category = excluded.category,
            tags = excluded.tags,
            internal_links = excluded.internal_links,
            updated_at = unixepoch()
    `);

    const info = stmt.run(
        pageData.url,
        pageData.title || null,
        pageData.h1 || null,
        pageData.metaDescription || null,
        pageData.content || null,
        pageData.category || null,
        pageData.tags ? JSON.stringify(pageData.tags) : null,
        pageData.internalLinks ? JSON.stringify(pageData.internalLinks) : null
    );

    // Get the page ID (either newly inserted or existing)
    if (info.lastInsertRowid) {
        return Number(info.lastInsertRowid);
    }

    // If it was an update, get the ID by URL
    const page = db.prepare('SELECT id FROM pages WHERE url = ?').get(pageData.url);
    return page.id;
}

/**
 * Inserts or updates an embedding for a page.
 * Virtual tables (vec0) don't support ON CONFLICT, so we delete then insert.
 * @param {Database} db - The database instance
 * @param {number} pageId - The page ID
 * @param {number[]} embedding - The embedding vector
 */
export function upsertEmbedding(db, pageId, embedding) {
    // Convert embedding array to JSON string for storage
    const embeddingJson = JSON.stringify(embedding);

    // Virtual tables don't support UPSERT, so delete first then insert
    db.prepare('DELETE FROM embeddings WHERE page_id = ?').run(pageId);
    db.prepare('INSERT INTO embeddings (page_id, embedding) VALUES (?, ?)').run(pageId, embeddingJson);
}

/**
 * Searches for pages with similar embeddings using vector search.
 * @param {Database} db - The database instance
 * @param {number[]} embedding - The query embedding vector
 * @param {number} limit - Maximum number of results
 * @returns {Array} Array of similar pages with scores
 */
export function searchSimilar(db, embedding, limit = 10) {
    const embeddingJson = JSON.stringify(embedding);

    const stmt = db.prepare(`
        SELECT
            p.id,
            p.url,
            p.title,
            p.h1,
            p.meta_description,
            p.content,
            p.category,
            p.tags,
            vec_distance_cosine(e.embedding, ?) as distance
        FROM embeddings e
        JOIN pages p ON p.id = e.page_id
        ORDER BY distance ASC
        LIMIT ?
    `);

    const results = stmt.all(embeddingJson, limit);

    // Parse JSON fields
    return results.map(row => ({
        ...row,
        tags: row.tags ? JSON.parse(row.tags) : [],
        score: 1 - row.distance, // Convert distance to similarity score
    }));
}

/**
 * Gets all pages from the database.
 * @param {Database} db - The database instance
 * @returns {Array} Array of all pages
 */
export function getAllPages(db) {
    const stmt = db.prepare(`
        SELECT id, url, title, h1, meta_description, content, category, tags, internal_links, created_at, updated_at
        FROM pages
        ORDER BY created_at DESC
    `);

    const results = stmt.all();

    // Parse JSON fields
    return results.map(row => ({
        ...row,
        tags: row.tags ? JSON.parse(row.tags) : [],
        internalLinks: row.internal_links ? JSON.parse(row.internal_links) : [],
    }));
}

/**
 * Gets a page by URL.
 * @param {Database} db - The database instance
 * @param {string} url - The page URL
 * @returns {Object|null} The page object or null
 */
export function getPageByUrl(db, url) {
    const stmt = db.prepare(`
        SELECT id, url, title, h1, meta_description, content, category, tags, internal_links, created_at, updated_at
        FROM pages
        WHERE url = ?
    `);

    const row = stmt.get(url);

    if (!row) {
        return null;
    }

    return {
        ...row,
        tags: row.tags ? JSON.parse(row.tags) : [],
        internalLinks: row.internal_links ? JSON.parse(row.internal_links) : [],
    };
}

/**
 * Gets all known URLs from the database.
 * @param {Database} db - The database instance
 * @returns {string[]} Array of URLs
 */
export function getKnownUrls(db) {
    const stmt = db.prepare('SELECT url FROM pages');
    const results = stmt.all();
    return results.map(row => row.url);
}
