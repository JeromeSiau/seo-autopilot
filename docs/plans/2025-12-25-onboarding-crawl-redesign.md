# Redesign Onboarding avec Crawl Intelligent

> Date: 2025-12-25

## Objectif

Refondre le flow d'onboarding pour que le crawl du site soit utilisé efficacement dans la génération du content plan :
1. **Éviter les duplicatas** — Ne pas proposer des sujets déjà couverts
2. **Analyser le contenu existant** — Comprendre les thématiques pour générer des idées pertinentes
3. **Préparer le maillage interne** — Indexer avec embeddings pour le linking agent

---

## Décisions clés

| Aspect | Choix |
|--------|-------|
| Déclenchement crawl | Dès Step 1 (création du site) |
| Exécution | Parallèle pendant que l'utilisateur continue l'onboarding |
| Données utilisées | Extraction de topics + Gap analysis GSC |
| Gestion timeout | Mode dégradé intelligent (continue avec données partielles) |
| Stockage | Hybride MySQL (sitemap rapide) + SQLite (crawl profond) |

---

## Nouveau flow d'onboarding

```
Step 1: Domaine + Nom
   ├─→ SiteCrawlerService::crawl() — sitemap → site_pages MySQL (sync, ~10s)
   ├─→ Dispatch SiteIndexJob (queue: crawl) — crawl profond → SQLite (async)
   └─→ crawl_status = 'partial'

Step 2: Connexion GSC (optionnel)
   └─→ Le crawl profond continue en background

Step 3: Business description + topics
   └─→ Le crawl profond continue en background

Step 4: Settings (articles/semaine, jours)
   └─→ Le crawl profond continue en background

Step 5: Intégration WordPress (optionnel)
   └─→ Le crawl profond continue en background

Step 6: Finalisation
   ├─→ Vérifie crawl_status
   ├─→ Si 'completed' → Continue avec toutes les données
   ├─→ Si 'partial' (sitemap OK) → Continue avec données partielles
   ├─→ Si 'running' → Attendre max 2 min, puis continue en partial
   └─→ Si 'failed' → Continue avec sitemap seul + warning
```

---

## Modifications Backend

### 1. Nouveaux champs sur `sites`

```php
// Migration
$table->enum('crawl_status', ['pending', 'running', 'partial', 'completed', 'failed'])
    ->default('pending');
$table->integer('crawl_pages_count')->default(0);
```

### 2. OnboardingController::storeStep1

```php
public function storeStep1(Request $request)
{
    $validated = $request->validate([...]);

    $site = Site::create([
        'team_id' => auth()->user()->team_id,
        'crawl_status' => 'running',
        ...$validated,
    ]);

    // Crawl rapide du sitemap (sync)
    $this->crawler->crawl($site);
    $this->crawler->extractTitlesForPages($site, 50);

    $site->update([
        'crawl_status' => 'partial',
        'crawl_pages_count' => $site->pages()->count(),
    ]);

    // Crawl profond avec embeddings (async)
    SiteIndexJob::dispatch($site, delta: false)->onQueue('crawl');

    return response()->json(['site_id' => $site->id]);
}
```

### 3. SiteIndexJob avec progression temps réel

```php
public function handle(SiteIndexService $indexService): void
{
    $this->site->update(['crawl_status' => 'running']);
    broadcast(new SiteCrawlProgress($this->site, 'running', 0));

    try {
        $result = $indexService->indexSite($this->site, $this->delta,
            onProgress: function($pagesCount) {
                $this->site->update(['crawl_pages_count' => $pagesCount]);
                broadcast(new SiteCrawlProgress($this->site, 'running', $pagesCount));
            }
        );

        $this->site->update([
            'crawl_status' => 'completed',
            'crawl_pages_count' => $result['pages_indexed'],
            'last_crawled_at' => now(),
        ]);
        broadcast(new SiteCrawlProgress($this->site, 'completed', $result['pages_indexed']));

    } catch (\Exception $e) {
        $this->site->update(['crawl_status' => 'failed']);
        broadcast(new SiteCrawlProgress($this->site, 'failed', 0));
        throw $e;
    }
}
```

