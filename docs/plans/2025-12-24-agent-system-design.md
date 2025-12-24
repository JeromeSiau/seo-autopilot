# Agent System Design — SEO Autopilot

> Date: 2025-12-24
> Inspiré par: [SEObot AI Agent](https://seobotai.com/seo-ai-agent/)

## Objectif

Transformer SEO Autopilot en un système d'agents IA transparents qui :
- Montrent leur raisonnement en temps réel
- Font de la vraie recherche web (pas juste du LLM)
- Vérifient les faits et citent leurs sources
- Automatisent le linking interne

---

## 1. Architecture des Agents

Chaque agent est un **script Node.js indépendant** dans le dossier `/agents` :

```
/agents
  /research-agent
    index.js          # Point d'entrée
    google-search.js  # Recherche Google via scraping
    content-scraper.js # Extraction du contenu des pages
    package.json
  /competitor-agent
    index.js
    serp-analyzer.js  # Analyse des résultats SERP
    structure-extractor.js # Extraction H2/H3/word count
  /fact-checker-agent
    index.js
    claim-extractor.js # Identifie les affirmations à vérifier
    verifier.js       # Vérifie via recherche web
  /internal-linking-agent
    index.js
    site-scanner.js   # Indexe les pages existantes
    link-suggester.js # Trouve les opportunités de liens
  /shared
    event-emitter.js  # Écrit les events dans Redis
    puppeteer-setup.js # Config Crawlee/Puppeteer partagée
```

### Stack technique

| Composant | Technologie |
|-----------|-------------|
| Scraping | Crawlee + Puppeteer (avec stealth plugin) |
| Communication | CLI async + Redis pub/sub |
| Broadcasting | Laravel Reverb (WebSocket) |
| Stockage events | Table `agent_events` |

---

## 2. Système d'Events

### Table `agent_events`

```sql
CREATE TABLE agent_events (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    article_id BIGINT NOT NULL,          -- Lié à l'article en cours
    agent_type VARCHAR(50) NOT NULL,     -- 'research', 'competitor', 'fact_checker', 'internal_linking'
    event_type VARCHAR(50) NOT NULL,     -- 'started', 'progress', 'completed', 'error'
    message TEXT NOT NULL,               -- Message affiché à l'utilisateur
    reasoning TEXT NULL,                 -- Explication du "pourquoi" (optionnel)
    metadata JSON NULL,                  -- Données structurées (sources trouvées, stats, etc.)
    progress_current INT NULL,           -- Pour les progress bars (ex: 5)
    progress_total INT NULL,             -- (ex: 23)
    created_at TIMESTAMP DEFAULT NOW(),

    INDEX idx_article_created (article_id, created_at)
);
```

### Exemples d'events

| agent_type | event_type | message | reasoning |
|------------|------------|---------|-----------|
| research | started | "Démarrage de la recherche..." | "Le keyword 'seo tools' suggère un article comparatif" |
| research | progress | "Recherche Google en cours..." | null |
| research | progress | "23 sources collectées" | "J'ai filtré les résultats non pertinents" |
| competitor | completed | "Moyenne 2,450 mots" | "Les 3 premiers font 2800+, je recommande 3000" |

---

## 3. Communication Laravel ↔ Node.js ↔ Frontend

### Flow complet

```
┌─────────────────────────────────────────────────────────────┐
│  Laravel Job (GenerateArticleJob)                           │
│    ↓                                                        │
│  Lance: node research-agent.js --articleId=X --keyword="Y"  │
│    ↓                                                        │
│  Node.js écrit dans Redis:                                  │
│    → { taskId, event: "searching", query: "..." }           │
│    → { taskId, event: "found_sources", count: 23 }          │
│    ↓                                                        │
│  Laravel écoute Redis, sauvegarde en DB, broadcast Reverb   │
│    ↓                                                        │
│  React reçoit via Echo et affiche en temps réel             │
└─────────────────────────────────────────────────────────────┘
```

### Laravel lance l'agent

```php
// GenerateArticleJob.php
Process::path(base_path('agents/research-agent'))
    ->start("node index.js --articleId={$id} --keyword=\"{$keyword}\"");
```

### Node.js émet des events

```javascript
// agents/shared/event-emitter.js
async function emitEvent(articleId, agentType, eventType, message, options = {}) {
    const event = {
        article_id: articleId,
        agent_type: agentType,
        event_type: eventType,
        message: message,
        reasoning: options.reasoning || null,
        metadata: options.metadata || null,
        progress_current: options.progressCurrent || null,
        progress_total: options.progressTotal || null,
        timestamp: Date.now()
    };

    await redis.publish(`agent-events.${articleId}`, JSON.stringify(event));
    await redis.rpush(`agent-events-log:${articleId}`, JSON.stringify(event));
}
```

### Laravel broadcast vers frontend

```php
// app/Listeners/AgentEventSubscriber.php
Redis::subscribe(['agent-events.*'], function ($message, $channel) {
    $event = json_decode($message);
    AgentEvent::create((array) $event);
    broadcast(new AgentActivityEvent($event));
});
```

### React reçoit en temps réel

```typescript
useEffect(() => {
    const channel = Echo.private(`article.${articleId}`);
    channel.listen('AgentActivityEvent', (event) => {
        setEvents(prev => [...prev, event]);
    });
    return () => channel.stopListening('AgentActivityEvent');
}, [articleId]);
```

---

## 4. Research Agent

### Responsabilités
1. Rechercher sur Google les requêtes pertinentes au keyword
2. Scraper le contenu des top résultats
3. Extraire et structurer les informations utiles

### Flow

```
INPUT: keyword="keyword research tools"

STEP 1: Générer les requêtes de recherche
  → LLM génère 5-8 requêtes variées
  → Event: "Je prépare 6 requêtes de recherche..."

STEP 2: Scraper Google pour chaque requête
  → Crawlee scrape les 10 premiers résultats par requête
  → Event: "Recherche Google : 'best keyword research...'"
  → Event: "42 URLs collectées (dédupliquées: 28)"

STEP 3: Scraper le contenu des pages
  → Pour chaque URL, extrait: titre, contenu texte, H2/H3
  → Event: "Extraction du contenu (5/28)..."

STEP 4: Synthétiser les données
  → LLM analyse et structure les informations clés
  → Event: "J'ai identifié 12 outils mentionnés fréquemment"

OUTPUT: research_data.json
  {
    sources: [...],
    key_topics: [...],
    entities: [...],
    facts: [...],
    suggested_angles: [...]
  }
```

---

## 5. Competitor Analyzer Agent

### Responsabilités
1. Analyser les 10 premiers résultats SERP
2. Extraire word count, structure H2/H3, topics couverts
3. Générer des recommandations pour battre la concurrence

### Flow

```
INPUT: keyword + top 10 URLs from Research Agent

STEP 1: Analyser chaque page concurrente
  → Word count, structure H1/H2/H3, médias
  → Event: "Analyse de example.com (3/10)..."

STEP 2: Extraire les topics couverts
  → LLM identifie les sous-sujets dans chaque H2
  → Event: "Topics: 'free tools' (8/10), 'pricing' (7/10)"

STEP 3: Identifier les content gaps
  → Topics peu couverts = opportunités
  → Event: "Gap: seulement 2/10 parlent de 'local SEO'"

STEP 4: Générer les recommandations
  → Event reasoning: "Les 3 premiers font 2800+ mots.
     Je recommande 3200 mots avec 'local SEO' comme angle"

OUTPUT: competitor_analysis.json
  {
    competitors: [...],
    avg_word_count: 2450,
    top3_avg_word_count: 2834,
    recommended_word_count: 3200,
    common_topics: [...],
    content_gaps: [...],
    recommended_headings: [...]
  }
```

---

## 6. Fact Checker Agent

### Responsabilités
1. Identifier les affirmations factuelles dans l'article
2. Vérifier chaque claim via recherche web
3. Ajouter des citations ou corriger les erreurs

### Flow

```
INPUT: article_content (généré par Writing Agent)

STEP 1: Extraire les claims vérifiables
  → Statistiques, dates, comparaisons, faits techniques
  → Event: "12 affirmations à vérifier identifiées"

STEP 2: Vérifier chaque claim
  → Recherche Google ciblée, compare avec sources fiables
  → Event: "Vérification (4/12): '73% des marketers...'
            → ✅ Confirmé (source: HubSpot 2024)"
  → Event: "Vérification (7/12): 'lancé en 2019'
            → ❌ Incorrect, c'était 2020"

STEP 3: Catégoriser les résultats
  → ✅ Confirmé | ⚠️ Partiellement vrai | ❌ Incorrect | ❓ Non vérifiable
  → Event: "Résultat: 8 ✅, 2 ⚠️, 1 ❌, 1 ❓"

STEP 4: Générer les corrections et citations
  → Event reasoning: "1 erreur factuelle trouvée, correction proposée"

OUTPUT: fact_check_report.json
  {
    total_claims: 12,
    verified: 8,
    partially_true: 2,
    incorrect: 1,
    unverifiable: 1,
    claims: [...],
    citations_to_add: [...]
  }
```

---

## 7. Internal Linking Agent

### Responsabilités
1. Indexer toutes les pages existantes du site
2. Analyser l'article pour trouver des opportunités de liens
3. Insérer automatiquement les liens internes pertinents

### Flow

```
INPUT: article_content + site_id

STEP 1: Charger l'index du site
  → Récupère les pages existantes (table site_pages)
  → Event: "Index chargé: 47 pages existantes"

STEP 2: Analyser le contenu de l'article
  → LLM extrait les termes/concepts linkables
  → Event: "18 termes potentiellement linkables identifiés"

STEP 3: Matcher termes ↔ pages existantes
  → Score de pertinence sémantique
  → Event: "8 opportunités de liens trouvées"
  → Event reasoning: "'keyword research' peut lier vers
     votre guide existant. Je priorise les pages orphelines."

STEP 4: Sélectionner et insérer les liens
  Règles:
  → Max 1 lien par 300 mots
  → Pas de lien dans l'intro (premiers 150 mots)
  → Privilégie les pages orphelines
  → Anchor text naturel
  → Event: "5 liens insérés (3 ignorés: densité trop haute)"

OUTPUT: linked_article + linking_report.json
  {
    links_added: [...],
    links_skipped: [...],
    site_linking_health: {
      orphan_pages: 5,
      over_linked_pages: 2,
      avg_internal_links: 3.2
    }
  }
```

---

## 8. UI — Drawer d'Activité

### Composants React

```
/resources/js/Components/AgentActivity/
  ActivityDrawer.tsx      # Drawer principal (on-demand)
  ActivityFeed.tsx        # Liste des events en temps réel
  ActivityItem.tsx        # Un event individuel
  AgentBadge.tsx          # Badge coloré par type d'agent
  ProgressIndicator.tsx   # Barre de progression
  ArticleTimeline.tsx     # Timeline complète d'un article
```

### Design du Drawer

```
┌─────────────────────────────────────────────────────────────┐
│  ● Activité des Agents                              [X]     │
├─────────────────────────────────────────────────────────────┤
│  🔍 RESEARCH        "keyword research tools"                │
│  ├─ 14:32:01  Démarrage de la recherche...                  │
│  ├─ 14:32:03  Je prépare 6 requêtes de recherche            │
│  │            └─ "Le keyword suggère un article comparatif" │
│  ├─ 14:32:15  Recherche Google: "best keyword tools 2025"   │
│  ├─ 14:32:28  42 URLs collectées (dédupliquées: 28)         │
│  └─ 14:34:02  ✅ Terminé — 12 outils identifiés             │
│                                                             │
│  📊 COMPETITOR       en cours...                            │
│  ├─ 14:34:05  Analyse des concurrents (3/10)                │
│  │            ████████░░░░░░░░░░░░ 30%                      │
│  └─ 14:34:12  example.com: 2,847 mots, 8 H2                 │
│                                                             │
│  ⏳ FACT CHECKER     en attente                             │
│  ⏳ INTERNAL LINKING en attente                             │
├─────────────────────────────────────────────────────────────┤
│  [Voir la timeline complète]                                │
└─────────────────────────────────────────────────────────────┘
```

### Code couleur des agents

| Agent | Couleur | Icône |
|-------|---------|-------|
| Research | Bleu | 🔍 |
| Competitor | Violet | 📊 |
| Fact Checker | Orange | ✓ |
| Internal Linking | Vert | 🔗 |
| Writing | Indigo | ✍️ |
| Error | Rouge | ⚠️ |

---

## 9. Pipeline de Génération Révisé

### Nouveau flow avec les 4 agents

```
┌─────────────────────────────────────────────────────────────────┐
│                    ARTICLE GENERATION PIPELINE                  │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  ┌─────────────┐      ┌──────────────────┐                      │
│  │  RESEARCH   │ ───► │   COMPETITOR     │                      │
│  │   AGENT     │      │     AGENT        │                      │
│  └─────────────┘      └──────────────────┘                      │
│         │                      │                                │
│         ▼                      ▼                                │
│  research_data.json    competitor_analysis.json                 │
│         │                      │                                │
│         └──────────┬───────────┘                                │
│                    ▼                                            │
│           ┌───────────────┐                                     │
│           │  OUTLINE LLM  │  (enrichi avec données réelles)     │
│           └───────────────┘                                     │
│                    │                                            │
│                    ▼                                            │
│           ┌───────────────┐                                     │
│           │  WRITING LLM  │  (avec sources à citer)             │
│           └───────────────┘                                     │
│                    │                                            │
│                    ▼                                            │
│           ┌───────────────┐                                     │
│           │ FACT CHECKER  │                                     │
│           │    AGENT      │                                     │
│           └───────────────┘                                     │
│                    │                                            │
│                    ▼                                            │
│           ┌───────────────┐                                     │
│           │  POLISH LLM   │  (applique corrections + citations) │
│           └───────────────┘                                     │
│                    │                                            │
│                    ▼                                            │
│         ┌───────────────────┐                                   │
│         │ INTERNAL LINKING  │                                   │
│         │      AGENT        │                                   │
│         └───────────────────┘                                   │
│                    │                                            │
│                    ▼                                            │
│             📄 ARTICLE FINAL                                    │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
```

### Orchestration dans le Job Laravel

```php
// GenerateArticleJob.php
public function handle()
{
    // Phase 1: Agents de recherche (parallélisables)
    $researchPromise = $this->runAgent('research', $this->article);
    $competitorPromise = $this->runAgent('competitor', $this->article);

    [$researchData, $competitorData] = await_all([$researchPromise, $competitorPromise]);

    // Phase 2: Génération du contenu
    $outline = $this->llm->generateOutline($researchData, $competitorData);
    $content = $this->llm->writeArticle($outline, $researchData);

    // Phase 3: Vérification et polish
    $factCheckReport = $this->runAgent('fact-checker', $content);
    $polishedContent = $this->llm->polish($content, $factCheckReport);

    // Phase 4: Internal linking
    $finalContent = $this->runAgent('internal-linking', $polishedContent);

    // Sauvegarde
    $this->article->update(['content' => $finalContent, 'status' => 'ready']);
}
```

---

## 10. Fichiers à Créer/Modifier

### Nouveaux fichiers

```
agents/                              # Nouveau dossier Node.js
├── package.json
├── shared/
│   ├── event-emitter.js
│   ├── puppeteer-setup.js
│   └── llm-client.js
├── research-agent/
│   ├── index.js
│   ├── google-search.js
│   └── content-scraper.js
├── competitor-agent/
│   ├── index.js
│   ├── serp-analyzer.js
│   └── structure-extractor.js
├── fact-checker-agent/
│   ├── index.js
│   ├── claim-extractor.js
│   └── verifier.js
└── internal-linking-agent/
    ├── index.js
    ├── site-scanner.js
    └── link-suggester.js

app/Models/AgentEvent.php
app/Events/AgentActivityEvent.php
app/Listeners/AgentEventSubscriber.php
app/Services/Agent/AgentRunner.php

database/migrations/xxx_create_agent_events_table.php

resources/js/Components/AgentActivity/
├── ActivityDrawer.tsx
├── ActivityFeed.tsx
├── ActivityItem.tsx
├── AgentBadge.tsx
├── ProgressIndicator.tsx
└── ArticleTimeline.tsx

resources/js/hooks/useAgentActivity.ts
```

### Fichiers à modifier

```
app/Jobs/GenerateArticleJob.php      # Nouveau pipeline avec agents
app/Services/ArticleGenerator.php    # Intégration des données agents
resources/js/Layouts/AuthenticatedLayout.tsx  # Bouton activity drawer
config/broadcasting.php              # Configuration Reverb
```

---

## 11. Estimation de Complexité

| Phase | Composants | Effort |
|-------|------------|--------|
| Infrastructure | Events table, Reverb setup, Redis pub/sub | Moyen |
| Research Agent | Google scraping, content extraction, LLM synthesis | Important |
| Competitor Agent | SERP analysis, structure extraction | Moyen |
| Fact Checker Agent | Claim extraction, verification, citations | Important |
| Internal Linking Agent | Site indexing, semantic matching | Moyen |
| UI Components | Drawer, Feed, Timeline | Moyen |
| Pipeline Integration | Job orchestration, error handling | Moyen |

---

## Références

- [SEObot AI Agent](https://seobotai.com/seo-ai-agent/) — Inspiration principale
- [Crawlee Documentation](https://crawlee.dev/js/docs/3.11/examples/crawler-plugins) — Puppeteer stealth setup
- [Laravel Reverb](https://laravel.com/docs/reverb) — WebSocket broadcasting
