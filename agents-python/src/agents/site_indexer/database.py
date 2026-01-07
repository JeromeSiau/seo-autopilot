# src/agents/site_indexer/database.py
"""SQLite database for site index."""
import sqlite3
import json
from pathlib import Path


class SiteIndexDB:
    """SQLite database for storing indexed pages."""

    def __init__(self, site_id: int, storage_path: str | None = None):
        if storage_path is None:
            storage_path = Path(__file__).parent.parent.parent.parent.parent / "storage" / "indexes"

        storage_path = Path(storage_path)
        storage_path.mkdir(parents=True, exist_ok=True)

        self.db_path = storage_path / f"site_{site_id}.sqlite"
        self._conn: sqlite3.Connection | None = None
        self._init_db()

    def _get_conn(self) -> sqlite3.Connection:
        if self._conn is None:
            self._conn = sqlite3.connect(str(self.db_path))
            self._conn.row_factory = sqlite3.Row
        return self._conn

    def _init_db(self):
        conn = self._get_conn()
        conn.executescript('''
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
                content_hash TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE IF NOT EXISTS embeddings (
                page_id INTEGER PRIMARY KEY,
                embedding BLOB,
                FOREIGN KEY (page_id) REFERENCES pages(id)
            );

            CREATE INDEX IF NOT EXISTS idx_pages_url ON pages(url);
        ''')
        conn.commit()

    def upsert_page(self, url: str, title: str = "", h1: str = "", meta_description: str = "",
                    content: str = "", category: str = "", tags: list[str] | None = None,
                    internal_links: list[str] | None = None, content_hash: str = "") -> int:
        """Insert or update a page, return its ID."""
        conn = self._get_conn()
        cursor = conn.execute('''
            INSERT INTO pages (url, title, h1, meta_description, content, category, tags, internal_links, content_hash, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)
            ON CONFLICT(url) DO UPDATE SET
                title = excluded.title, h1 = excluded.h1, meta_description = excluded.meta_description,
                content = excluded.content, category = excluded.category, tags = excluded.tags,
                internal_links = excluded.internal_links, content_hash = excluded.content_hash,
                updated_at = CURRENT_TIMESTAMP
            RETURNING id
        ''', (url, title, h1, meta_description, content, category,
              json.dumps(tags or []), json.dumps(internal_links or []), content_hash))
        row = cursor.fetchone()
        conn.commit()
        return row[0]

    def upsert_embedding(self, page_id: int, embedding: list[float]):
        """Store embedding for a page."""
        import struct
        conn = self._get_conn()
        blob = struct.pack(f'{len(embedding)}f', *embedding)
        conn.execute('''
            INSERT INTO embeddings (page_id, embedding) VALUES (?, ?)
            ON CONFLICT(page_id) DO UPDATE SET embedding = excluded.embedding
        ''', (page_id, blob))
        conn.commit()

    def get_known_urls(self) -> list[str]:
        conn = self._get_conn()
        cursor = conn.execute('SELECT url FROM pages')
        return [row[0] for row in cursor.fetchall()]

    def is_unchanged(self, url: str, content_hash: str) -> bool:
        conn = self._get_conn()
        cursor = conn.execute('SELECT content_hash FROM pages WHERE url = ?', (url,))
        row = cursor.fetchone()
        return row is not None and row[0] == content_hash

    def count_pages(self) -> int:
        conn = self._get_conn()
        cursor = conn.execute('SELECT COUNT(*) FROM pages')
        return cursor.fetchone()[0]

    def close(self):
        if self._conn:
            self._conn.close()
            self._conn = None
