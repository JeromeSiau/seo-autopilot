# Content Plan Auto-Generation Design

## Overview

Après l'onboarding, générer automatiquement un Content Plan de 30 jours avec effet "waouh" : progressbar animée → célébration → révélation du calendrier.

## User Flow

```
WIZARD STEP 6
└── Click "Activer l'Autopilot"
    └── POST /onboarding/complete
        └── Crée GenerateContentPlanJob
        └── Redirect → /onboarding/generating/{site}

PAGE GENERATING (nouvelle)
└── Affiche les étapes dynamiques
└── Poll GET /api/sites/{id}/generation-status toutes les 2s
└── Met à jour la step courante + progression

TERMINÉ
└── Animation célébration (confettis + message)
└── Pause 2-3s
└── Fade in du calendrier 30 jours
└── Boutons "Voir le dashboard" / "Modifier le plan"
```

## Étapes dynamiques

Les étapes affichées dépendent de la configuration du site :

| Étape | Condition | Nom affiché | Action |
|-------|-----------|-------------|--------|
| 1 | TOUJOURS | "Analyse de votre site existant..." | Fetch sitemap.xml + pages GSC |
| 2 | SI GSC connecté | "Analyse de vos performances..." | Mine keywords depuis GSC |
| 3 | SI business description | "Génération d'idées avec l'IA..." | GPT génère 30-50 keywords |
| 4 | TOUJOURS | "Vérification des sujets existants..." | LLM batch check duplicates |
| 5 | TOUJOURS | "Analyse du potentiel de chaque sujet..." | DataForSEO enrichissement |
| 6 | TOUJOURS | "Création de votre Content Plan..." | Scoring + scheduling |

### Fallback

Si pas assez de keywords (pas de GSC, pas de description) :
- Forcer génération IA basée sur le domaine seul
- Garantir minimum 10-15 keywords pour un calendrier crédible

## Data Model

### Table: `site_pages`

Stocke le contenu existant du site pour éviter les doublons.

```php
Schema::create('site_pages', function (Blueprint $table) {
    $table->id();
    $table->foreignId('site_id')->constrained()->cascadeOnDelete();
    $table->string('url');
    $table->string('title')->nullable();
    $table->enum('source', ['sitemap', 'gsc', 'crawl'])->default('sitemap');
    $table->timestamp('last_seen_at')->nullable();
    $table->timestamps();

    $table->unique(['site_id', 'url']);
    $table->index(['site_id', 'source']);
});
```

### Table: `scheduled_articles`

Le calendrier de contenu planifié.

```php
Schema::create('scheduled_articles', function (Blueprint $table) {
    $table->id();
    $table->foreignId('site_id')->constrained()->cascadeOnDelete();
    $table->foreignId('keyword_id')->constrained()->cascadeOnDelete();
    $table->date('scheduled_date');
    $table->enum('status', ['planned', 'generating', 'ready', 'published', 'skipped'])->default('planned');
    $table->foreignId('article_id')->nullable()->constrained()->nullOnDelete();
    $table->timestamps();

    $table->index(['site_id', 'scheduled_date']);
    $table->index(['site_id', 'status']);
});
```

### Table: `content_plan_generations`

Tracking du process de génération pour le polling.

```php
Schema::create('content_plan_generations', function (Blueprint $table) {
    $table->id();
    $table->foreignId('site_id')->constrained()->cascadeOnDelete();
    $table->enum('status', ['pending', 'running', 'completed', 'failed'])->default('pending');
    $table->unsignedTinyInteger('current_step')->default(1);
    $table->unsignedTinyInteger('total_steps')->default(6);
    $table->json('steps')->nullable(); // [{name, status, started_at, completed_at}]
    $table->unsignedInteger('keywords_found')->default(0);
    $table->unsignedInteger('articles_planned')->default(0);
    $table->text('error_message')->nullable();
    $table->timestamps();

    $table->index(['site_id', 'status']);
});
```

## Backend Architecture

### Job: `GenerateContentPlanJob`

