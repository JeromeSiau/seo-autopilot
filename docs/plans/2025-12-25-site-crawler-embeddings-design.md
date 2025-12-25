# Site Crawler avec Embeddings pour le Linking Agent

## Objectif

Permettre au linking agent de connaître tout le contenu existant d'un site, pas seulement les articles créés par SEO Autopilot. Résout le problème du "cold start" où le linking agent n'a rien à lier au départ.

## Décisions clés

| Aspect | Choix |
|--------|-------|
| Déclencheur | Onboarding + cron hebdomadaire |
| Découverte pages | Sitemap + navigation (crawler complet) |
| Extraction contenu | Readability (Mozilla algorithm) |
| Détection catégories | Parse URL/HTML + LLM fallback |
| Embeddings modèle | Voyage 3.5-lite |
| Stockage embeddings | SQLite + sqlite-vec (1 fichier par site) |
| Cron fréquence | Hebdomadaire, chunked avec délais |

## Architecture

### Nouveaux composants

```
app/Services/Crawler/
├── SiteCrawlerService.php      # Existant - on l'étend
├── ContentExtractorService.php # NEW - Readability + metadata
├── CategoryDetectorService.php # NEW - Parse + LLM fallback
└── SiteIndexService.php        # NEW - Gère l'index SQLite + embeddings

app/Services/LLM/Providers/
└── VoyageProvider.php          # NEW - API embeddings Voyage

agents/site-indexer/
├── index.js                    # Point d'entrée du crawler Node
├── navigator.js                # Crawl par navigation (Playwright)
├── content-extractor.js        # Readability + parsing HTML
└── embedder.js                 # Appel Voyage API

storage/indexes/
└── site_{id}.sqlite            # Un fichier par site
```

### Schéma SQLite par site

```sql
CREATE TABLE pages (
    id INTEGER PRIMARY KEY,
    url TEXT UNIQUE,
    title TEXT,
    h1 TEXT,
    meta_description TEXT,
    content_text TEXT,           -- Contenu extrait (Readability)
    category TEXT,               -- Catégorie détectée
    tags TEXT,                   -- JSON array
    inbound_links_count INTEGER, -- Nombre de liens entrants internes
    outbound_links TEXT,         -- JSON array des URLs linkées
    source TEXT,                 -- 'sitemap', 'navigation', 'gsc'
    crawled_at DATETIME,
    embedding BLOB               -- Vecteur sqlite-vec
);

CREATE INDEX idx_category ON pages(category);
CREATE VIRTUAL TABLE pages_vec USING vec0(embedding float[1024]);
```

## Flux de crawl

### Onboarding (crawl complet)

```
┌─────────────────────────────────────────────────────────────────┐
│  1. User connecte son site                                      │
│  2. Crawl sitemap → découvre toutes les URLs                    │
│  3. Navigation Playwright → découvre pages non-sitemap          │
│  4. Pour chaque page :                                          │
│     - Fetch HTML                                                │
│     - Readability → extrait contenu                             │
│     - Parse catégories/tags (URL + HTML)                        │
│     - Si rien trouvé → LLM classification                       │
│     - Voyage 3.5-lite → génère embedding                        │
│  5. Stocke tout dans SQLite                                     │
│  6. Calcule inbound_links_count pour chaque page                │
└─────────────────────────────────────────────────────────────────┘
```

### Cron hebdomadaire (crawl delta)

```
┌─────────────────────────────────────────────────────────────────┐
│  1. Re-fetch sitemap                                            │
│  2. Compare avec URLs connues                                   │
│  3. Nouvelles URLs → crawl complet                              │
│  4. URLs existantes → skip (sauf si lastmod changé)             │
│  5. Navigation légère → découvre nouvelles pages                │
│  6. Met à jour l'index                                          │
└─────────────────────────────────────────────────────────────────┘
```

### Dispatch chunked pour éviter surcharge

```php
// WeeklySiteCrawlJob.php
Site::active()->chunk(50, function ($sites) use (&$delay) {
    foreach ($sites as $site) {
        SiteIndexJob::dispatch($site)
            ->onQueue('crawl')
            ->delay(now()->addMinutes($delay));
    }
    $delay += 30; // 30 min entre chaque batch de 50
});
```

## Intégration linking agent

### Modification de site-scanner.js

```javascript
// Avant : seulement les articles créés par SEO Autopilot
const pages = await db.query('SELECT * FROM articles WHERE site_id = ?');

// Après : fusion articles + pages crawlées
const pages = await loadFromSQLiteIndex(siteId);  // Pages crawlées
const articles = await loadPublishedArticles(siteId);  // Articles SEO Autopilot
const allPages = mergeAndDedupe(pages, articles);  // Fusion sans doublons
```

### Nouveau flow du linking agent

```
1. Reçoit le contenu du nouvel article
2. Génère l'embedding du contenu (Voyage 3.5-lite)
3. Recherche vectorielle dans SQLite :
   - Top 20 pages les plus similaires sémantiquement
4. Filtre :
   - Exclut les pages déjà très linkées (> 10 inbound)
   - Priorise les orphelines
5. LLM sélectionne les anchor texts naturels
6. Insère les liens
```

## Intégration Voyage API

```php
// app/Services/LLM/Providers/VoyageProvider.php

class VoyageProvider
{
    public function embed(string $text): array
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . config('services.voyage.key'),
        ])->post('https://api.voyageai.com/v1/embeddings', [
            'model' => 'voyage-3.5-lite',
            'input' => [$text],
            'input_type' => 'document',
        ]);

        return $response->json('data.0.embedding');
    }

    public function embedBatch(array $texts): array
    {
        // Voyage supporte jusqu'à 128 textes par requête
    }
}
```

## Limites et sécurité

- **500 pages max** par site (configurable)
- **Timeout 30s** par page
- **Rate limit 2 req/sec** pour pas surcharger le site cible
- **Respecte robots.txt**

## Coûts estimés

- Voyage 3.5-lite : ~$0.05 / 1M tokens
- Site de 500 pages (~500 mots/page) : ~$0.01 par crawl complet
- Négligeable

## Monitoring

- Log le nombre de pages découvertes/màj
- Alerte si crawl échoue 2 semaines de suite
- Dashboard : "Dernière synchro : il y a X jours"

## Stockage

```
storage/indexes/
├── site_1.sqlite    (~5 MB pour 500 pages)
├── site_2.sqlite
└── ...

# Suppression site → delete le fichier
# Backup : les .sqlite sont inclus dans le backup standard
```
