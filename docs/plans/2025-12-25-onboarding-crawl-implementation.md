# Onboarding Crawl Redesign - Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Refondre l'onboarding pour lancer le crawl dès Step 1 et utiliser les données crawlées pour générer un content plan intelligent.

**Architecture:** Crawl rapide (sitemap→MySQL) au Step 1 sync, crawl profond (Node.js→SQLite) en async. Progress temps réel via WebSocket. TopicAnalyzerService extrait les thématiques et gaps pour alimenter la génération de keywords.

**Tech Stack:** Laravel Jobs, Laravel Echo/Reverb, Node.js site-indexer, SQLite, React/TypeScript.

---

## Task 1: Migration - Ajouter crawl_status à sites

**Files:**
- Create: `database/migrations/2025_12_25_200000_add_crawl_status_to_sites_table.php`
- Modify: `app/Models/Site.php`

**Step 1: Créer la migration**

```bash
php artisan make:migration add_crawl_status_to_sites_table
```

**Step 2: Écrire la migration**

```php
// database/migrations/2025_12_25_200000_add_crawl_status_to_sites_table.php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            $table->string('crawl_status', 20)->default('pending')->after('last_crawled_at');
            $table->unsignedInteger('crawl_pages_count')->default(0)->after('crawl_status');
        });
    }

    public function down(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            $table->dropColumn(['crawl_status', 'crawl_pages_count']);
        });
    }
};
```

**Step 3: Modifier Site.php**

Ajouter dans `$fillable`:
```php
'crawl_status',
'crawl_pages_count',
```

Ajouter dans `$casts`:
```php
'crawl_pages_count' => 'integer',
```

**Step 4: Exécuter la migration**

```bash
php artisan migrate
```

**Step 5: Commit**

```bash
git add database/migrations/*crawl_status* app/Models/Site.php
git commit -m "feat: add crawl_status and crawl_pages_count to sites table"
```

---

## Task 2: Event SiteCrawlProgress

**Files:**
- Create: `app/Events/SiteCrawlProgress.php`

**Step 1: Créer l'event**

```php
// app/Events/SiteCrawlProgress.php
<?php

namespace App\Events;

use App\Models\Site;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SiteCrawlProgress implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Site $site,
        public string $status,
        public int $pagesCount,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('site.' . $this->site->id)];
    }

    public function broadcastAs(): string
    {
        return 'SiteCrawlProgress';
    }

    public function broadcastWith(): array
    {
        return [
            'site_id' => $this->site->id,
            'status' => $this->status,
            'pagesCount' => $this->pagesCount,
        ];
    }
}
```

**Step 2: Vérifier le channel auth dans routes/channels.php**

Vérifier que ce channel existe déjà ou l'ajouter:
```php
Broadcast::channel('site.{siteId}', function ($user, $siteId) {
    $site = \App\Models\Site::find($siteId);
    return $site && $site->team_id === $user->team_id;
});
```

**Step 3: Commit**

```bash
git add app/Events/SiteCrawlProgress.php routes/channels.php
git commit -m "feat: add SiteCrawlProgress broadcast event"
```

---

## Task 3: Modifier SiteIndexJob pour broadcast progression

**Files:**
- Modify: `app/Jobs/SiteIndexJob.php`

**Step 1: Ajouter les imports et modifier handle()**