### 4. Nouveau Event: SiteCrawlProgress

```php
// app/Events/SiteCrawlProgress.php
class SiteCrawlProgress implements ShouldBroadcast
{
    public function __construct(
        public Site $site,
        public string $status,
        public int $pagesCount,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('site.' . $this->site->id)];
    }
}
```

### 5. Nouveau service: TopicAnalyzerService

```php
// app/Services/ContentPlan/TopicAnalyzerService.php
class TopicAnalyzerService
{
    public function extractTopics(Site $site): array
    {
        // Récupérer les pages (MySQL site_pages + SQLite si dispo)
        $pages = $this->loadPagesWithContent($site);

        $pagesContext = $pages->take(100)->map(fn($p) => [
            'title' => $p->title,
            'category' => $p->category,
            'url' => $p->url,
        ]);

        $prompt = "Analyse ces {$pages->count()} pages et identifie les 5-10 thématiques principales.
        Pages: {$pagesContext->toJson()}
        Retourne: {\"topics\": [{\"name\": \"...\", \"count\": X, \"examples\": [...]}]}";

        return $this->llm->generateJSON($prompt);
    }

    public function findGaps(Site $site): array
    {
        if (!$site->isGscConnected()) return [];

        $gscKeywords = $this->gsc->getTopKeywords($site, limit: 200);
        $existingTitles = $site->pages()->pluck('title')->map(fn($t) => strtolower($t));

        return $gscKeywords->filter(function($kw) use ($existingTitles) {
            return !$existingTitles->contains(fn($t) =>
                str_contains($t, strtolower($kw['keyword']))
            );
        })->take(50)->toArray();
    }

    private function loadPagesWithContent(Site $site): Collection
    {
        // 1. Charger depuis MySQL (toujours disponible)
        $mysqlPages = $site->pages()->get();

        // 2. Enrichir avec SQLite si disponible
        $indexService = app(SiteIndexService::class);
        if ($indexService->hasIndex($site)) {
            $sqlitePages = $indexService->getAllPages($site);
            // Merger les données (SQLite a le contenu, MySQL a les URLs)
            return $this->mergePageData($mysqlPages, $sqlitePages);
        }

        return $mysqlPages;
    }
}
```

### 6. ContentPlanGeneratorService modifié

```php
public function generate(Site $site): ContentPlanGeneration
{
    // Plus de crawl ici - déjà fait pendant l'onboarding

    $steps = $this->buildSteps($site);
    $generation = ContentPlanGeneration::create([...]);

    try {
        $stepIndex = 0;

        // Step 1: Extraction de topics depuis pages crawlées
        $generation->markStepRunning($stepIndex);
        $topics = $this->topicAnalyzer->extractTopics($site);
        $generation->markStepCompleted($stepIndex);
        $stepIndex++;

        // Step 2: Gap Analysis GSC (si connecté)
        $gaps = [];
        if ($site->isGscConnected()) {
            $generation->markStepRunning($stepIndex);
            $gaps = $this->topicAnalyzer->findGaps($site);
            $generation->markStepCompleted($stepIndex);
            $stepIndex++;
        }

        // Step 3: Génération d'idées basée sur topics + gaps + business
        $generation->markStepRunning($stepIndex);
        $keywords = $this->keywordDiscovery->generateTopicIdeas($site, [
            'topics' => $topics,
            'gaps' => $gaps,
            'count' => 50,
        ]);
        $generation->markStepCompleted($stepIndex);
        $stepIndex++;

        // Step 4: Filtrage duplicatas (utilise les pages crawlées)
        $generation->markStepRunning($stepIndex);
        $sitePages = $site->pages()->pluck('title', 'url');
        $keywords = $this->duplicateChecker->filterDuplicates($keywords, $sitePages);
        $generation->markStepCompleted($stepIndex);
        $stepIndex++;

        // Step 5-6: Enrichissement SEO + Planification (inchangé)
        // ...
    }
}
```

---

## Modifications Frontend

### 1. CrawlStatusIndicator component

