import mysql from 'mysql2/promise';
import { config } from '../shared/config.js';

export async function loadSiteIndex(siteId) {
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
        console.error('Database error in loadSiteIndex:', error.message);
        throw new Error(`Failed to load site index: ${error.message}`);
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