```php
// app/Jobs/SiteIndexJob.php
<?php

namespace App\Jobs;

use App\Events\SiteCrawlProgress;
use App\Models\Site;
use App\Services\Crawler\SiteIndexService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SiteIndexJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $timeout = 900;
    public int $backoff = 120;

    public function __construct(
        public readonly Site $site,
        public readonly bool $delta = true,
    ) {}

    public function handle(SiteIndexService $indexService): void
    {
        Log::info('SiteIndexJob: Starting', [
            'site_id' => $this->site->id,
            'domain' => $this->site->domain,
            'delta' => $this->delta,
        ]);

        $this->site->update(['crawl_status' => 'running']);
        broadcast(new SiteCrawlProgress($this->site, 'running', $this->site->crawl_pages_count));

        try {
            $result = $indexService->indexSite($this->site, $this->delta);

            $this->site->update([
                'crawl_status' => 'completed',
                'crawl_pages_count' => $result['pages_indexed'] ?? 0,
                'last_crawled_at' => now(),
            ]);

            broadcast(new SiteCrawlProgress($this->site, 'completed', $result['pages_indexed'] ?? 0));

            Log::info('SiteIndexJob: Completed', [
                'site_id' => $this->site->id,
                'pages_indexed' => $result['pages_indexed'] ?? 0,
            ]);
        } catch (\Exception $e) {
            Log::error('SiteIndexJob: Failed', [
                'site_id' => $this->site->id,
                'error' => $e->getMessage(),
            ]);

            $this->site->update(['crawl_status' => 'failed']);
            broadcast(new SiteCrawlProgress($this->site, 'failed', $this->site->crawl_pages_count));

            throw $e;
        }
    }

    public function uniqueId(): string
    {
        return 'site-index-' . $this->site->id;
    }

    public function uniqueFor(): int
    {
        return 3600;
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('SiteIndexJob: Permanently failed', [
            'site_id' => $this->site->id,
            'domain' => $this->site->domain,
            'error' => $exception->getMessage(),
        ]);

        $this->site->update(['crawl_status' => 'failed']);
        broadcast(new SiteCrawlProgress($this->site, 'failed', $this->site->crawl_pages_count));
    }

    public function tags(): array
    {
        return [
            'site-indexer',
            'site:' . $this->site->id,
        ];
    }
}
```

**Step 2: Commit**

```bash
git add app/Jobs/SiteIndexJob.php
git commit -m "feat: broadcast crawl progress from SiteIndexJob"
```

---

## Task 4: Modifier OnboardingController pour lancer le crawl au Step 1

**Files:**
- Modify: `app/Http/Controllers/Web/OnboardingController.php`

**Step 1: Ajouter les imports**

```php
use App\Jobs\SiteIndexJob;
use App\Services\Crawler\SiteCrawlerService;
```

**Step 2: Injecter SiteCrawlerService dans le constructeur**

```php
public function __construct(
    private readonly SiteCrawlerService $crawler,
) {}
```

**Step 3: Modifier storeStep1()**

```php
public function storeStep1(Request $request)
{
    $validated = $request->validate([
        'domain' => 'required|string|max:255',
        'name' => 'required|string|max:255',
        'language' => 'required|string|size:2',
    ]);

    // Créer le site avec status running
    $site = Site::create([
        'team_id' => auth()->user()->team_id,
        'crawl_status' => 'running',
        ...$validated,
    ]);

    // Crawl rapide du sitemap (sync) - stocke dans site_pages MySQL
    try {
        $this->crawler->crawl($site);
        $this->crawler->extractTitlesForPages($site, 50);

        $pagesCount = $site->pages()->count();
        $site->update([
            'crawl_status' => 'partial',
            'crawl_pages_count' => $pagesCount,
        ]);
    } catch (\Exception $e) {
        // Le crawl sitemap a échoué, on continue quand même
        \Log::warning('Sitemap crawl failed', ['site_id' => $site->id, 'error' => $e->getMessage()]);
    }

    // Lancer le crawl profond avec embeddings (async)
    SiteIndexJob::dispatch($site, delta: false)->onQueue('crawl');

    return response()->json(['site_id' => $site->id]);
}
```

**Step 4: Commit**

```bash
git add app/Http/Controllers/Web/OnboardingController.php
git commit -m "feat: start crawl at onboarding step 1"
```

---

## Task 5: Créer TopicAnalyzerService

**Files:**
- Create: `app/Services/ContentPlan/TopicAnalyzerService.php`

**Step 1: Créer le service**

