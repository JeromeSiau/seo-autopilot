# Content Plan Auto-Generation Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Générer automatiquement un calendrier de contenu 30 jours après l'onboarding avec effet "waouh" (progressbar → célébration → calendrier).

**Architecture:** Job asynchrone qui orchestre plusieurs services (crawl, discovery, dedup, enrichment, planning). Le frontend poll un endpoint API pour afficher la progression en temps réel.

**Tech Stack:** Laravel 12, React 18, Inertia.js, TypeScript, canvas-confetti

---

## Phase 1: Database & Models

### Task 1: Create site_pages migration

**Files:**
- Create: `database/migrations/2025_12_21_000001_create_site_pages_table.php`

**Step 1: Create migration file**

```bash
php artisan make:migration create_site_pages_table
```

**Step 2: Write migration code**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
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
    }

    public function down(): void
    {
        Schema::dropIfExists('site_pages');
    }
};
```

**Step 3: Commit**

```bash
git add database/migrations/*_create_site_pages_table.php
git commit -m "feat: add site_pages migration"
```

---

### Task 2: Create scheduled_articles migration

**Files:**
- Create: `database/migrations/2025_12_21_000002_create_scheduled_articles_table.php`

**Step 1: Create migration file**

```bash
php artisan make:migration create_scheduled_articles_table
```

**Step 2: Write migration code**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
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
    }

    public function down(): void
    {
        Schema::dropIfExists('scheduled_articles');
    }
};
```

**Step 3: Commit**

```bash
git add database/migrations/*_create_scheduled_articles_table.php
git commit -m "feat: add scheduled_articles migration"
```

---

### Task 3: Create content_plan_generations migration

**Files:**
- Create: `database/migrations/2025_12_21_000003_create_content_plan_generations_table.php`

**Step 1: Create migration file**

```bash
php artisan make:migration create_content_plan_generations_table
```

**Step 2: Write migration code**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_plan_generations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->enum('status', ['pending', 'running', 'completed', 'failed'])->default('pending');
            $table->unsignedTinyInteger('current_step')->default(1);
            $table->unsignedTinyInteger('total_steps')->default(6);
            $table->json('steps')->nullable();
            $table->unsignedInteger('keywords_found')->default(0);
            $table->unsignedInteger('articles_planned')->default(0);
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['site_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_plan_generations');
    }
};
```

**Step 3: Commit**

```bash
git add database/migrations/*_create_content_plan_generations_table.php
git commit -m "feat: add content_plan_generations migration"
```

---

### Task 4: Run migrations

**Step 1: Run migrations**

```bash
php artisan migrate
```

**Step 2: Verify tables exist**

```bash
php artisan db:show --counts
```

---

### Task 5: Create SitePage model

**Files:**
- Create: `app/Models/SitePage.php`

**Step 1: Create model**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SitePage extends Model
{
    protected $fillable = [
        'site_id',
        'url',
        'title',
        'source',
        'last_seen_at',
    ];

    protected $casts = [
        'last_seen_at' => 'datetime',
    ];

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }
}
```

**Step 2: Commit**

```bash
git add app/Models/SitePage.php
git commit -m "feat: add SitePage model"
```

---

### Task 6: Create ScheduledArticle model

**Files:**
- Create: `app/Models/ScheduledArticle.php`

**Step 1: Create model**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScheduledArticle extends Model
{
    protected $fillable = [
        'site_id',
        'keyword_id',
        'scheduled_date',
        'status',
        'article_id',
    ];

    protected $casts = [
        'scheduled_date' => 'date',
    ];

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function keyword(): BelongsTo
    {
        return $this->belongsTo(Keyword::class);
    }

    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }

    public function scopePlanned($query)
    {
        return $query->where('status', 'planned');
    }

    public function scopeForDate($query, $date)
    {
        return $query->whereDate('scheduled_date', $date);
    }
}
```

**Step 2: Commit**

```bash
git add app/Models/ScheduledArticle.php
git commit -m "feat: add ScheduledArticle model"
```

---

### Task 7: Create ContentPlanGeneration model

**Files:**
- Create: `app/Models/ContentPlanGeneration.php`

**Step 1: Create model**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContentPlanGeneration extends Model
{
    protected $fillable = [
        'site_id',
        'status',
        'current_step',
        'total_steps',
        'steps',
        'keywords_found',
        'articles_planned',
        'error_message',
    ];

    protected $casts = [
        'steps' => 'array',
        'current_step' => 'integer',
        'total_steps' => 'integer',
        'keywords_found' => 'integer',
        'articles_planned' => 'integer',
    ];

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function isRunning(): bool
    {
        return $this->status === 'running';
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    public function markStepRunning(int $stepIndex): void
    {
        $steps = $this->steps;
        $steps[$stepIndex]['status'] = 'running';
        $steps[$stepIndex]['started_at'] = now()->toIso8601String();

        $this->update([
            'current_step' => $stepIndex + 1,
            'steps' => $steps,
        ]);
    }

    public function markStepCompleted(int $stepIndex): void
    {
        $steps = $this->steps;
        $steps[$stepIndex]['status'] = 'completed';
        $steps[$stepIndex]['completed_at'] = now()->toIso8601String();

        $this->update(['steps' => $steps]);
    }

    public function markCompleted(int $keywordsFound, int $articlesPlanned): void
    {
        $this->update([
            'status' => 'completed',
            'keywords_found' => $keywordsFound,
            'articles_planned' => $articlesPlanned,
        ]);
    }

    public function markFailed(string $errorMessage): void
    {
        $this->update([
            'status' => 'failed',
            'error_message' => $errorMessage,
        ]);
    }
}
```

**Step 2: Commit**

```bash
git add app/Models/ContentPlanGeneration.php
git commit -m "feat: add ContentPlanGeneration model"
```

---

### Task 8: Add relationships to Site model

**Files:**
- Modify: `app/Models/Site.php`

**Step 1: Add relationships**

Add these methods to `Site.php` after the existing relationships:

```php
public function pages(): HasMany
{
    return $this->hasMany(SitePage::class);
}

public function scheduledArticles(): HasMany
{
    return $this->hasMany(ScheduledArticle::class);
}

public function contentPlanGenerations(): HasMany
{
    return $this->hasMany(ContentPlanGeneration::class);
}

public function latestContentPlanGeneration(): HasOne
{
    return $this->hasOne(ContentPlanGeneration::class)->latestOfMany();
}
```

**Step 2: Add import at top**

```php
use Illuminate\Database\Eloquent\Relations\HasOne;
```

**Step 3: Commit**

```bash
git add app/Models/Site.php
git commit -m "feat: add content plan relationships to Site model"
```

---

## Phase 2: Backend Services

### Task 9: Create SiteCrawlerService

**Files:**
- Create: `app/Services/Crawler/SiteCrawlerService.php`

**Step 1: Create directory and file**

```bash
mkdir -p app/Services/Crawler
```

**Step 2: Write service**

```php
<?php

namespace App\Services\Crawler;

use App\Models\Site;
use App\Models\SitePage;
use App\Services\Google\SearchConsoleService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SiteCrawlerService
{
    public function __construct(
        private readonly SearchConsoleService $searchConsole,
    ) {}

    public function crawl(Site $site): int
    {
        $pagesFound = 0;

        // 1. Try sitemap
        $pagesFound += $this->crawlSitemap($site);

        // 2. Add GSC pages if connected
        if ($site->isGscConnected()) {
            $pagesFound += $this->importFromGSC($site);
        }

        // Update last_crawled_at
        $site->update(['last_crawled_at' => now()]);

        Log::info("Site crawl completed", [
            'site_id' => $site->id,
            'pages_found' => $pagesFound,
        ]);

        return $pagesFound;
    }

    private function crawlSitemap(Site $site): int
    {
        $sitemapUrls = [
            "https://{$site->domain}/sitemap.xml",
            "https://{$site->domain}/sitemap_index.xml",
            "https://www.{$site->domain}/sitemap.xml",
        ];

        foreach ($sitemapUrls as $sitemapUrl) {
            try {
                $response = Http::timeout(10)->get($sitemapUrl);

                if (!$response->successful()) {
                    continue;
                }

                $xml = @simplexml_load_string($response->body());
                if (!$xml) {
                    continue;
                }

                return $this->parseSitemap($site, $xml);
            } catch (\Exception $e) {
                Log::debug("Sitemap fetch failed: {$sitemapUrl}", ['error' => $e->getMessage()]);
                continue;
            }
        }

        Log::info("No sitemap found for {$site->domain}");
        return 0;
    }

    private function parseSitemap(Site $site, \SimpleXMLElement $xml): int
    {
        $count = 0;

        // Handle sitemap index (contains other sitemaps)
        if (isset($xml->sitemap)) {
            foreach ($xml->sitemap as $sitemap) {
                $sitemapUrl = (string) $sitemap->loc;
                try {
                    $response = Http::timeout(10)->get($sitemapUrl);
                    if ($response->successful()) {
                        $childXml = @simplexml_load_string($response->body());
                        if ($childXml) {
                            $count += $this->parseSitemap($site, $childXml);
                        }
                    }
                } catch (\Exception $e) {
                    continue;
                }
            }
            return $count;
        }

        // Handle regular sitemap with URLs
        foreach ($xml->url as $url) {
            $pageUrl = (string) $url->loc;

            SitePage::updateOrCreate(
                ['site_id' => $site->id, 'url' => $pageUrl],
                ['source' => 'sitemap', 'last_seen_at' => now()]
            );
            $count++;

            // Limit to avoid memory issues
            if ($count >= 500) {
                break;
            }
        }

        return $count;
    }

    private function importFromGSC(Site $site): int
    {
        try {
            $pages = $this->searchConsole->getTopPages($site, 28, 100);
            $count = 0;

            foreach ($pages as $page) {
                SitePage::updateOrCreate(
                    ['site_id' => $site->id, 'url' => $page->url],
                    ['source' => 'gsc', 'last_seen_at' => now()]
                );
                $count++;
            }

            return $count;
        } catch (\Exception $e) {
            Log::warning("GSC import failed for site {$site->id}", ['error' => $e->getMessage()]);
            return 0;
        }
    }

    public function extractTitlesForPages(Site $site, int $limit = 50): int
    {
        $pages = $site->pages()
            ->whereNull('title')
            ->limit($limit)
            ->get();

        $updated = 0;

        foreach ($pages as $page) {
            $title = $this->extractTitle($page->url);
            if ($title) {
                $page->update(['title' => $title]);
                $updated++;
            }
        }

        return $updated;
    }

    private function extractTitle(string $url): ?string
    {
        try {
            $response = Http::timeout(5)
                ->withHeaders(['User-Agent' => 'Mozilla/5.0 (compatible; SEOAutopilot/1.0)'])
                ->get($url);

            if (!$response->successful()) {
                return null;
            }

            if (preg_match('/<title[^>]*>(.+?)<\/title>/is', $response->body(), $matches)) {
                $title = html_entity_decode(trim($matches[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
                // Clean up common suffixes
                $title = preg_replace('/\s*[-|–]\s*[^-|–]+$/', '', $title);
                return mb_substr($title, 0, 255);
            }
        } catch (\Exception $e) {
            // Ignore failures
        }

        return null;
    }
}
```

**Step 3: Commit**

```bash
git add app/Services/Crawler/SiteCrawlerService.php
git commit -m "feat: add SiteCrawlerService for sitemap and GSC crawling"
```

---

### Task 10: Create DuplicateCheckerService

**Files:**
- Create: `app/Services/ContentPlan/DuplicateCheckerService.php`

**Step 1: Create directory and file**

```bash
mkdir -p app/Services/ContentPlan
```

**Step 2: Write service**

```php
<?php

namespace App\Services\ContentPlan;

use App\Services\LLM\LLMManager;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class DuplicateCheckerService
{
    public function __construct(
        private readonly LLMManager $llm,
    ) {}

    public function filterDuplicates(Collection $keywords, Collection $sitePages): Collection
    {
        if ($sitePages->isEmpty() || $keywords->isEmpty()) {
            return $keywords;
        }

        // Get titles from site pages
        $existingTitles = $sitePages
            ->filter(fn($title) => !empty($title))
            ->values()
            ->take(100) // Limit to avoid token limits
            ->toArray();

        if (empty($existingTitles)) {
            return $keywords;
        }

        $keywordList = $keywords->pluck('keyword')->take(50)->toArray();

        $prompt = $this->buildPrompt($keywordList, $existingTitles);

        try {
            $response = $this->llm->completeJson('openai', $prompt, [], [
                'model' => 'gpt-4o-mini',
                'temperature' => 0.1,
            ]);

            $results = $response->getJson() ?? [];

            Log::info("Duplicate check completed", [
                'keywords_checked' => count($keywordList),
                'results' => $results,
            ]);

            return $keywords->filter(function ($kw) use ($results) {
                $keyword = $kw['keyword'] ?? '';
                $status = $results[$keyword] ?? 'new';
                return $status !== 'covered';
            });
        } catch (\Exception $e) {
            Log::warning("Duplicate check failed, returning all keywords", [
                'error' => $e->getMessage(),
            ]);
            return $keywords;
        }
    }

    private function buildPrompt(array $keywords, array $existingTitles): string
    {
        $keywordList = implode("\n- ", $keywords);
        $titleList = implode("\n- ", $existingTitles);

        return <<<PROMPT
Tu es un expert SEO. Analyse ces mots-clés candidats et détermine s'ils sont déjà couverts par les articles existants.

**Mots-clés candidats:**
- {$keywordList}

**Articles existants sur le site:**
- {$titleList}

Pour chaque mot-clé, indique:
- "new" = sujet pas encore couvert, bon candidat pour un nouvel article
- "covered" = un article existant couvre déjà ce sujet (même si le titre est différent)
- "partial" = partiellement couvert, pourrait être un angle complémentaire

Sois strict: si un article existant traite du même sujet principal, marque "covered".

Réponds UNIQUEMENT avec un objet JSON valide, format: {"mot-clé": "status", ...}
PROMPT;
    }
}
```

**Step 3: Commit**

```bash
git add app/Services/ContentPlan/DuplicateCheckerService.php
git commit -m "feat: add DuplicateCheckerService for LLM-based deduplication"
```

---

### Task 11: Create ContentPlanService

**Files:**
- Create: `app/Services/ContentPlan/ContentPlanService.php`

**Step 1: Write service**

```php
<?php

namespace App\Services\ContentPlan;

use App\Models\Keyword;
use App\Models\ScheduledArticle;
use App\Models\Site;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class ContentPlanService
{
    public function createPlan(Site $site, Collection $keywords, int $days = 30): Collection
    {
        $settings = $site->settings;
        $publishDays = $settings?->publish_days ?? ['mon', 'wed', 'fri'];
        $articlesPerWeek = $settings?->articles_per_week ?? 3;

        // Sort keywords by score descending
        $sortedKeywords = $keywords->sortByDesc('score')->values();

        // Get publish dates for next N days
        $publishDates = $this->getPublishDates($publishDays, $days, $articlesPerWeek);

        Log::info("Creating content plan", [
            'site_id' => $site->id,
            'keywords_available' => $sortedKeywords->count(),
            'publish_dates' => count($publishDates),
        ]);

        // Clear existing planned articles (not generating/ready/published)
        $site->scheduledArticles()->where('status', 'planned')->delete();

        // Create scheduled articles
        $scheduled = collect();

        foreach ($publishDates as $index => $date) {
            if (!isset($sortedKeywords[$index])) {
                break;
            }

            $keywordData = $sortedKeywords[$index];

            // Save keyword if not already in DB
            $keyword = Keyword::firstOrCreate(
                ['site_id' => $site->id, 'keyword' => $keywordData['keyword']],
                [
                    'volume' => $keywordData['volume'] ?? null,
                    'difficulty' => $keywordData['difficulty'] ?? null,
                    'score' => $keywordData['score'] ?? 0,
                    'source' => $keywordData['source'] ?? 'ai_generated',
                    'status' => 'queued',
                ]
            );

            // Create scheduled article
            $scheduledArticle = ScheduledArticle::create([
                'site_id' => $site->id,
                'keyword_id' => $keyword->id,
                'scheduled_date' => $date,
                'status' => 'planned',
            ]);

            $scheduled->push($scheduledArticle);
        }

        Log::info("Content plan created", [
            'site_id' => $site->id,
            'articles_scheduled' => $scheduled->count(),
        ]);

        return $scheduled;
    }

    public function getPublishDates(array $publishDays, int $days, int $maxPerWeek): array
    {
        $dates = [];
        $currentDate = now()->addDay()->startOfDay();
        $endDate = now()->addDays($days)->endOfDay();
        $weekCount = [];

        while ($currentDate <= $endDate) {
            $dayName = strtolower($currentDate->format('D'));
            $weekKey = $currentDate->format('Y-W');

            if (in_array($dayName, $publishDays)) {
                $weekCount[$weekKey] = ($weekCount[$weekKey] ?? 0) + 1;

                if ($weekCount[$weekKey] <= $maxPerWeek) {
                    $dates[] = $currentDate->toDateString();
                }
            }

            $currentDate = $currentDate->addDay();
        }

        return $dates;
    }

    public function getCalendarData(Site $site, string $month): array
    {
        $startDate = \Carbon\Carbon::createFromFormat('Y-m', $month)->startOfMonth();
        $endDate = $startDate->copy()->endOfMonth();

        $scheduled = $site->scheduledArticles()
            ->with('keyword', 'article')
            ->whereBetween('scheduled_date', [$startDate, $endDate])
            ->orderBy('scheduled_date')
            ->get();

        return $scheduled->map(fn(ScheduledArticle $s) => [
            'id' => $s->id,
            'date' => $s->scheduled_date->toDateString(),
            'keyword' => $s->keyword->keyword,
            'volume' => $s->keyword->volume,
            'difficulty' => $s->keyword->difficulty,
            'score' => $s->keyword->score,
            'status' => $s->status,
            'article_id' => $s->article_id,
        ])->toArray();
    }
}
```

**Step 2: Commit**

```bash
git add app/Services/ContentPlan/ContentPlanService.php
git commit -m "feat: add ContentPlanService for calendar scheduling"
```

---

### Task 12: Create ContentPlanGeneratorService

**Files:**
- Create: `app/Services/ContentPlan/ContentPlanGeneratorService.php`

**Step 1: Write service**

```php
<?php

namespace App\Services\ContentPlan;

use App\Models\ContentPlanGeneration;
use App\Models\Site;
use App\Services\Crawler\SiteCrawlerService;
use App\Services\Keyword\KeywordDiscoveryService;
use App\Services\Keyword\KeywordScoringService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class ContentPlanGeneratorService
{
    public function __construct(
        private readonly SiteCrawlerService $crawler,
        private readonly KeywordDiscoveryService $keywordDiscovery,
        private readonly KeywordScoringService $keywordScoring,
        private readonly DuplicateCheckerService $duplicateChecker,
        private readonly ContentPlanService $planService,
    ) {}

    public function generate(Site $site): ContentPlanGeneration
    {
        Log::info("Starting content plan generation", ['site_id' => $site->id]);

        // Build dynamic steps based on site config
        $steps = $this->buildSteps($site);

        // Create tracking record
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

            // Step 1: Crawl site (always)
            $generation->markStepRunning($stepIndex);
            $pagesFound = $this->crawler->crawl($site);
            $this->crawler->extractTitlesForPages($site, 30);
            $generation->markStepCompleted($stepIndex);
            $stepIndex++;

            // Step 2: Analyze GSC (if connected)
            if ($site->isGscConnected()) {
                $generation->markStepRunning($stepIndex);
                $gscKeywords = $this->keywordDiscovery->mineFromSearchConsole($site);
                $keywords = $keywords->merge($gscKeywords);
                $generation->markStepCompleted($stepIndex);
                $stepIndex++;
            }

            // Step 3: Generate AI ideas (if business description OR no keywords yet)
            if ($site->business_description || $keywords->isEmpty()) {
                $generation->markStepRunning($stepIndex);
                $aiKeywords = $this->keywordDiscovery->generateTopicIdeas($site, [
                    'count' => 50,
                    'existing_keywords' => $keywords->pluck('keyword')->toArray(),
                ]);
                $keywords = $keywords->merge($aiKeywords);
                $generation->markStepCompleted($stepIndex);
                $stepIndex++;
            }

            // Step 4: Check duplicates (always)
            $generation->markStepRunning($stepIndex);
            $sitePages = $site->pages()->pluck('title', 'url');
            $keywords = $this->duplicateChecker->filterDuplicates($keywords, $sitePages);
            $generation->markStepCompleted($stepIndex);
            $stepIndex++;

            // Step 5: Enrich with SEO data (always)
            $generation->markStepRunning($stepIndex);
            $keywords = $this->keywordDiscovery->enrichWithSeoData($keywords, $site->language ?? 'en');
            // Calculate scores
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

            // Step 6: Create plan (always)
            $generation->markStepRunning($stepIndex);
            $scheduled = $this->planService->createPlan($site, $keywords);
            $generation->markStepCompleted($stepIndex);

            // Mark completed
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

    private function buildSteps(Site $site): array
    {
        $steps = [];

        // Step 1: Always crawl site
        $steps[] = [
            'name' => 'Analyse de votre site existant',
            'status' => 'pending',
            'icon' => 'globe',
        ];

        // Step 2: GSC analysis (if connected)
        if ($site->isGscConnected()) {
            $steps[] = [
                'name' => 'Analyse de vos performances',
                'status' => 'pending',
                'icon' => 'chart',
            ];
        }

        // Step 3: AI generation (if has description)
        if ($site->business_description) {
            $steps[] = [
                'name' => "Génération d'idées avec l'IA",
                'status' => 'pending',
                'icon' => 'sparkles',
            ];
        }

        // Step 4: Always check duplicates
        $steps[] = [
            'name' => 'Vérification des sujets existants',
            'status' => 'pending',
            'icon' => 'search',
        ];

        // Step 5: Always enrich
        $steps[] = [
            'name' => 'Analyse du potentiel de chaque sujet',
            'status' => 'pending',
            'icon' => 'trending',
        ];

        // Step 6: Always create plan
        $steps[] = [
            'name' => 'Création de votre Content Plan',
            'status' => 'pending',
            'icon' => 'calendar',
        ];

        return $steps;
    }
}
```

**Step 2: Commit**

```bash
git add app/Services/ContentPlan/ContentPlanGeneratorService.php
git commit -m "feat: add ContentPlanGeneratorService orchestrating the full pipeline"
```

---

### Task 13: Create GenerateContentPlanJob

**Files:**
- Create: `app/Jobs/GenerateContentPlanJob.php`

**Step 1: Write job**

```php
<?php

namespace App\Jobs;

use App\Models\Site;
use App\Services\ContentPlan\ContentPlanGeneratorService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class GenerateContentPlanJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 300; // 5 minutes max

    public function __construct(
        public Site $site
    ) {}

    public function handle(ContentPlanGeneratorService $generator): void
    {
        $generator->generate($this->site);
    }
}
```

**Step 2: Commit**

```bash
git add app/Jobs/GenerateContentPlanJob.php
git commit -m "feat: add GenerateContentPlanJob"
```

---

## Phase 3: API & Routes

### Task 14: Create ContentPlanController

**Files:**
- Create: `app/Http/Controllers/Api/ContentPlanController.php`

**Step 1: Write controller**

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Site;
use App\Services\ContentPlan\ContentPlanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ContentPlanController extends Controller
{
    public function __construct(
        private readonly ContentPlanService $planService,
    ) {}

    public function generationStatus(Site $site): JsonResponse
    {
        // Ensure user owns this site
        if ($site->team_id !== auth()->user()->team_id) {
            abort(403);
        }

        $generation = $site->contentPlanGenerations()
            ->latest()
            ->first();

        if (!$generation) {
            return response()->json([
                'status' => 'not_started',
            ]);
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

    public function contentPlan(Site $site, Request $request): JsonResponse
    {
        // Ensure user owns this site
        if ($site->team_id !== auth()->user()->team_id) {
            abort(403);
        }

        $month = $request->get('month', now()->format('Y-m'));

        $articles = $this->planService->getCalendarData($site, $month);

        return response()->json([
            'month' => $month,
            'articles' => $articles,
        ]);
    }
}
```

**Step 2: Commit**

```bash
git add app/Http/Controllers/Api/ContentPlanController.php
git commit -m "feat: add ContentPlanController API endpoints"
```

---

### Task 15: Add API routes

**Files:**
- Modify: `routes/api.php`

**Step 1: Add routes**

Add these lines inside the `auth:sanctum` middleware group:

```php
// Content Plan
Route::get('sites/{site}/generation-status', [\App\Http\Controllers\Api\ContentPlanController::class, 'generationStatus']);
Route::get('sites/{site}/content-plan', [\App\Http\Controllers\Api\ContentPlanController::class, 'contentPlan']);
```

**Step 2: Commit**

```bash
git add routes/api.php
git commit -m "feat: add content plan API routes"
```

---

### Task 16: Add web route for generating page

**Files:**
- Modify: `routes/web.php`

**Step 1: Add route**

Add this inside the `auth, verified` middleware group, after the onboarding routes:

```php
// Content Plan Generation (after onboarding)
Route::get('/onboarding/generating/{site}', [OnboardingController::class, 'generating'])->name('onboarding.generating');
```

**Step 2: Commit**

```bash
git add routes/web.php
git commit -m "feat: add generating page web route"
```

---

### Task 17: Update OnboardingController

**Files:**
- Modify: `app/Http/Controllers/Web/OnboardingController.php`

**Step 1: Add generating method**

Add this method to the controller:

```php
public function generating(Site $site)
{
    // Ensure user owns this site
    if ($site->team_id !== auth()->user()->team_id) {
        abort(403);
    }

    return Inertia::render('Onboarding/Generating', [
        'site' => $site->only(['id', 'name', 'domain']),
    ]);
}
```

**Step 2: Update complete method**

Replace the `complete` method:

```php
public function complete(Site $site)
{
    // Ensure user owns this site
    if ($site->team_id !== auth()->user()->team_id) {
        abort(403);
    }

    $site->update(['onboarding_completed_at' => now()]);

    // Enable autopilot
    SiteSetting::updateOrCreate(
        ['site_id' => $site->id],
        ['autopilot_enabled' => true]
    );

    // Dispatch content plan generation job
    \App\Jobs\GenerateContentPlanJob::dispatch($site);

    // Redirect to generating page
    return redirect()->route('onboarding.generating', $site);
}
```

**Step 3: Commit**

```bash
git add app/Http/Controllers/Web/OnboardingController.php
git commit -m "feat: update OnboardingController to dispatch content plan job"
```

---

## Phase 4: Frontend Components

### Task 18: Install canvas-confetti

**Step 1: Install package**

```bash
npm install canvas-confetti
npm install --save-dev @types/canvas-confetti
```

**Step 2: Commit**

```bash
git add package.json package-lock.json
git commit -m "chore: add canvas-confetti package"
```

---

### Task 19: Create ProgressSteps component

**Files:**
- Create: `resources/js/Components/ContentPlan/ProgressSteps.tsx`

**Step 1: Create directory**

```bash
mkdir -p resources/js/Components/ContentPlan
```

**Step 2: Write component**

```tsx
import { Check, Loader2, Circle, Globe, BarChart3, Sparkles, Search, TrendingUp, Calendar } from 'lucide-react';
import clsx from 'clsx';

interface Step {
    name: string;
    status: 'pending' | 'running' | 'completed';
    icon?: string;
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

interface Props {
    status: GenerationStatus | null;
}

const ICONS: Record<string, React.ComponentType<{ className?: string }>> = {
    globe: Globe,
    chart: BarChart3,
    sparkles: Sparkles,
    search: Search,
    trending: TrendingUp,
    calendar: Calendar,
};

const TIPS = [
    "Nous analysons les données de Google pour trouver vos meilleures opportunités SEO",
    "Plus votre site a de données, meilleur sera le plan",
    "Les articles seront optimisés pour votre audience cible",
    "Chaque sujet est sélectionné pour son potentiel de trafic",
    "Notre IA évite les sujets que vous avez déjà traités",
];

export default function ProgressSteps({ status }: Props) {
    const [tipIndex, setTipIndex] = useState(0);

    useEffect(() => {
        const interval = setInterval(() => {
            setTipIndex((i) => (i + 1) % TIPS.length);
        }, 5000);
        return () => clearInterval(interval);
    }, []);

    if (!status) {
        return (
            <div className="flex items-center justify-center min-h-[400px]">
                <Loader2 className="h-8 w-8 animate-spin text-primary-500" />
            </div>
        );
    }

    const progress = status.steps
        ? (status.steps.filter(s => s.status === 'completed').length / status.steps.length) * 100
        : 0;

    return (
        <div className="space-y-8">
            {/* Header */}
            <div className="text-center">
                <div className="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-primary-100 dark:bg-primary-500/20 mb-4">
                    <Sparkles className="h-8 w-8 text-primary-600 dark:text-primary-400" />
                </div>
                <h1 className="text-2xl font-bold text-surface-900 dark:text-white">
                    Création de votre Content Plan
                </h1>
                <p className="mt-2 text-surface-500 dark:text-surface-400">
                    Nous analysons votre site et préparons votre calendrier de contenu
                </p>
            </div>

            {/* Steps */}
            <div className="max-w-md mx-auto space-y-3">
                {status.steps?.map((step, index) => {
                    const IconComponent = ICONS[step.icon || 'circle'] || Circle;

                    return (
                        <div
                            key={index}
                            className={clsx(
                                'flex items-center gap-4 p-4 rounded-xl transition-all',
                                step.status === 'completed' && 'bg-primary-50 dark:bg-primary-500/10',
                                step.status === 'running' && 'bg-primary-100 dark:bg-primary-500/20 ring-2 ring-primary-500',
                                step.status === 'pending' && 'bg-surface-50 dark:bg-surface-800/50'
                            )}
                        >
                            {/* Icon */}
                            <div className={clsx(
                                'flex-shrink-0 w-10 h-10 rounded-full flex items-center justify-center',
                                step.status === 'completed' && 'bg-primary-500 text-white',
                                step.status === 'running' && 'bg-primary-500 text-white',
                                step.status === 'pending' && 'bg-surface-200 dark:bg-surface-700 text-surface-400'
                            )}>
                                {step.status === 'completed' ? (
                                    <Check className="h-5 w-5" />
                                ) : step.status === 'running' ? (
                                    <Loader2 className="h-5 w-5 animate-spin" />
                                ) : (
                                    <IconComponent className="h-5 w-5" />
                                )}
                            </div>

                            {/* Text */}
                            <span className={clsx(
                                'font-medium',
                                step.status === 'completed' && 'text-primary-700 dark:text-primary-400',
                                step.status === 'running' && 'text-primary-900 dark:text-primary-300',
                                step.status === 'pending' && 'text-surface-400 dark:text-surface-500'
                            )}>
                                {step.name}
                                {step.status === 'running' && '...'}
                            </span>
                        </div>
                    );
                })}
            </div>

            {/* Progress bar */}
            <div className="max-w-md mx-auto">
                <div className="h-2 bg-surface-200 dark:bg-surface-700 rounded-full overflow-hidden">
                    <div
                        className="h-full bg-primary-500 rounded-full transition-all duration-500"
                        style={{ width: `${progress}%` }}
                    />
                </div>
                <p className="mt-2 text-center text-sm text-surface-500 dark:text-surface-400">
                    {Math.round(progress)}% complété
                </p>
            </div>

            {/* Rotating tip */}
            <div className="max-w-md mx-auto text-center">
                <p className="text-sm text-surface-500 dark:text-surface-400 transition-opacity duration-300">
                    💡 {TIPS[tipIndex]}
                </p>
            </div>
        </div>
    );
}

