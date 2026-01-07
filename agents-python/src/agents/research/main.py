"""Research agent - discovers and analyzes sources for a keyword."""
import asyncio
import click
from ..shared.crawler import ContentCrawler
from ..shared.llm import LLMClient
from ..shared.events import EventEmitter
from ..shared.output import emit_json

AGENT_TYPE = "research"


async def generate_search_queries(llm: LLMClient, keyword: str) -> list[str]:
    """Generate varied search queries for the keyword."""
    result = await llm.generate_json(f'''
        Génère 5-6 requêtes de recherche Google variées pour le keyword "{keyword}".
        Les requêtes doivent couvrir:
        - La requête principale
        - Des variations avec "best", "top", "guide"
        - Des questions (how to, what is)
        - Des comparaisons si pertinent

        Retourne un JSON: {{ "queries": ["query1", "query2", ...] }}
    ''')
    return result.get("queries", [keyword])


async def analyze_content(llm: LLMClient, keyword: str, sources: list[dict]) -> dict:
    """Analyze and synthesize the scraped content."""
    sources_text = "\n\n---\n\n".join([
        f"[{s['title']}]\n{s['markdown'][:2000]}"
        for s in sources if s.get("markdown")
    ])

    result = await llm.generate_json(f'''
        Analyse ces sources sur le sujet "{keyword}" et extrait:

        1. topics: Les sous-sujets principaux couverts (liste de strings)
        2. entities: Les entités importantes (outils, marques, personnes) mentionnées
        3. facts: Les faits/statistiques citables avec leur source
        4. angles: 2-3 angles d'article suggérés pour se différencier
        5. summary: Un résumé de 2-3 phrases de ce que les sources couvrent

        Sources:
        {sources_text}

        Retourne un JSON avec ces 5 clés.
    ''', model="google/gemini-2.0-flash-exp")

    return {
        "topics": result.get("topics", []),
        "entities": result.get("entities", []),
        "facts": result.get("facts", []),
        "angles": result.get("angles", []),
        "summary": result.get("summary", ""),
    }


async def search_google(query: str) -> list[str]:
    """Search Google and return URLs.

    TODO: Implement with SearXNG or Google Custom Search API.
    For now, returns empty list (placeholder).
    """
    return []


async def run(article_id: int, keyword: str, site_id: int | None = None):
    """Main research agent logic."""
    events = EventEmitter(article_id, AGENT_TYPE)
    llm = LLMClient()
    crawler = ContentCrawler()

    try:
        await events.started(
            f'Démarrage de la recherche pour "{keyword}"',
            f'Le keyword "{keyword}" sera analysé.'
        )

        # Step 1: Generate search queries
        await events.progress("Génération des requêtes de recherche...")
        queries = await generate_search_queries(llm, keyword)
        await events.progress(f"{len(queries)} requêtes préparées")

        # Step 2: Search and collect URLs
        all_urls = []
        for i, query in enumerate(queries):
            await events.progress(
                f'Recherche: "{query}"',
                progress_current=i + 1,
                progress_total=len(queries),
            )
            urls = await search_google(query)
            all_urls.extend(urls)

        unique_urls = list(set(all_urls))
        await events.progress(f"{len(unique_urls)} URLs collectées")

        if not unique_urls:
            await events.completed("Aucune URL trouvée (search not implemented)")
            emit_json({
                "success": True,
                "sources": [],
                "key_topics": [],
                "entities": [],
                "facts": [],
                "suggested_angles": [],
                "competitor_urls": [],
            })
            return

        # Step 3: Extract content
        await events.progress("Extraction du contenu...")
        scraped = await crawler.extract_many(unique_urls)
        valid = [s for s in scraped if s["success"] and len(s.get("markdown", "")) > 200]

        if not valid:
            await events.completed("Aucune source exploitable")
            emit_json({
                "success": True,
                "sources": [],
                "key_topics": [],
                "entities": [],
                "facts": [],
                "suggested_angles": [],
                "competitor_urls": unique_urls[:10],
            })
            return

        await events.progress(f"{len(valid)} pages exploitables")

        # Step 4: Analyze
        await events.progress("Analyse et synthèse...")
        analysis = await analyze_content(llm, keyword, valid)

        await events.completed(
            f"Recherche terminée: {len(analysis['entities'])} entités, {len(analysis['facts'])} faits",
            reasoning=analysis["summary"],
            metadata={
                "sources_count": len(valid),
                "entities_count": len(analysis["entities"]),
                "facts_count": len(analysis["facts"]),
            },
        )

        emit_json({
            "success": True,
            "sources": [
                {"url": s["url"], "title": s["title"], "snippet": s["markdown"][:500]}
                for s in valid
            ],
            "key_topics": analysis["topics"],
            "entities": analysis["entities"],
            "facts": analysis["facts"],
            "suggested_angles": analysis["angles"],
            "competitor_urls": unique_urls[:10],
        })

    except Exception as e:
        await events.error(f"Erreur: {e}", e)
        emit_json({"success": False, "error": str(e)})
        raise
    finally:
        await events.close()


@click.command()
@click.option("--articleId", required=True, type=int, help="Article ID")
@click.option("--keyword", required=True, help="Target keyword")
@click.option("--siteId", type=int, help="Site ID")
def main(articleid: int, keyword: str, siteid: int | None):
    """Research agent - discovers sources for a keyword."""
    asyncio.run(run(articleid, keyword, siteid))


if __name__ == "__main__":
    main()