```php
// app/Services/ContentPlan/TopicAnalyzerService.php
<?php

namespace App\Services\ContentPlan;

use App\Models\Site;
use App\Services\Crawler\SiteIndexService;
use App\Services\Google\SearchConsoleService;
use App\Services\LLM\LLMManager;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class TopicAnalyzerService
{
    public function __construct(
        private readonly LLMManager $llm,
        private readonly SiteIndexService $indexService,
        private readonly SearchConsoleService $searchConsole,
    ) {}

    /**
     * Extrait les thématiques principales du site à partir des pages crawlées.
     */
    public function extractTopics(Site $site): array
    {
        $pages = $this->loadPagesWithContent($site);

        if ($pages->isEmpty()) {
            Log::warning('TopicAnalyzer: No pages to analyze', ['site_id' => $site->id]);
            return [];
        }

        $pagesContext = $pages->take(100)->map(fn($p) => [
            'title' => $p['title'] ?? '',
            'category' => $p['category'] ?? null,
            'url' => $p['url'] ?? '',
        ])->filter(fn($p) => !empty($p['title']))->values();

        if ($pagesContext->isEmpty()) {
            return [];
        }

        $prompt = "Analyse ces {$pagesContext->count()} pages d'un site web et identifie les 5-10 thématiques principales.

Pages du site:
{$pagesContext->toJson(JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)}

Retourne un JSON avec ce format exact:
{
    \"topics\": [
        {\"name\": \"nom de la thématique\", \"count\": nombre_de_pages, \"examples\": [\"titre exemple 1\", \"titre exemple 2\"]}
    ]
}";

        try {
            $result = $this->llm->generateJSON($prompt, 'Extraction de thématiques');
            return $result['topics'] ?? [];
        } catch (\Exception $e) {
            Log::error('TopicAnalyzer: LLM failed', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Trouve les keywords GSC qui n'ont pas de page dédiée (gaps).
     */
    public function findGaps(Site $site): array
    {
        if (!$site->isGscConnected()) {
            return [];
        }

        try {
            $gscKeywords = $this->searchConsole->getSearchAnalytics(
                $site,
                now()->subDays(28)->format('Y-m-d'),
                now()->subDay()->format('Y-m-d'),
                ['query'],
                200
            );

            $existingTitles = $site->pages()
                ->whereNotNull('title')
                ->pluck('title')
                ->map(fn($t) => strtolower($t));

            $gaps = collect($gscKeywords)->filter(function($row) use ($existingTitles) {
                $keyword = strtolower($row->keys[0] ?? '');
                if (strlen($keyword) < 3) return false;

                // Garder si aucune page n'a ce keyword dans le titre
                return !$existingTitles->contains(fn($t) => str_contains($t, $keyword));
            })->take(50)->map(fn($row) => [
                'keyword' => $row->keys[0],
                'impressions' => $row->impressions ?? 0,
                'clicks' => $row->clicks ?? 0,
                'position' => $row->position ?? null,
            ])->values()->toArray();

            return $gaps;
        } catch (\Exception $e) {
            Log::warning('TopicAnalyzer: GSC gaps analysis failed', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Charge les pages avec contenu depuis MySQL + SQLite si disponible.
     */
    private function loadPagesWithContent(Site $site): Collection
    {
        // 1. Charger depuis MySQL (toujours disponible)
        $mysqlPages = $site->pages()->get()->map(fn($p) => [
            'url' => $p->url,
            'title' => $p->title,
            'category' => null,
            'source' => 'mysql',
        ]);

        // 2. Enrichir avec SQLite si disponible
        if ($this->indexService->hasIndex($site)) {
            try {
                $sqlitePages = $this->indexService->getAllPages($site);

                // Créer un map URL -> données SQLite
                $sqliteMap = collect($sqlitePages)->keyBy('url');

                // Merger les données
                $mysqlPages = $mysqlPages->map(function($page) use ($sqliteMap) {
                    if (isset($sqliteMap[$page['url']])) {
                        $sqlite = $sqliteMap[$page['url']];
                        $page['title'] = $sqlite['title'] ?: $page['title'];
                        $page['category'] = $sqlite['category'] ?? null;
                        $page['source'] = 'sqlite';
                    }
                    return $page;
                });
            } catch (\Exception $e) {
                Log::warning('TopicAnalyzer: SQLite read failed', ['error' => $e->getMessage()]);
            }
        }

        return $mysqlPages;
    }
}
```

**Step 2: Commit**

```bash
git add app/Services/ContentPlan/TopicAnalyzerService.php
git commit -m "feat: add TopicAnalyzerService for topic extraction and gap analysis"
```

---

## Task 6: Ajouter getAllPages() à SiteIndexService