import { useState, useEffect } from 'react';
```

**Step 3: Fix imports order at top of file**

Move the `import { useState, useEffect }` line to the top.

**Step 4: Commit**

```bash
git add resources/js/Components/ContentPlan/ProgressSteps.tsx
git commit -m "feat: add ProgressSteps component"
```

---

### Task 20: Create Celebration component

**Files:**
- Create: `resources/js/Components/ContentPlan/Celebration.tsx`

**Step 1: Write component**

```tsx
import { useEffect } from 'react';
import confetti from 'canvas-confetti';
import { PartyPopper } from 'lucide-react';

interface Props {
    articlesPlanned: number;
}

export default function Celebration({ articlesPlanned }: Props) {
    useEffect(() => {
        // Fire confetti
        const duration = 2000;
        const end = Date.now() + duration;

        const frame = () => {
            confetti({
                particleCount: 3,
                angle: 60,
                spread: 55,
                origin: { x: 0 },
                colors: ['#10b981', '#34d399', '#6ee7b7'],
            });
            confetti({
                particleCount: 3,
                angle: 120,
                spread: 55,
                origin: { x: 1 },
                colors: ['#10b981', '#34d399', '#6ee7b7'],
            });

            if (Date.now() < end) {
                requestAnimationFrame(frame);
            }
        };

        frame();
    }, []);

    return (
        <div className="flex flex-col items-center justify-center min-h-[500px] text-center">
            <div className="relative">
                <div className="absolute inset-0 animate-ping rounded-full bg-primary-400/30" />
                <div className="relative inline-flex items-center justify-center w-24 h-24 rounded-full bg-gradient-to-br from-primary-400 to-primary-600">
                    <PartyPopper className="h-12 w-12 text-white" />
                </div>
            </div>

            <h1 className="mt-8 text-4xl font-bold text-surface-900 dark:text-white">
                C'est prêt !
            </h1>

            <p className="mt-4 text-xl text-surface-600 dark:text-surface-400 max-w-md">
                Votre Content Plan pour les 30 prochains jours est prêt avec{' '}
                <span className="font-semibold text-primary-600 dark:text-primary-400">
                    {articlesPlanned} articles
                </span>
            </p>
        </div>
    );
}
```

**Step 2: Commit**

```bash
git add resources/js/Components/ContentPlan/Celebration.tsx
git commit -m "feat: add Celebration component with confetti"
```

---

### Task 21: Create ContentCalendar component

**Files:**
- Create: `resources/js/Components/ContentPlan/ContentCalendar.tsx`

**Step 1: Write component**

```tsx
import { useState, useEffect } from 'react';
import { ChevronLeft, ChevronRight, FileText } from 'lucide-react';
import clsx from 'clsx';

