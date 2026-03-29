"""Competitor agent - analyzes SERP competitors."""
import asyncio
import json
import click
from ..shared.crawler import ContentCrawler
from ..shared.llm import LLMClient
from ..shared.events import EventEmitter
from ..shared.output import emit_json

AGENT_TYPE = "competitor"

async def run(article_id: int, keyword: str, urls: list[str]):
    """Analyze competitor content."""
    events = EventEmitter(article_id, AGENT_TYPE)
    crawler = ContentCrawler()
    llm = LLMClient()

    try:
        await events.started(f'Analyzing {len(urls)} competitors for "{keyword}"')

        await events.progress("Extracting competitor content...")
        results = await crawler.extract_many(urls)
        valid = [r for r in results if r["success"]]

        if not valid:
            await events.completed("No valid competitor content found")
            emit_json({"success": True, "analysis": {}})
            return

        await events.progress("Analyzing content structure...")

        competitors_text = "\n\n---\n\n".join([
            f"URL: {c['url']}\nTitle: {c['title']}\n\n{c['markdown'][:3000]}"
            for c in valid
        ])

        analysis = await llm.generate_json(f'''
            Analyse ces articles concurrents pour le keyword "{keyword}":

            {competitors_text}

            Retourne un JSON avec:
            1. avg_word_count: Nombre moyen de mots
            2. common_headings: Les H2/H3 les plus fréquents
            3. topics_covered: Sujets couverts par tous
            4. gaps: Sujets manquants ou mal couverts
            5. recommendations: 3 recommandations pour se différencier
        ''', model="google/gemini-2.5-flash")

        await events.completed(f"Analysis complete: {len(valid)} competitors analyzed")

        emit_json({
            "success": True,
            "competitors_analyzed": len(valid),
            "analysis": analysis,
        })

    except Exception as e:
        await events.error(str(e), e)
        emit_json({"success": False, "error": str(e)})
        raise
    finally:
        await events.close()

@click.command()
@click.option("--articleId", required=True, type=int)
@click.option("--keyword", required=True)
@click.option("--urls", required=True, help="JSON array of URLs")
def main(articleid: int, keyword: str, urls: str):
    """Competitor agent - analyzes SERP competitors."""
    url_list = json.loads(urls)
    asyncio.run(run(articleid, keyword, url_list))

if __name__ == "__main__":
    main()