**Files:**
- Modify: `app/Services/Crawler/SiteIndexService.php`

**Step 1: Ajouter la méthode getAllPages()**

```php
/**
 * Récupère toutes les pages depuis l'index SQLite.
 */
public function getAllPages(Site $site): array
{
    $path = $this->getIndexPath($site);
    if (!file_exists($path)) {
        return [];
    }

    try {
        $db = new \SQLite3($path, SQLITE3_OPEN_READONLY);
        $result = $db->query('SELECT url, title, h1, meta_description, category, tags, inbound_links_count FROM pages ORDER BY crawled_at DESC');

        $pages = [];
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $pages[] = $row;
        }

        $db->close();
        return $pages;
    } catch (\Exception $e) {
        Log::error('SiteIndexService: Failed to read SQLite', ['error' => $e->getMessage()]);
        return [];
    }
}
```

**Step 2: Commit**

```bash
git add app/Services/Crawler/SiteIndexService.php
git commit -m "feat: add getAllPages() to SiteIndexService"
```

---

## Task 7: Modifier ContentPlanGeneratorService pour utiliser TopicAnalyzer

**Files:**
- Modify: `app/Services/ContentPlan/ContentPlanGeneratorService.php`

**Step 1: Ajouter TopicAnalyzerService dans le constructeur**

```php
public function __construct(
    private readonly SiteCrawlerService $crawler,
    private readonly KeywordDiscoveryService $keywordDiscovery,
    private readonly KeywordScoringService $keywordScoring,
    private readonly DuplicateCheckerService $duplicateChecker,
    private readonly ContentPlanService $planService,
    private readonly TopicAnalyzerService $topicAnalyzer,
) {}
```

**Step 2: Modifier la méthode generate()**

```php
public function generate(Site $site): ContentPlanGeneration
{
    Log::info("Starting content plan generation", ['site_id' => $site->id]);

    $steps = $this->buildSteps($site);

    $generation = ContentPlanGeneration::create([
        'site_id' => $site->id,
        'status' => 'running',
        'current_step' => 1,
        'total_steps' => count($steps),
        'steps' => $steps,
    ]);

    try {
        $keywords = collect();
        $stepIndex = 0;

        // Step 1: Extraction de topics depuis pages crawlées
        $generation->markStepRunning($stepIndex);
        $topics = $this->topicAnalyzer->extractTopics($site);
        Log::info("Extracted topics", ['count' => count($topics)]);
        $generation->markStepCompleted($stepIndex);
        $stepIndex++;

        // Step 2: Gap Analysis GSC (si connecté)
        $gaps = [];
        if ($site->isGscConnected()) {
            $generation->markStepRunning($stepIndex);
            try {
                $gscKeywords = $this->keywordDiscovery->mineFromSearchConsole($site);
                $keywords = $keywords->merge($gscKeywords);
                $gaps = $this->topicAnalyzer->findGaps($site);
                Log::info("Found GSC gaps", ['count' => count($gaps)]);
            } catch (\Exception $e) {
                Log::warning("GSC analysis failed, continuing without", [
                    'site_id' => $site->id,
                    'error' => $e->getMessage(),
                ]);
            }
            $generation->markStepCompleted($stepIndex);
            $stepIndex++;
        }

        // Step 3: Génération d'idées avec contexte enrichi
        if ($site->business_description || $keywords->isEmpty()) {
            $generation->markStepRunning($stepIndex);
            $aiKeywords = $this->keywordDiscovery->generateTopicIdeas($site, [
                'count' => 50,
                'existing_keywords' => $keywords->pluck('keyword')->toArray(),
                'topics' => $topics,
                'gaps' => $gaps,
            ]);
            $keywords = $keywords->merge($aiKeywords);
            Log::info("Generated keywords via AI", ['count' => $aiKeywords->count()]);
            $generation->markStepCompleted($stepIndex);
            $stepIndex++;
        }

        // Step 4: Check duplicates (toujours)
        $generation->markStepRunning($stepIndex);
        $sitePages = $site->pages()->pluck('title', 'url');
        $keywords = $this->duplicateChecker->filterDuplicates($keywords, $sitePages);
        $generation->markStepCompleted($stepIndex);
        $stepIndex++;

        // Step 5: Enrich with SEO data (toujours)
        $generation->markStepRunning($stepIndex);
        $keywords = $this->keywordDiscovery->enrichWithSeoData($keywords, $site->language ?? 'en');
        $keywords = $keywords->map(function ($kw) {
            if (!isset($kw['score'])) {
                $kw['score'] = $this->keywordScoring->calculateScoreFromData(
                    new \App\Services\SEO\DTOs\KeywordData(
                        keyword: $kw['keyword'],
                        volume: $kw['volume'] ?? 0,
                        difficulty: $kw['difficulty'] ?? 50,
                        cpc: $kw['cpc'] ?? 0,
                    ),
                    $kw['position'] ?? null,
                );
            }
            return $kw;
        });
        $generation->markStepCompleted($stepIndex);
        $stepIndex++;

        // Step 6: Create plan (toujours)
        $generation->markStepRunning($stepIndex);
        $scheduled = $this->planService->createPlan($site, $keywords);
        $generation->markStepCompleted($stepIndex);

        $generation->markCompleted($keywords->count(), $scheduled->count());

        Log::info("Content plan generation completed", [
            'site_id' => $site->id,
            'keywords_found' => $keywords->count(),
            'articles_planned' => $scheduled->count(),
        ]);

    } catch (\Exception $e) {
        Log::error("Content plan generation failed", [
            'site_id' => $site->id,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);

        $generation->markFailed($e->getMessage());
        throw $e;
    }

    return $generation;
}
```