```php
class GenerateContentPlanJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public Site $site
    ) {}

    public function handle(ContentPlanGeneratorService $generator): void
    {
        $generator->generate($this->site);
    }
}
```

### Service: `ContentPlanGeneratorService`

```php
class ContentPlanGeneratorService
{
    public function __construct(
        private SiteCrawlerService $crawler,
        private KeywordDiscoveryService $keywordDiscovery,
        private DuplicateCheckerService $duplicateChecker,
        private ContentPlanService $planService,
    ) {}

    public function generate(Site $site): ContentPlanGeneration
    {
        // Determine which steps to run
        $steps = $this->buildSteps($site);

        // Create tracking record
        $generation = ContentPlanGeneration::create([
            'site_id' => $site->id,
            'status' => 'running',
            'total_steps' => count($steps),
            'steps' => $steps,
        ]);

        try {
            $keywords = collect();

            // Step 1: Crawl site (always)
            $this->runStep($generation, 1, fn() =>
                $this->crawler->crawl($site)
            );

            // Step 2: Analyze GSC (if connected)
            if ($site->isGscConnected()) {
                $gscKeywords = $this->runStep($generation, 2, fn() =>
                    $this->keywordDiscovery->mineFromSearchConsole($site)
                );
                $keywords = $keywords->merge($gscKeywords);
            }

            // Step 3: Generate AI ideas (if business description)
            if ($site->business_description || $keywords->isEmpty()) {
                $aiKeywords = $this->runStep($generation, 3, fn() =>
                    $this->keywordDiscovery->generateTopicIdeas($site, [
                        'count' => 50,
                        'existing_keywords' => $keywords->pluck('keyword')->toArray(),
                    ])
                );
                $keywords = $keywords->merge($aiKeywords);
            }

            // Step 4: Check duplicates (always)
            $sitePages = $site->pages()->pluck('title', 'url');
            $keywords = $this->runStep($generation, 4, fn() =>
                $this->duplicateChecker->filterDuplicates($keywords, $sitePages)
            );

            // Step 5: Enrich with SEO data (always)
            $keywords = $this->runStep($generation, 5, fn() =>
                $this->keywordDiscovery->enrichWithSeoData($keywords, $site->language)
            );

            // Step 6: Create plan (always)
            $scheduled = $this->runStep($generation, 6, fn() =>
                $this->planService->createPlan($site, $keywords)
            );

            $generation->update([
                'status' => 'completed',
                'keywords_found' => $keywords->count(),
                'articles_planned' => $scheduled->count(),
            ]);

        } catch (\Exception $e) {
            $generation->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);
            throw $e;
        }

        return $generation;
    }

    private function buildSteps(Site $site): array
    {
        $steps = [
            ['name' => 'Analyse de votre site existant', 'status' => 'pending'],
        ];

        if ($site->isGscConnected()) {
            $steps[] = ['name' => 'Analyse de vos performances', 'status' => 'pending'];
        }

        if ($site->business_description) {
            $steps[] = ['name' => 'Génération d\'idées avec l\'IA', 'status' => 'pending'];
        }

        $steps[] = ['name' => 'Vérification des sujets existants', 'status' => 'pending'];
        $steps[] = ['name' => 'Analyse du potentiel de chaque sujet', 'status' => 'pending'];
        $steps[] = ['name' => 'Création de votre Content Plan', 'status' => 'pending'];

        return $steps;
    }

    private function runStep(ContentPlanGeneration $generation, int $step, callable $action): mixed
    {
        $this->updateStep($generation, $step, 'running');
        $result = $action();
        $this->updateStep($generation, $step, 'completed');
        return $result;
    }

    private function updateStep(ContentPlanGeneration $generation, int $step, string $status): void
    {
        $steps = $generation->steps;
        $steps[$step - 1]['status'] = $status;
        $steps[$step - 1][$status === 'running' ? 'started_at' : 'completed_at'] = now();

        $generation->update([
            'current_step' => $step,
            'steps' => $steps,
        ]);
    }
}
```

### Service: `DuplicateCheckerService`