interface ScheduledArticle {
    id: number;
    date: string;
    keyword: string;
    volume: number | null;
    difficulty: number | null;
    score: number;
    status: 'planned' | 'generating' | 'ready' | 'published' | 'skipped';
    article_id: number | null;
}

interface Props {
    siteId: number;
    initialMonth?: string;
}

export default function ContentCalendar({ siteId, initialMonth }: Props) {
    const [month, setMonth] = useState(initialMonth || new Date().toISOString().slice(0, 7));
    const [articles, setArticles] = useState<ScheduledArticle[]>([]);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        fetchCalendar();
    }, [month]);

    const fetchCalendar = async () => {
        setLoading(true);
        try {
            const res = await fetch(`/api/sites/${siteId}/content-plan?month=${month}`);
            const data = await res.json();
            setArticles(data.articles || []);
        } catch (e) {
            console.error('Failed to fetch calendar', e);
        }
        setLoading(false);
    };

    const prevMonth = () => {
        const d = new Date(month + '-01');
        d.setMonth(d.getMonth() - 1);
        setMonth(d.toISOString().slice(0, 7));
    };

    const nextMonth = () => {
        const d = new Date(month + '-01');
        d.setMonth(d.getMonth() + 1);
        setMonth(d.toISOString().slice(0, 7));
    };

    // Generate calendar grid
    const firstDay = new Date(month + '-01');
    const lastDay = new Date(firstDay.getFullYear(), firstDay.getMonth() + 1, 0);
    const startPadding = firstDay.getDay() === 0 ? 6 : firstDay.getDay() - 1; // Monday start
    const totalDays = lastDay.getDate();

    const days = [];
    for (let i = 0; i < startPadding; i++) {
        days.push(null);
    }
    for (let i = 1; i <= totalDays; i++) {
        days.push(i);
    }

    const getArticleForDay = (day: number | null) => {
        if (!day) return null;
        const dateStr = `${month}-${String(day).padStart(2, '0')}`;
        return articles.find(a => a.date === dateStr);
    };

    const monthName = new Intl.DateTimeFormat('fr-FR', { month: 'long', year: 'numeric' }).format(firstDay);

    return (
        <div className="bg-white dark:bg-surface-900/50 rounded-2xl border border-surface-200 dark:border-surface-800 overflow-hidden">
            {/* Header */}
            <div className="flex items-center justify-between p-4 border-b border-surface-200 dark:border-surface-800">
                <button
                    onClick={prevMonth}
                    className="p-2 hover:bg-surface-100 dark:hover:bg-surface-800 rounded-lg transition-colors"
                >
                    <ChevronLeft className="h-5 w-5" />
                </button>
                <h3 className="font-semibold text-lg capitalize text-surface-900 dark:text-white">
                    {monthName}
                </h3>
                <button
                    onClick={nextMonth}
                    className="p-2 hover:bg-surface-100 dark:hover:bg-surface-800 rounded-lg transition-colors"
                >
                    <ChevronRight className="h-5 w-5" />
                </button>
            </div>

            {/* Day headers */}
            <div className="grid grid-cols-7 border-b border-surface-200 dark:border-surface-800">
                {['Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam', 'Dim'].map(day => (
                    <div key={day} className="p-2 text-center text-xs font-medium text-surface-500 dark:text-surface-400">
                        {day}
                    </div>
                ))}
            </div>

            {/* Calendar grid */}
            <div className="grid grid-cols-7">
                {days.map((day, index) => {
                    const article = getArticleForDay(day);
                    const isToday = day && new Date().toISOString().slice(0, 10) === `${month}-${String(day).padStart(2, '0')}`;

                    return (
                        <div
                            key={index}
                            className={clsx(
                                'min-h-[100px] p-2 border-b border-r border-surface-100 dark:border-surface-800',
                                !day && 'bg-surface-50 dark:bg-surface-900',
                            )}
                        >
                            {day && (
                                <>
                                    <span className={clsx(
                                        'inline-flex items-center justify-center w-7 h-7 rounded-full text-sm',
                                        isToday && 'bg-primary-500 text-white font-semibold',
                                        !isToday && 'text-surface-600 dark:text-surface-400'
                                    )}>
                                        {day}
                                    </span>

                                    {article && (
                                        <div className={clsx(
                                            'mt-1 p-2 rounded-lg text-xs',
                                            article.status === 'planned' && 'bg-blue-50 dark:bg-blue-500/10 border border-blue-200 dark:border-blue-500/20',
                                            article.status === 'generating' && 'bg-yellow-50 dark:bg-yellow-500/10 border border-yellow-200 dark:border-yellow-500/20',
                                            article.status === 'ready' && 'bg-green-50 dark:bg-green-500/10 border border-green-200 dark:border-green-500/20',
                                            article.status === 'published' && 'bg-primary-50 dark:bg-primary-500/10 border border-primary-200 dark:border-primary-500/20',
                                        )}>
                                            <p className="font-medium text-surface-700 dark:text-surface-300 line-clamp-2">
                                                {article.keyword}
                                            </p>
                                            {article.volume && (
                                                <p className="mt-1 text-surface-500 dark:text-surface-400">
                                                    Vol: {article.volume.toLocaleString()}
                                                </p>
                                            )}
                                        </div>
                                    )}
                                </>
                            )}
                        </div>
                    );
                })}
            </div>
        </div>
    );
}
```

**Step 2: Commit**

```bash
git add resources/js/Components/ContentPlan/ContentCalendar.tsx
git commit -m "feat: add ContentCalendar component"
```

---

### Task 22: Create CalendarReveal component

**Files:**
- Create: `resources/js/Components/ContentPlan/CalendarReveal.tsx`

**Step 1: Write component**

```tsx
import { Link } from '@inertiajs/react';
import { LayoutDashboard, Settings } from 'lucide-react';
import ContentCalendar from './ContentCalendar';