**Step 3: Modifier buildSteps() pour refléter les nouvelles étapes**

```php
private function buildSteps(Site $site): array
{
    $steps = [];

    $steps[] = ['name' => 'Analyse des thématiques du site', 'status' => 'pending', 'icon' => 'sparkles'];

    if ($site->isGscConnected()) {
        $steps[] = ['name' => 'Analyse de vos performances GSC', 'status' => 'pending', 'icon' => 'chart'];
    }

    if ($site->business_description) {
        $steps[] = ['name' => "Génération d'idées avec l'IA", 'status' => 'pending', 'icon' => 'lightbulb'];
    }

    $steps[] = ['name' => 'Vérification des sujets existants', 'status' => 'pending', 'icon' => 'search'];
    $steps[] = ['name' => 'Analyse du potentiel SEO', 'status' => 'pending', 'icon' => 'trending'];
    $steps[] = ['name' => 'Création du calendrier éditorial', 'status' => 'pending', 'icon' => 'calendar'];

    return $steps;
}
```

**Step 4: Commit**

```bash
git add app/Services/ContentPlan/ContentPlanGeneratorService.php
git commit -m "feat: integrate TopicAnalyzer into content plan generation"
```

---

## Task 8: Composant React CrawlStatusIndicator

**Files:**
- Create: `resources/js/Components/Onboarding/CrawlStatusIndicator.tsx`

**Step 1: Créer le composant**

```tsx
// resources/js/Components/Onboarding/CrawlStatusIndicator.tsx
import { Loader2, CheckCircle, Clock, AlertCircle } from 'lucide-react';

interface Props {
    status: 'pending' | 'running' | 'partial' | 'completed' | 'failed';
    pagesCount: number;
}

export function CrawlStatusIndicator({ status, pagesCount }: Props) {
    if (status === 'pending') return null;

    return (
        <div className="flex items-center gap-2 text-sm">
            {status === 'running' && (
                <>
                    <Loader2 className="h-4 w-4 animate-spin text-blue-500" />
                    <span className="text-muted-foreground">
                        Analyse en cours : {pagesCount} pages découvertes
                    </span>
                </>
            )}
            {status === 'completed' && (
                <>
                    <CheckCircle className="h-4 w-4 text-green-500" />
                    <span className="text-green-600">
                        {pagesCount} pages analysées
                    </span>
                </>
            )}
            {status === 'partial' && (
                <>
                    <Clock className="h-4 w-4 text-yellow-500" />
                    <span className="text-muted-foreground">
                        {pagesCount} pages (analyse approfondie en cours...)
                    </span>
                </>
            )}
            {status === 'failed' && (
                <>
                    <AlertCircle className="h-4 w-4 text-red-500" />
                    <span className="text-red-600">
                        Analyse partielle ({pagesCount} pages)
                    </span>
                </>
            )}
        </div>
    );
}
```