```php
class DuplicateCheckerService
{
    public function __construct(
        private LLMManager $llm
    ) {}

    public function filterDuplicates(Collection $keywords, Collection $sitePages): Collection
    {
        if ($sitePages->isEmpty()) {
            return $keywords;
        }

        $keywordList = $keywords->pluck('keyword')->implode("\n- ");
        $pageList = $sitePages->map(fn($title, $url) => "- {$title}")->implode("\n");

        $prompt = <<<PROMPT
Voici des mots-clés candidats pour de nouveaux articles:
- {$keywordList}

Voici les articles existants sur le site:
{$pageList}

Pour chaque mot-clé, indique s'il est:
- "new" = pas couvert par un article existant
- "covered" = déjà un article similaire existe
- "partial" = partiellement couvert, pourrait compléter

Réponds en JSON: {"keyword": "status", ...}
PROMPT;

        $response = $this->llm->completeJson('openai', $prompt, [], [
            'model' => 'gpt-4o-mini',
            'temperature' => 0.1,
        ]);

        $results = $response->getJson() ?? [];

        return $keywords->filter(function ($kw) use ($results) {
            $status = $results[$kw['keyword']] ?? 'new';
            return $status !== 'covered';
        });
    }
}
```

### Service: `ContentPlanService`

```php
class ContentPlanService
{
    public function createPlan(Site $site, Collection $keywords, int $days = 30): Collection
    {
        $settings = $site->settings;
        $publishDays = $settings?->publish_days ?? ['mon', 'wed', 'fri'];
        $articlesPerWeek = $settings?->articles_per_week ?? 3;

        // Sort keywords by score
        $sortedKeywords = $keywords->sortByDesc('score')->values();

        // Get publish dates for next 30 days
        $publishDates = $this->getPublishDates($publishDays, $days, $articlesPerWeek);

        // Create scheduled articles
        $scheduled = collect();
        foreach ($publishDates as $index => $date) {
            if (!isset($sortedKeywords[$index])) {
                break;
            }

            $keyword = $sortedKeywords[$index];

            // Save keyword if not already saved
            $keywordModel = Keyword::firstOrCreate(
                ['site_id' => $site->id, 'keyword' => $keyword['keyword']],
                [
                    'volume' => $keyword['volume'] ?? null,
                    'difficulty' => $keyword['difficulty'] ?? null,
                    'score' => $keyword['score'] ?? 0,
                    'source' => $keyword['source'] ?? 'ai_generated',
                    'status' => 'queued',
                ]
            );

            $scheduledArticle = ScheduledArticle::create([
                'site_id' => $site->id,
                'keyword_id' => $keywordModel->id,
                'scheduled_date' => $date,
                'status' => 'planned',
            ]);

            $scheduled->push($scheduledArticle);
        }

        return $scheduled;
    }

    private function getPublishDates(array $publishDays, int $days, int $maxPerWeek): array
    {
        $dates = [];
        $currentDate = now()->addDay();
        $endDate = now()->addDays($days);
        $weekCount = [];

        while ($currentDate <= $endDate) {
            $dayName = strtolower($currentDate->format('D'));
            $weekNumber = $currentDate->weekOfYear;

            if (in_array($dayName, $publishDays)) {
                $weekCount[$weekNumber] = ($weekCount[$weekNumber] ?? 0) + 1;

                if ($weekCount[$weekNumber] <= $maxPerWeek) {
                    $dates[] = $currentDate->toDateString();
                }
            }

            $currentDate->addDay();
        }

        return $dates;
    }
}
```

### Service: `SiteCrawlerService`

