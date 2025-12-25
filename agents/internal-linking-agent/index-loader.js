// agents/internal-linking-agent/index-loader.js
import Database from 'better-sqlite3';
import * as sqliteVec from 'sqlite-vec';
import path from 'path';
import { existsSync } from 'fs';

const INDEXES_DIR = path.join(process.cwd(), '..', 'storage', 'indexes');

function parseTagsSafely(tags) {
    if (!tags) return [];
    try {
        const parsed = JSON.parse(tags);
        return Array.isArray(parsed) ? parsed : [];
    } catch {
        return [];
    }
}

export function loadSiteIndex(siteId) {
    // Validate siteId
    const parsedId = parseInt(siteId, 10);
    if (!parsedId || isNaN(parsedId) || parsedId <= 0) {
        return { pages: [], hasIndex: false };
    }

    const dbPath = path.join(INDEXES_DIR, `site_${parsedId}.sqlite`);

    if (!existsSync(dbPath)) {
        return { pages: [], hasIndex: false };
    }

    let db;
    try {
        db = new Database(dbPath, { readonly: true });
        sqliteVec.load(db);

        const pages = db.prepare(`
            SELECT
                id,
                url,
                title,
                meta_description as description,
                h1,
                category,
                tags,
                inbound_links_count as inbound_links
            FROM pages
            WHERE content_text IS NOT NULL
            ORDER BY inbound_links_count ASC
        `).all();

        return {
            pages: pages.map(p => ({
                ...p,
                tags: parseTagsSafely(p.tags),
            })),
            hasIndex: true,
        };
    } catch (error) {
        console.error(`Failed to load site index: ${error.message}`);
        return { pages: [], hasIndex: false };
    } finally {
        if (db) db.close();
    }
}

export function searchSimilarPages(siteId, embedding, limit = 20) {
    // Validate siteId
    const parsedId = parseInt(siteId, 10);
    if (!parsedId || isNaN(parsedId) || parsedId <= 0) {
        return [];
    }

    const dbPath = path.join(INDEXES_DIR, `site_${parsedId}.sqlite`);

    if (!existsSync(dbPath)) {
        return [];
    }

    let db;
    try {
        db = new Database(dbPath, { readonly: true });
        sqliteVec.load(db);

        const results = db.prepare(`
            SELECT
                p.id,
                p.url,
                p.title,
                p.meta_description as description,
                p.h1,
                p.category,
                p.inbound_links_count as inbound_links,
                vec_distance_cosine(pv.embedding, ?) as distance
            FROM pages_vec pv
            JOIN pages p ON p.id = pv.page_id
            WHERE p.content_text IS NOT NULL
            ORDER BY distance ASC
            LIMIT ?
        `).all(new Float32Array(embedding), limit);

        return results;
    } catch (error) {
        console.error(`Failed to search similar pages: ${error.message}`);
        return [];
    } finally {
        if (db) db.close();
    }
}
