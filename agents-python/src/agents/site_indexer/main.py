# src/agents/site_indexer/main.py
"""Site indexer agent - crawls and indexes a website."""
import asyncio
import hashlib
import json
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


def parse_seed_urls(seed_urls: str | None) -> list[str]:
    if not seed_urls:
        return []

    try:
        data = json.loads(seed_urls)
    except json.JSONDecodeError as exc:
        raise ValueError("seedUrls must be valid JSON") from exc

    if not isinstance(data, list):
        raise ValueError("seedUrls must decode to a list")

    return [str(url).rstrip("/") for url in data if str(url).strip()]


def build_crawl_targets(site_url: str, seed_urls: list[str], max_pages: int) -> list[str]:
    site_host = urlparse(site_url).netloc.lower()
    targets: list[str] = []

    for candidate in seed_urls:
        try:
            validated = validate_url(candidate)
        except ValueError:
            continue

        if urlparse(validated).netloc.lower() != site_host:
            continue

        if validated not in targets:
            targets.append(validated)

        if len(targets) >= max_pages:
            break

    return targets or [site_url]


async def run(
    site_id: int,
    site_url: str,
    max_pages: int = 100,
    delta: bool = False,
    seed_urls: list[str] | None = None,
    storage_path: str | None = None,
):
    """Main site indexer logic."""
    events = EventEmitter(site_id, AGENT_TYPE)
    crawler = ContentCrawler()
    db = None

    try:
        validated_url = validate_url(site_url)
        db = SiteIndexDB(site_id, storage_path=storage_path)
        await events.started("Starting site indexing", f"Indexing {validated_url}")

        # Get known URLs for delta mode
        known_urls = set()
        if delta:
            known_urls = set(db.get_known_urls())
            print(f"Delta mode: {len(known_urls)} known URLs")

        await events.progress("Crawling site...")
        crawl_targets = build_crawl_targets(validated_url, seed_urls or [], max_pages)
        results = [await crawler.extract(url) for url in crawl_targets]

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
            "discovered": len(crawl_targets),
            "errors": error_count,
        })

    except Exception as e:
        await events.error(f"Indexing failed: {e}", e)
        emit_json({"success": False, "error": str(e)})
        raise
    finally:
        if db is not None:
            db.close()
        await events.close()


@click.command()
@click.option("--siteId", required=True, type=int, help="Site ID")
@click.option("--siteUrl", required=True, help="Site URL to index")
@click.option("--maxPages", default=100, type=int, help="Maximum pages to index")
@click.option("--delta", is_flag=True, help="Only index new/changed pages")
@click.option("--seedUrls", default=None, help="JSON array of seed URLs to index")
@click.option("--storagePath", default=None, help="Custom storage directory for SQLite indexes")
def main(siteid: int, siteurl: str, maxpages: int, delta: bool, seedurls: str | None, storagepath: str | None):
    """Site indexer - crawls and indexes a website."""
    asyncio.run(
        run(
            siteid,
            siteurl,
            maxpages,
            delta,
            parse_seed_urls(seedurls),
            storagepath,
        )
    )


if __name__ == "__main__":
    main()