interface Site {
    id: number;
    name: string;
    domain: string;
}

interface Props {
    site: Site;
    articlesPlanned: number;
}

export default function CalendarReveal({ site, articlesPlanned }: Props) {
    return (
        <div className="space-y-8 animate-in fade-in duration-500">
            {/* Header */}
            <div className="text-center">
                <h1 className="text-2xl font-bold text-surface-900 dark:text-white">
                    Votre Content Plan est prêt !
                </h1>
                <p className="mt-2 text-surface-500 dark:text-surface-400">
                    {articlesPlanned} articles planifiés pour les 30 prochains jours
                </p>
            </div>

            {/* Calendar */}
            <ContentCalendar siteId={site.id} />

            {/* Actions */}
            <div className="flex flex-col sm:flex-row items-center justify-center gap-4">
                <Link
                    href={route('dashboard')}
                    className="inline-flex items-center gap-2 px-6 py-3 bg-primary-500 text-white font-semibold rounded-xl hover:bg-primary-600 transition-colors"
                >
                    <LayoutDashboard className="h-5 w-5" />
                    Voir le Dashboard
                </Link>
                <Link
                    href={route('sites.show', site.id)}
                    className="inline-flex items-center gap-2 px-6 py-3 bg-surface-100 dark:bg-surface-800 text-surface-700 dark:text-surface-300 font-semibold rounded-xl hover:bg-surface-200 dark:hover:bg-surface-700 transition-colors"
                >
                    <Settings className="h-5 w-5" />
                    Gérer le site
                </Link>
            </div>
        </div>
    );
}
```

**Step 2: Commit**

```bash
git add resources/js/Components/ContentPlan/CalendarReveal.tsx
git commit -m "feat: add CalendarReveal component"
```

---

### Task 23: Create Generating page

**Files:**
- Create: `resources/js/Pages/Onboarding/Generating.tsx`

**Step 1: Write page**

```tsx
import { useState, useEffect } from 'react';
import { Head } from '@inertiajs/react';
import ProgressSteps from '@/Components/ContentPlan/ProgressSteps';
import Celebration from '@/Components/ContentPlan/Celebration';
import CalendarReveal from '@/Components/ContentPlan/CalendarReveal';

