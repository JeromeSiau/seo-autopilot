# Migration des Agents Node.js vers Python (Crawl4AI)

**Date**: 2025-01-07
**Statut**: Approuvé
**Contexte**: Remplacement de la stack Node.js (Crawlee + Puppeteer) par Python (Crawl4AI) pour améliorer la qualité d'extraction de contenu (markdown structuré vs texte brut).

## Motivation

- **Extraction LLM-optimized** : Crawl4AI produit du markdown structuré (tables, listes préservées) vs texte brut actuel
- **Stack homogène** : Un seul langage pour tous les agents (maintenance simplifiée)
- **Meilleure qualité** : Trafilatura/Crawl4AI ont de meilleurs scores F1 que Readability.js
- **Écosystème Python** : Accès à SearXNG, Trafilatura, et autres outils open source

## Architecture

```
agents-python/
├── pyproject.toml
├── uv.lock
├── src/
│   └── agents/
│       ├── __init__.py
│       ├── shared/
│       │   ├── crawler.py      # Wrapper Crawl4AI
│       │   ├── llm.py          # Client OpenRouter
│       │   ├── embeddings.py   # Client Voyage
│       │   └── output.py       # JSON stdout helper
│       ├── research/
│       │   └── main.py
│       ├── competitor/
│       │   └── main.py
│       ├── fact_checker/
│       │   └── main.py
│       ├── internal_linking/
│       │   └── main.py
│       └── site_indexer/
│           └── main.py
```

## Agents

### 1. Research Agent
- Recherche des URLs via SearXNG (optionnel) ou Google
- Extraction markdown via Crawl4AI (`extract_many`)
- Synthèse via Gemini Flash (comme actuellement)

### 2. Competitor Agent
- Reçoit URLs SERP, extrait le contenu concurrent
- Analyse structure, longueur, topics couverts

### 3. Fact Checker Agent
- Extrait les claims du contenu via LLM
- Vérifie chaque claim via recherche web + extraction

### 4. Internal Linking Agent
- Utilise les embeddings Voyage existants
- Recherche similarité dans l'index SQLite du site
- Suggère placements de liens via LLM

### 5. Site Indexer
- Deep crawl via `BFSDeepCrawlStrategy`
- Mode delta (ne ré-indexe que les pages modifiées)
- Génère embeddings Voyage pour chaque page

## Shared Components

### ContentCrawler (Crawl4AI wrapper)
```python
class ContentCrawler:
    async def extract(self, url: str) -> dict:
        """Retourne {url, title, markdown, links, success}"""

    async def extract_many(self, urls: list[str]) -> list[dict]:
        """Extraction batch concurrente"""
```

Configuration:
- `PruningContentFilter` avec threshold 0.4 (supprime boilerplate)
- `fit_markdown` pour output nettoyé
- Timeout 30s par page

### Output JSON
Même format qu'actuellement : dernière ligne stdout = JSON parsé par Laravel.

## Dépendances

```toml
[project]
name = "seo-autopilot-agents"
version = "1.0.0"
requires-python = ">=3.11"

dependencies = [
    "crawl4ai>=0.4.0",
    "httpx>=0.27",
    "openai>=1.0",
    "voyageai>=0.3",
    "sqlite-vec>=0.1",
    "click>=8.0",
]

[project.scripts]
research = "agents.research.main:main"
competitor = "agents.competitor.main:main"
fact-checker = "agents.fact_checker.main:main"
internal-linking = "agents.internal_linking.main:main"
site-indexer = "agents.site_indexer.main:main"
```

## Intégration Laravel

### Modification AgentRunner.php
```php
private function runAgent(string $agentName, array $args): array
{
    $command = [
        'uv', 'run',
        '--project', base_path('agents-python'),
        $agentName,
    ];

    foreach ($args as $key => $value) {
        $command[] = "{$key}={$value}";
    }

    // Reste inchangé...
}
```

`uv run` gère automatiquement le virtual environment.

## Plan de Migration

### Étape 1 : Setup
- [ ] Créer `agents-python/` avec structure
- [ ] Configurer `pyproject.toml`
- [ ] Implémenter `shared/` (crawler, llm, embeddings, output)

### Étape 2 : Agents
- [ ] Research agent
- [ ] Competitor agent
- [ ] Fact checker agent
- [ ] Internal linking agent
- [ ] Site indexer

### Étape 3 : Tests
- [ ] Tests unitaires par agent
- [ ] Tests d'intégration avec Laravel
- [ ] Comparaison qualité output vs anciens agents

### Étape 4 : Switch
- [ ] Modifier `AgentRunner.php` pour utiliser `uv run`
- [ ] Déployer
- [ ] Supprimer `agents/` (Node.js)

## Risques et Mitigations

| Risque | Mitigation |
|--------|------------|
| Crawl4AI moins stable que Crawlee | Tests extensifs avant switch |
| Playwright dependencies sur serveur | Docker ou install script |
| Performance différente | Benchmark avant/après |

## Alternatives Considérées

1. **Améliorer l'existant** (Readability + Turndown) - Rejeté : ne résout pas la fragmentation JS/Python
2. **Stack modulaire** (Playwright + Trafilatura) - Rejeté : plus de glue code
3. **Migration progressive** - Rejeté : complexité de 2 stacks en parallèle
