"""Internal linking agent - suggests internal links."""
import asyncio
import struct
import sqlite3
import click
from pathlib import Path
from ..shared.embeddings import VoyageEmbedder
from ..shared.llm import LLMClient
from ..shared.events import EventEmitter
from ..shared.output import emit_json

AGENT_TYPE = "internal-linking"

def cosine_similarity(a: list[float], b: list[float]) -> float:
    dot = sum(x * y for x, y in zip(a, b))
    norm_a = sum(x * x for x in a) ** 0.5
    norm_b = sum(x * x for x in b) ** 0.5
    return dot / (norm_a * norm_b) if norm_a and norm_b else 0

async def run(article_id: int, site_id: int, content: str):
    events = EventEmitter(article_id, AGENT_TYPE)
    embedder = VoyageEmbedder()
    llm = LLMClient()

    try:
        await events.started("Finding relevant internal pages")

        index_path = Path(__file__).parent.parent.parent.parent.parent / "storage" / "indexes" / f"site_{site_id}.sqlite"

        if not index_path.exists():
            await events.completed("No site index found")
            emit_json({"success": True, "links": []})
            return

        conn = sqlite3.connect(str(index_path))
        conn.row_factory = sqlite3.Row

        try:
            metadata = {
                row["key"]: row["value"]
                for row in conn.execute("SELECT key, value FROM metadata")
            }
        except sqlite3.OperationalError:
            metadata = {}

        if metadata.get("embedding_model") != embedder.model_name:
            conn.close()
            await events.completed("Site index embeddings are outdated; reindex required")
            emit_json({
                "success": True,
                "links": [],
                "reason": "site_index_reindex_required",
                "expected_embedding_model": embedder.model_name,
                "current_embedding_model": metadata.get("embedding_model"),
            })
            return

        await events.progress("Generating content embedding...")
        content_embedding = await embedder.embed(content[:8000], "query")

        await events.progress("Finding similar pages...")
        cursor = conn.execute('''
            SELECT p.id, p.url, p.title, e.embedding
            FROM pages p JOIN embeddings e ON e.page_id = p.id
        ''')

        similarities = []
        for row in cursor:
            embedding_blob = row["embedding"]
            num_floats = len(embedding_blob) // 4
            page_embedding = list(struct.unpack(f'{num_floats}f', embedding_blob))
            similarity = cosine_similarity(content_embedding, page_embedding)
            similarities.append({"url": row["url"], "title": row["title"], "similarity": similarity})

        conn.close()

        top_pages = sorted(similarities, key=lambda x: x["similarity"], reverse=True)[:20]

        await events.progress("Generating link suggestions...")

        pages_text = "\n".join([f"- {p['title']} ({p['url']}) - similarity: {p['similarity']:.2f}" for p in top_pages])

        suggestions = await llm.generate_json(f'''
            Voici un article et des pages internes similaires.
            Suggère où placer des liens internes de manière naturelle.

            Article (extrait):
            {content[:2000]}

            Pages disponibles:
            {pages_text}

            Retourne: {{ "suggestions": [{{ "anchor_text": "...", "target_url": "...", "context": "..." }}] }}
        ''', model="anthropic/claude-haiku-4.5")

        await events.completed(f"Found {len(suggestions.get('suggestions', []))} link opportunities")

        emit_json({
            "success": True,
            "similar_pages": top_pages[:10],
            "suggestions": suggestions.get("suggestions", []),
        })

    except Exception as e:
        await events.error(str(e), e)
        emit_json({"success": False, "error": str(e)})
        raise
    finally:
        await events.close()

@click.command()
@click.option("--articleId", required=True, type=int)
@click.option("--siteId", required=True, type=int)
@click.option("--contentFile", required=True)
def main(articleid: int, siteid: int, contentfile: str):
    content = Path(contentfile).read_text()
    asyncio.run(run(articleid, siteid, content))

if __name__ == "__main__":
    main()