interface Site {
    id: number;
    name: string;
    domain: string;
}

interface Step {
    name: string;
    status: 'pending' | 'running' | 'completed';
    icon?: string;
}

interface GenerationStatus {
    status: 'not_started' | 'pending' | 'running' | 'completed' | 'failed';
    current_step: number;
    total_steps: number;
    steps: Step[];
    keywords_found: number;
    articles_planned: number;
    error_message?: string;
}

interface Props {
    site: Site;
}

type Phase = 'progress' | 'celebration' | 'calendar';

export default function Generating({ site }: Props) {
    const [status, setStatus] = useState<GenerationStatus | null>(null);
    const [phase, setPhase] = useState<Phase>('progress');

    // Polling
    useEffect(() => {
        const fetchStatus = async () => {
            try {
                const res = await fetch(`/api/sites/${site.id}/generation-status`);
                const data = await res.json();
                setStatus(data);

                if (data.status === 'completed' && phase === 'progress') {
                    // Start celebration sequence
                    setTimeout(() => setPhase('celebration'), 500);
                    setTimeout(() => setPhase('calendar'), 3500);
                }
            } catch (e) {
                console.error('Failed to fetch status', e);
            }
        };

        // Initial fetch
        fetchStatus();

        // Poll every 2 seconds while in progress
        const interval = setInterval(() => {
            if (phase === 'progress') {
                fetchStatus();
            }
        }, 2000);

        return () => clearInterval(interval);
    }, [site.id, phase]);

    return (
        <div className="min-h-screen bg-surface-50 dark:bg-surface-900 transition-colors">
            <Head title="Création du Content Plan" />

            <div className="max-w-3xl mx-auto px-4 py-12">
                {phase === 'progress' && (
                    <ProgressSteps status={status} />
                )}

                {phase === 'celebration' && (
                    <Celebration articlesPlanned={status?.articles_planned || 0} />
                )}

                {phase === 'calendar' && (
                    <CalendarReveal
                        site={site}
                        articlesPlanned={status?.articles_planned || 0}
                    />
                )}

                {/* Error state */}
                {status?.status === 'failed' && (
                    <div className="mt-8 p-4 bg-red-50 dark:bg-red-500/10 border border-red-200 dark:border-red-500/20 rounded-xl">
                        <p className="text-red-700 dark:text-red-400">
                            Une erreur est survenue : {status.error_message}
                        </p>
                    </div>
                )}
            </div>
        </div>
    );
}
```

**Step 2: Commit**

```bash
git add resources/js/Pages/Onboarding/Generating.tsx
git commit -m "feat: add Generating page with full flow"
```

---

### Task 24: Export components from index

**Files:**
- Create: `resources/js/Components/ContentPlan/index.ts`

**Step 1: Create index file**

```ts
export { default as ProgressSteps } from './ProgressSteps';
export { default as Celebration } from './Celebration';
export { default as ContentCalendar } from './ContentCalendar';
export { default as CalendarReveal } from './CalendarReveal';
```

**Step 2: Commit**

```bash
git add resources/js/Components/ContentPlan/index.ts
git commit -m "chore: add ContentPlan component exports"
```

---

## Phase 5: Integration & Testing

### Task 25: Build frontend assets

**Step 1: Build**

```bash
npm run build
```

**Step 2: Verify no errors**

Check the build output for any TypeScript or compilation errors.

---

### Task 26: Manual test the flow

**Step 1: Start queue worker**

```bash
php artisan queue:work
```

**Step 2: Test the flow**

1. Go to onboarding wizard
2. Complete all steps
3. Click "Activer l'Autopilot"
4. Verify redirect to `/onboarding/generating/{site}`
5. Watch progress steps animate
6. See celebration animation
7. See calendar reveal

---

### Task 27: Final commit

**Step 1: Commit any remaining changes**

```bash
git add -A
git commit -m "feat: complete content plan auto-generation feature"
```

---

## Summary

**Files created:**
- 3 migrations
- 3 models (SitePage, ScheduledArticle, ContentPlanGeneration)
- 4 services (SiteCrawlerService, DuplicateCheckerService, ContentPlanService, ContentPlanGeneratorService)
- 1 job (GenerateContentPlanJob)
- 1 API controller (ContentPlanController)
- 5 React components (ProgressSteps, Celebration, ContentCalendar, CalendarReveal, Generating page)

**Files modified:**
- Site model (added relationships)
- OnboardingController (updated complete method)
- routes/web.php (added generating route)
- routes/api.php (added content plan endpoints)

**Total tasks:** 27