```php
class SiteCrawlerService
{
    public function crawl(Site $site): int
    {
        $pagesFound = 0;

        // 1. Try sitemap
        $pagesFound += $this->crawlSitemap($site);

        // 2. Add GSC pages if connected
        if ($site->isGscConnected()) {
            $pagesFound += $this->importFromGSC($site);
        }

        return $pagesFound;
    }

    private function crawlSitemap(Site $site): int
    {
        $sitemapUrl = "https://{$site->domain}/sitemap.xml";

        try {
            $response = Http::timeout(10)->get($sitemapUrl);

            if (!$response->successful()) {
                return 0;
            }

            $xml = simplexml_load_string($response->body());
            $count = 0;

            foreach ($xml->url as $url) {
                $pageUrl = (string) $url->loc;

                // Try to get title from page (optional, can be slow)
                $title = $this->extractTitle($pageUrl);

                SitePage::updateOrCreate(
                    ['site_id' => $site->id, 'url' => $pageUrl],
                    ['title' => $title, 'source' => 'sitemap', 'last_seen_at' => now()]
                );
                $count++;
            }

            return $count;
        } catch (\Exception $e) {
            Log::warning("Sitemap crawl failed for {$site->domain}: {$e->getMessage()}");
            return 0;
        }
    }

    private function importFromGSC(Site $site): int
    {
        $searchConsole = app(SearchConsoleService::class);
        $pages = $searchConsole->getTopPages($site, 28, 100);
        $count = 0;

        foreach ($pages as $page) {
            SitePage::updateOrCreate(
                ['site_id' => $site->id, 'url' => $page->url],
                ['title' => $page->title ?? null, 'source' => 'gsc', 'last_seen_at' => now()]
            );
            $count++;
        }

        return $count;
    }

    private function extractTitle(string $url): ?string
    {
        try {
            $response = Http::timeout(5)->get($url);
            if (preg_match('/<title>(.+?)<\/title>/i', $response->body(), $matches)) {
                return html_entity_decode($matches[1]);
            }
        } catch (\Exception $e) {
            // Ignore failures
        }
        return null;
    }
}
```

## API Endpoints

### GET `/api/sites/{site}/generation-status`

```php
// routes/api.php
Route::get('/sites/{site}/generation-status', [ContentPlanController::class, 'generationStatus']);

// ContentPlanController.php
public function generationStatus(Site $site)
{
    $generation = $site->contentPlanGenerations()
        ->latest()
        ->first();

    if (!$generation) {
        return response()->json(['status' => 'not_started']);
    }

    return response()->json([
        'status' => $generation->status,
        'current_step' => $generation->current_step,
        'total_steps' => $generation->total_steps,
        'steps' => $generation->steps,
        'keywords_found' => $generation->keywords_found,
        'articles_planned' => $generation->articles_planned,
        'error_message' => $generation->error_message,
    ]);
}
```

### GET `/api/sites/{site}/content-plan`

```php
public function contentPlan(Site $site, Request $request)
{
    $month = $request->get('month', now()->format('Y-m'));

    $scheduled = $site->scheduledArticles()
        ->with('keyword')
        ->whereRaw("DATE_FORMAT(scheduled_date, '%Y-%m') = ?", [$month])
        ->orderBy('scheduled_date')
        ->get();

    return response()->json([
        'month' => $month,
        'articles' => $scheduled->map(fn($s) => [
            'id' => $s->id,
            'date' => $s->scheduled_date,
            'keyword' => $s->keyword->keyword,
            'volume' => $s->keyword->volume,
            'difficulty' => $s->keyword->difficulty,
            'status' => $s->status,
        ]),
    ]);
}
```

## Frontend Components

### Page: `Onboarding/Generating.tsx`

```tsx
interface Step {
  name: string;
  status: 'pending' | 'running' | 'completed';
  started_at?: string;
  completed_at?: string;
}

interface GenerationStatus {
  status: 'pending' | 'running' | 'completed' | 'failed';
  current_step: number;
  total_steps: number;
  steps: Step[];
  keywords_found: number;
  articles_planned: number;
  error_message?: string;
}

export default function Generating({ site }: { site: Site }) {
  const [status, setStatus] = useState<GenerationStatus | null>(null);
  const [showCelebration, setShowCelebration] = useState(false);
  const [showCalendar, setShowCalendar] = useState(false);

  // Polling
  useEffect(() => {
    const interval = setInterval(async () => {
      const res = await fetch(`/api/sites/${site.id}/generation-status`);
      const data = await res.json();
      setStatus(data);

      if (data.status === 'completed') {
        clearInterval(interval);
        // Trigger celebration sequence
        setTimeout(() => setShowCelebration(true), 500);
        setTimeout(() => {
          setShowCelebration(false);
          setShowCalendar(true);
        }, 3000);
      }
    }, 2000);

    return () => clearInterval(interval);
  }, [site.id]);

  if (showCalendar) {
    return <CalendarReveal site={site} articlesPlanned={status?.articles_planned} />;
  }

  if (showCelebration) {
    return <Celebration articlesPlanned={status?.articles_planned} />;
  }

  return <ProgressSteps status={status} />;
}
```