**Step 2: Commit**

```bash
git add resources/js/Components/Onboarding/CrawlStatusIndicator.tsx
git commit -m "feat: add CrawlStatusIndicator component"
```

---

## Task 9: Modifier le Wizard pour écouter le crawl en temps réel

**Files:**
- Modify: `resources/js/Pages/Onboarding/Wizard.tsx`

**Step 1: Ajouter les imports**

```tsx
import { useEffect } from 'react';
import { CrawlStatusIndicator } from '@/Components/Onboarding/CrawlStatusIndicator';
```

**Step 2: Ajouter le hook useEffect pour écouter Echo**

Dans le composant, après les états existants :

```tsx
// Écouter les updates de crawl en temps réel
useEffect(() => {
    if (!site?.id) return;

    const channel = (window as any).Echo?.private(`site.${site.id}`);
    if (!channel) return;

    const handler = (e: { status: string; pagesCount: number }) => {
        setSite((prev: any) => prev ? {
            ...prev,
            crawl_status: e.status,
            crawl_pages_count: e.pagesCount,
        } : null);
    };

    channel.listen('.SiteCrawlProgress', handler);

    return () => {
        channel.stopListening('.SiteCrawlProgress', handler);
    };
}, [site?.id]);
```

**Step 3: Ajouter l'indicateur dans le JSX**

Après le header du wizard, ajouter :

```tsx
{site && (
    <div className="mb-4">
        <CrawlStatusIndicator
            status={site.crawl_status || 'pending'}
            pagesCount={site.crawl_pages_count || 0}
        />
    </div>
)}
```

**Step 4: Modifier la condition de finalisation**

```tsx
const canFinish = site && ['completed', 'partial', 'failed'].includes(site.crawl_status || '');
```

**Step 5: Commit**

```bash
git add resources/js/Pages/Onboarding/Wizard.tsx
git commit -m "feat: add real-time crawl progress to onboarding wizard"
```

---

## Task 10: Mettre à jour les types TypeScript

**Files:**
- Modify: `resources/js/types/index.d.ts` ou fichier de types approprié

**Step 1: Ajouter les nouveaux champs au type Site**

```typescript
interface Site {
    // ... champs existants
    crawl_status: 'pending' | 'running' | 'partial' | 'completed' | 'failed';
    crawl_pages_count: number;
}
```

**Step 2: Commit**

```bash
git add resources/js/types/
git commit -m "feat: add crawl status types"
```

---

## Task 11: Test manuel et commit final

**Step 1: Lancer les migrations**

```bash
php artisan migrate
```

**Step 2: Rebuild le frontend**

```bash
npm run build
```

**Step 3: Tester le flow complet**

1. Créer un nouveau site via l'onboarding
2. Vérifier que le crawl démarre immédiatement
3. Vérifier les updates temps réel pendant les steps 2-5
4. Vérifier que la finalisation attend le crawl si nécessaire

**Step 4: Commit final**

```bash
git add -A
git commit -m "feat: complete onboarding crawl redesign implementation"
```

---

## Récapitulatif des fichiers

| Action | Fichier |
|--------|---------|
| Create | `database/migrations/2025_12_25_200000_add_crawl_status_to_sites_table.php` |
| Create | `app/Events/SiteCrawlProgress.php` |
| Create | `app/Services/ContentPlan/TopicAnalyzerService.php` |
| Create | `resources/js/Components/Onboarding/CrawlStatusIndicator.tsx` |
| Modify | `app/Models/Site.php` |
| Modify | `app/Jobs/SiteIndexJob.php` |
| Modify | `app/Http/Controllers/Web/OnboardingController.php` |
| Modify | `app/Services/Crawler/SiteIndexService.php` |
| Modify | `app/Services/ContentPlan/ContentPlanGeneratorService.php` |
| Modify | `resources/js/Pages/Onboarding/Wizard.tsx` |
| Modify | `routes/channels.php` |
