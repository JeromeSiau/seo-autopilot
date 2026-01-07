# src/agents/site_indexer/main.py
"""Site indexer agent - crawls and indexes a website."""
import asyncio
import hashlib
import click
from urllib.parse import urlparse
from ..shared.crawler import ContentCrawler
from ..shared.events import EventEmitter
from ..shared.embeddings import VoyageEmbedder
from ..shared.output import emit_json
from .database import SiteIndexDB

AGENT_TYPE = "site-indexer"
MAX_CONTENT_LENGTH = 8000


def validate_url(url: str) -> str:
    parsed = urlparse(url)
    if parsed.scheme not in ("http", "https"):
        raise ValueError("URL must use http or https protocol")
    return url.rstrip("/")


async def run(site_id: int, site_url: str, max_pages: int = 100, delta: bool = False):
    """Main site indexer logic."""
    events = EventEmitter(site_id, AGENT_TYPE)
    db = SiteIndexDB(site_id)
    crawler = ContentCrawler()

    try:
        validated_url = validate_url(site_url)
        await events.started("Starting site indexing", f"Indexing {validated_url}")

        # Get known URLs for delta mode
        known_urls = set()
        if delta:
            known_urls = set(db.get_known_urls())
            print(f"Delta mode: {len(known_urls)} known URLs")

        # For now, just crawl the homepage (deep crawl implementation depends on Crawl4AI API)
        await events.progress("Crawling site...")

        # Simple implementation - crawl single URL
        # TODO: Implement deep crawl when Crawl4AI API is verified
        results = [await crawler.extract(validated_url)]

        indexed_count = 0
        error_count = 0
        embedder = VoyageEmbedder()

        for i, page in enumerate(results):
            if not page["success"]:
                error_count += 1
                continue

            page_url = page["url"]
            markdown = page.get("markdown", "")
            content_hash = hashlib.md5(markdown.encode()).hexdigest()

            if delta and page_url in known_urls:
                if db.is_unchanged(page_url, content_hash):
                    continue

            await events.progress(f"Processing page {i + 1}/{len(results)}",
                progress_current=i + 1, progress_total=len(results))

            page_id = db.upsert_page(
                url=page_url,
                title=page.get("title", ""),
                content=markdown[:MAX_CONTENT_LENGTH],
                content_hash=content_hash,
            )
            indexed_count += 1

            # Generate embedding
            try:
                content_for_embedding = f"{page.get('title', '')}\n\n{markdown}"[:MAX_CONTENT_LENGTH]
                embedding = await embedder.embed(content_for_embedding, "document")
                db.upsert_embedding(page_id, embedding)
            except Exception as e:
                print(f"Embedding failed for {page_url}: {e}")

        total_pages = db.count_pages()

        await events.completed("Site indexing completed", metadata={
            "indexed": indexed_count,
            "errors": error_count,
            "total": total_pages,
        })

        emit_json({
            "pages_indexed": indexed_count,
            "discovered": len(results),
            "errors": error_count,
        })

    except Exception as e:
        await events.error(f"Indexing failed: {e}", e)
        emit_json({"success": False, "error": str(e)})
        raise
    finally:
        db.close()
        await events.close()


@click.command()
@click.option("--siteId", required=True, type=int, help="Site ID")
@click.option("--siteUrl", required=True, help="Site URL to index")
@click.option("--maxPages", default=100, type=int, help="Maximum pages to index")
@click.option("--delta", is_flag=True, help="Only index new/changed pages")
def main(siteid: int, siteurl: str, maxpages: int, delta: bool):
    """Site indexer - crawls and indexes a website."""
    asyncio.run(run(siteid, siteurl, maxpages, delta))


if __name__ == "__main__":
    main()