### Component: `Celebration.tsx`

```tsx
import confetti from 'canvas-confetti';

export function Celebration({ articlesPlanned }: { articlesPlanned: number }) {
  useEffect(() => {
    confetti({
      particleCount: 100,
      spread: 70,
      origin: { y: 0.6 }
    });
  }, []);

  return (
    <div className="flex flex-col items-center justify-center min-h-screen">
      <div className="text-6xl mb-6">🎉</div>
      <h1 className="text-3xl font-bold text-surface-900 dark:text-white mb-4">
        C'est prêt !
      </h1>
      <p className="text-lg text-surface-600 dark:text-surface-400">
        Votre Content Plan pour les 30 prochains jours est prêt
        avec <span className="font-semibold text-primary-600">{articlesPlanned} articles</span>
      </p>
    </div>
  );
}
```

### Tips rotatifs

```tsx
const TIPS = [
  "Nous analysons les données de Google pour trouver vos meilleures opportunités SEO",
  "Plus votre site a de données, meilleur sera le plan",
  "Les articles seront optimisés pour votre audience cible",
  "Chaque sujet est sélectionné pour son potentiel de trafic",
];

function RotatingTip() {
  const [index, setIndex] = useState(0);

  useEffect(() => {
    const interval = setInterval(() => {
      setIndex((i) => (i + 1) % TIPS.length);
    }, 5000);
    return () => clearInterval(interval);
  }, []);

  return (
    <p className="text-sm text-surface-500 dark:text-surface-400">
      💡 {TIPS[index]}
    </p>
  );
}
```

## Accès permanent au calendrier

Le calendrier sera accessible depuis :

1. **Dashboard** : Widget "Content Plan" avec mini-calendrier
2. **Site Show** : Onglet "Content Plan" avec calendrier complet
3. **Onboarding** : Page de génération puis révélation

### Navigation

```
/dashboard                    → Widget mini-calendrier par site
/sites/{id}                   → Onglet "Content Plan"
/sites/{id}/content-plan      → Page dédiée calendrier (optionnel)
/onboarding/generating/{id}   → Page de génération initiale
```

## Résumé des fichiers à créer

### Backend
- `database/migrations/xxxx_create_site_pages_table.php`
- `database/migrations/xxxx_create_scheduled_articles_table.php`
- `database/migrations/xxxx_create_content_plan_generations_table.php`
- `app/Models/SitePage.php`
- `app/Models/ScheduledArticle.php`
- `app/Models/ContentPlanGeneration.php`
- `app/Services/ContentPlan/ContentPlanGeneratorService.php`
- `app/Services/ContentPlan/ContentPlanService.php`
- `app/Services/ContentPlan/DuplicateCheckerService.php`
- `app/Services/Crawler/SiteCrawlerService.php`
- `app/Jobs/GenerateContentPlanJob.php`
- `app/Http/Controllers/Api/ContentPlanController.php`

### Frontend
- `resources/js/Pages/Onboarding/Generating.tsx`
- `resources/js/Components/ContentPlan/ProgressSteps.tsx`
- `resources/js/Components/ContentPlan/Celebration.tsx`
- `resources/js/Components/ContentPlan/CalendarReveal.tsx`
- `resources/js/Components/ContentPlan/ContentCalendar.tsx`

### Routes
- `GET /onboarding/generating/{site}` → Page React
- `GET /api/sites/{site}/generation-status` → Polling
- `GET /api/sites/{site}/content-plan` → Données calendrier
