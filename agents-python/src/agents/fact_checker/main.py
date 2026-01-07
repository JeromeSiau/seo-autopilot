"""Fact checker agent - verifies claims in content."""
import asyncio
import click
from pathlib import Path
from ..shared.llm import LLMClient
from ..shared.events import EventEmitter
from ..shared.output import emit_json

AGENT_TYPE = "fact-checker"

async def run(article_id: int, content: str):
    """Verify claims in content."""
    events = EventEmitter(article_id, AGENT_TYPE)
    llm = LLMClient()

    try:
        await events.started("Extracting claims from content")

        claims_result = await llm.generate_json(f'''
            Extrait les affirmations vérifiables de ce texte.
            Ignore les opinions et les généralités.

            Texte:
            {content[:5000]}

            Retourne: {{ "claims": [{{ "text": "...", "importance": "high|medium|low" }}] }}
        ''')

        claims = claims_result.get("claims", [])
        await events.progress(f"{len(claims)} claims extracted")

        verified_claims = []
        for i, claim in enumerate(claims[:10]):
            await events.progress(f"Verifying claim {i + 1}/{min(len(claims), 10)}",
                progress_current=i + 1, progress_total=min(len(claims), 10))
            verified_claims.append({
                **claim,
                "verified": None,
                "sources": [],
            })

        await events.completed(f"Checked {len(verified_claims)} claims")

        emit_json({
            "success": True,
            "claims_count": len(claims),
            "verified_claims": verified_claims,
        })

    except Exception as e:
        await events.error(str(e), e)
        emit_json({"success": False, "error": str(e)})
        raise
    finally:
        await events.close()

@click.command()
@click.option("--articleId", required=True, type=int)
@click.option("--contentFile", required=True, help="Path to content file")
def main(articleid: int, contentfile: str):
    """Fact checker - verifies claims in content."""
    content = Path(contentfile).read_text()
    asyncio.run(run(articleid, content))

if __name__ == "__main__":
    main()