```tsx
// resources/js/Components/Onboarding/CrawlStatusIndicator.tsx
export function CrawlStatusIndicator({ status, pagesCount }: Props) {
    if (status === 'pending') return null;

    return (
        <div className="flex items-center gap-2 text-sm text-muted-foreground">
            {status === 'running' && (
                <>
                    <Loader2 className="h-4 w-4 animate-spin" />
                    <span>Analyse en cours : {pagesCount} pages découvertes</span>
                </>
            )}
            {status === 'completed' && (
                <>
                    <CheckCircle className="h-4 w-4 text-green-500" />
                    <span>{pagesCount} pages analysées</span>
                </>
            )}
            {status === 'partial' && (
                <>
                    <Clock className="h-4 w-4 text-yellow-500" />
                    <span>{pagesCount} pages (analyse en cours...)</span>
                </>
            )}
            {status === 'failed' && (
                <>
                    <AlertCircle className="h-4 w-4 text-red-500" />
                    <span>Analyse partielle ({pagesCount} pages)</span>
                </>
            )}
        </div>
    );
}
```

### 2. Wizard avec écoute temps réel

```tsx
// resources/js/Pages/Onboarding/Wizard.tsx
useEffect(() => {
    if (!site?.id) return;

    const channel = Echo.private(`site.${site.id}`);
    channel.listen('SiteCrawlProgress', (e: CrawlProgressEvent) => {
        setSite(prev => prev ? {
            ...prev,
            crawl_status: e.status,
            crawl_pages_count: e.pagesCount,
        } : null);
    });

    return () => channel.stopListening('SiteCrawlProgress');
}, [site?.id]);

// Bloquer finalisation si crawl pas prêt
const canFinish = site && ['completed', 'partial', 'failed'].includes(site.crawl_status);
```

---

## Fichiers à créer/modifier

### Nouveaux fichiers
- `database/migrations/xxx_add_crawl_status_to_sites.php`
- `app/Events/SiteCrawlProgress.php`
- `app/Services/ContentPlan/TopicAnalyzerService.php`
- `resources/js/Components/Onboarding/CrawlStatusIndicator.tsx`

### Fichiers à modifier
- `app/Models/Site.php` — ajouter casts pour nouveaux champs
- `app/Http/Controllers/Web/OnboardingController.php` — lancer crawl au Step 1
- `app/Jobs/SiteIndexJob.php` — broadcast progression
- `app/Services/Crawler/SiteIndexService.php` — callback onProgress
- `app/Services/ContentPlan/ContentPlanGeneratorService.php` — utiliser TopicAnalyzer
- `resources/js/Pages/Onboarding/Wizard.tsx` — indicateur + écoute temps réel

---

## Récapitulatif du flow

```
┌─────────────────────────────────────────────────────────────────────────┐
│                         ONBOARDING FLOW                                  │
├─────────────────────────────────────────────────────────────────────────┤
│                                                                          │
│  Step 1: Création site                                                   │
│    ├── SiteCrawlerService::crawl() ──────→ site_pages MySQL (sync)       │
│    └── SiteIndexJob::dispatch() ─────────→ SQLite + embeddings (async)   │
│                                                                          │
│  Steps 2-5: Utilisateur continue                                         │
│    └── Crawl profond en background avec updates temps réel               │
│                                                                          │
│  Step 6: Finalisation                                                    │
│    ├── Attend crawl si running (max 2 min)                               │
│    └── GenerateContentPlanJob::dispatch()                                │
│                                                                          │
│  Content Plan Generation                                                 │
│    ├── TopicAnalyzer::extractTopics() ───→ LLM analyse les pages         │
│    ├── TopicAnalyzer::findGaps() ────────→ Croise avec GSC               │
│    ├── KeywordDiscovery::generate() ────→ Idées basées sur contexte      │
│    ├── DuplicateChecker::filter() ──────→ Évite les duplicatas           │
│    └── ContentPlanService::create() ────→ Planning final                 │
│                                                                          │
└─────────────────────────────────────────────────────────────────────────┘
```
