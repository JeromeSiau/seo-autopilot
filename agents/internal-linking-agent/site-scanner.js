import mysql from 'mysql2/promise';
import { config } from '../shared/config.js';

export async function loadSiteIndex(siteId) {
    const connection = await mysql.createConnection({
        host: config.database.host,
        port: config.database.port,
        user: config.database.user,
        password: config.database.password,
        database: config.database.database,
    });

    try {
        // Query published articles from the site as linkable pages
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
            keywords: row.keywords ? JSON.parse(row.keywords) : [],
            inbound_links: row.inbound_links_count || 0,
        }));

    } finally {
        await connection.end();
    }
}
