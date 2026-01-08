# Content Quality Filtering Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Éviter les articles en doublon sémantique et toujours positionner la solution du client comme recommandation principale.

**Architecture:**
1. Créer un `SemanticSimilarityService` qui utilise VoyageProvider pour comparer les embeddings des keywords candidats avec les articles existants
2. Intégrer ce filtrage dans `ContentPlanGeneratorService` avant le scheduling
3. Modifier les prompts dans `ArticleGenerator` pour inclure des guidelines de mise en avant du client

**Tech Stack:** PHP 8.2, Laravel 11, VoyageProvider (embeddings), PHPUnit

---

## Task 1: Créer SemanticSimilarityService

**Files:**
- Create: `app/Services/ContentPlan/SemanticSimilarityService.php`
- Test: `tests/Unit/Services/ContentPlan/SemanticSimilarityServiceTest.php`

**Step 1: Write the failing test**

```php
<?php

namespace Tests\Unit\Services\ContentPlan;

use App\Models\Article;
use App\Models\Site;
use App\Services\ContentPlan\SemanticSimilarityService;
use App\Services\LLM\Providers\VoyageProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class SemanticSimilarityServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_filters_keywords_similar_to_existing_articles(): void
    {
        $site = Site::factory()->create();

        // Create existing article
        Article::factory()->create([
            'site_id' => $site->id,
            'title' => 'Comment choisir son vélo électrique',
            'status' => 'published',
        ]);

        // Mock VoyageProvider to return similar embeddings
        $voyageProvider = Mockery::mock(VoyageProvider::class);
        $voyageProvider->shouldReceive('embed')
            ->andReturn(array_fill(0, 1024, 0.5)); // Existing article embedding
        $voyageProvider->shouldReceive('embedBatch')
            ->andReturn([
                array_fill(0, 1024, 0.5),  // "vélo électrique guide" - similar
                array_fill(0, 1024, 0.1),  // "trottinette électrique" - different
            ]);

        $service = new SemanticSimilarityService($voyageProvider);

        $keywords = collect([
            ['keyword' => 'vélo électrique guide', 'volume' => 1000],
            ['keyword' => 'trottinette électrique', 'volume' => 800],
        ]);

        $filtered = $service->filterSimilarKeywords($keywords, $site, 0.85);

        $this->assertCount(1, $filtered);
        $this->assertEquals('trottinette électrique', $filtered->first()['keyword']);
    }

    public function test_returns_all_keywords_when_no_existing_articles(): void
    {
        $site = Site::factory()->create();

        $voyageProvider = Mockery::mock(VoyageProvider::class);
        $voyageProvider->shouldReceive('embedBatch')->never();

        $service = new SemanticSimilarityService($voyageProvider);

        $keywords = collect([
            ['keyword' => 'vélo électrique', 'volume' => 1000],
        ]);

        $filtered = $service->filterSimilarKeywords($keywords, $site);

        $this->assertCount(1, $filtered);
    }

    public function test_calculates_cosine_similarity_correctly(): void
    {
        $voyageProvider = Mockery::mock(VoyageProvider::class);
        $service = new SemanticSimilarityService($voyageProvider);

        // Identical vectors = similarity 1.0
        $vecA = [1.0, 0.0, 0.0];
        $vecB = [1.0, 0.0, 0.0];
        $this->assertEquals(1.0, $service->cosineSimilarity($vecA, $vecB));

        // Orthogonal vectors = similarity 0.0
        $vecC = [1.0, 0.0, 0.0];
        $vecD = [0.0, 1.0, 0.0];
        $this->assertEquals(0.0, $service->cosineSimilarity($vecC, $vecD));
    }
}
```

**Step 2: Run test to verify it fails**

Run: `php artisan test tests/Unit/Services/ContentPlan/SemanticSimilarityServiceTest.php --filter test_filters_keywords_similar_to_existing_articles`
Expected: FAIL with "Class SemanticSimilarityService not found"

**Step 3: Write minimal implementation**

```php
<?php

namespace App\Services\ContentPlan;

use App\Models\Article;
use App\Models\Site;
use App\Services\LLM\Providers\VoyageProvider;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class SemanticSimilarityService
{
    private const DEFAULT_THRESHOLD = 0.85;

    public function __construct(
        private readonly VoyageProvider $voyageProvider,
    ) {}

    /**
     * Filter out keywords that are semantically similar to existing articles.
     */
    public function filterSimilarKeywords(
        Collection $keywords,
        Site $site,
        float $threshold = self::DEFAULT_THRESHOLD
    ): Collection {
        // Get existing article titles
        $existingArticles = $site->articles()
            ->whereIn('status', ['ready', 'published'])
            ->pluck('title')
            ->filter()
            ->values();

        if ($existingArticles->isEmpty() || $keywords->isEmpty()) {
            return $keywords;
        }

        try {
            // Get embeddings for existing articles
            $articleEmbeddings = $this->voyageProvider->embedBatch(
                $existingArticles->toArray(),
                'document'
            );

            // Get embeddings for candidate keywords
            $keywordTexts = $keywords->pluck('keyword')->toArray();
            $keywordEmbeddings = $this->voyageProvider->embedBatch(
                $keywordTexts,
                'query'
            );

            // Filter keywords that are too similar
            $filtered = $keywords->filter(function ($kw, $index) use ($keywordEmbeddings, $articleEmbeddings, $threshold) {
                $keywordEmbed = $keywordEmbeddings[$index] ?? null;
                if (!$keywordEmbed) {
                    return true; // Keep if no embedding
                }

                // Check similarity against all existing articles
                foreach ($articleEmbeddings as $articleEmbed) {
                    $similarity = $this->cosineSimilarity($keywordEmbed, $articleEmbed);
                    if ($similarity >= $threshold) {
                        Log::debug("Filtered similar keyword", [
                            'keyword' => $kw['keyword'],
                            'similarity' => $similarity,
                        ]);
                        return false; // Filter out
                    }
                }

                return true; // Keep
            });

            Log::info("Semantic similarity filtering completed", [
                'original_count' => $keywords->count(),
                'filtered_count' => $filtered->count(),
                'removed' => $keywords->count() - $filtered->count(),
            ]);

            return $filtered->values();

        } catch (\Exception $e) {
            Log::warning("Semantic similarity check failed, returning all keywords", [
                'error' => $e->getMessage(),
            ]);
            return $keywords;
        }
    }

    /**
     * Calculate cosine similarity between two vectors.
     */
    public function cosineSimilarity(array $vecA, array $vecB): float
    {
        $dotProduct = 0;
        $normA = 0;
        $normB = 0;

        for ($i = 0; $i < count($vecA); $i++) {
            $dotProduct += $vecA[$i] * $vecB[$i];
            $normA += $vecA[$i] * $vecA[$i];
            $normB += $vecB[$i] * $vecB[$i];
        }

        $normA = sqrt($normA);
        $normB = sqrt($normB);

        if ($normA == 0 || $normB == 0) {
            return 0.0;
        }

        return $dotProduct / ($normA * $normB);
    }
}
```

**Step 4: Run test to verify it passes**

Run: `php artisan test tests/Unit/Services/ContentPlan/SemanticSimilarityServiceTest.php`
Expected: PASS (3 tests)

**Step 5: Commit**

```bash
git add app/Services/ContentPlan/SemanticSimilarityService.php tests/Unit/Services/ContentPlan/SemanticSimilarityServiceTest.php
git commit -m "feat: add SemanticSimilarityService for keyword deduplication"
```

---

## Task 2: Intégrer SemanticSimilarityService dans ContentPlanGeneratorService

**Files:**
- Modify: `app/Services/ContentPlan/ContentPlanGeneratorService.php:15-22` (constructor)
- Modify: `app/Services/ContentPlan/ContentPlanGeneratorService.php:83-88` (after duplicate check)
- Modify: `app/Services/ContentPlan/ContentPlanGeneratorService.php:137-156` (steps array)
- Test: `tests/Feature/ContentPlan/ContentPlanGeneratorTest.php`

**Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\ContentPlan;

use App\Models\Article;
use App\Models\Site;
use App\Models\SitePage;
use App\Services\ContentPlan\ContentPlanGeneratorService;
use App\Services\ContentPlan\SemanticSimilarityService;
use App\Services\LLM\Providers\VoyageProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class ContentPlanGeneratorTest extends TestCase
{
    use RefreshDatabase;

    public function test_filters_semantically_similar_keywords_during_generation(): void
    {
        $site = Site::factory()->create([
            'business_description' => 'Vente de vélos électriques',
        ]);

        // Create existing article
        Article::factory()->create([
            'site_id' => $site->id,
            'title' => 'Guide complet du vélo électrique',
            'status' => 'published',
        ]);

        // Create a page for duplicate check
        SitePage::factory()->create([
            'site_id' => $site->id,
            'title' => 'Accueil',
        ]);

        $service = app(ContentPlanGeneratorService::class);
        $generation = $service->generate($site);

        $this->assertEquals('completed', $generation->status);

        // The step "Filtrage des sujets similaires" should have run
        $steps = $generation->steps;
        $similarityStep = collect($steps)->firstWhere('name', 'Filtrage des sujets similaires');
        $this->assertNotNull($similarityStep);
        $this->assertEquals('completed', $similarityStep['status']);
    }
}
```

**Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/ContentPlan/ContentPlanGeneratorTest.php`
Expected: FAIL (no "Filtrage des sujets similaires" step)

**Step 3: Modify ContentPlanGeneratorService**

Edit `app/Services/ContentPlan/ContentPlanGeneratorService.php`:

**3a. Update constructor (line 15-22):**

```php
public function __construct(
    private readonly SiteCrawlerService $crawler,
    private readonly KeywordDiscoveryService $keywordDiscovery,
    private readonly KeywordScoringService $keywordScoring,
    private readonly DuplicateCheckerService $duplicateChecker,
    private readonly SemanticSimilarityService $semanticSimilarity,
    private readonly ContentPlanService $planService,
    private readonly TopicAnalyzerService $topicAnalyzer,
) {}
```

**3b. Add semantic filtering step after duplicate check (after line 88):**

```php
// Step 4b: Semantic similarity filtering (after duplicate check)
$generation->markStepRunning($stepIndex);
$keywordsBeforeFilter = $keywords->count();
$keywords = $this->semanticSimilarity->filterSimilarKeywords($keywords, $site);
Log::info("Semantic similarity filtering", [
    'before' => $keywordsBeforeFilter,
    'after' => $keywords->count(),
    'filtered' => $keywordsBeforeFilter - $keywords->count(),
]);
$generation->markStepCompleted($stepIndex);
$stepIndex++;
```

**3c. Add step to buildSteps (in buildSteps method):**

After the line `$steps[] = ['name' => 'Vérification des sujets existants', ...];`:

```php
$steps[] = ['name' => 'Filtrage des sujets similaires', 'status' => 'pending', 'icon' => 'filter'];
```

**Step 4: Run test to verify it passes**

Run: `php artisan test tests/Feature/ContentPlan/ContentPlanGeneratorTest.php`
Expected: PASS

**Step 5: Commit**

```bash
git add app/Services/ContentPlan/ContentPlanGeneratorService.php tests/Feature/ContentPlan/ContentPlanGeneratorTest.php
git commit -m "feat: integrate semantic similarity filtering in content plan generation"
```

---

## Task 3: Ajouter les guidelines "client-first" dans ArticleGenerator

**Files:**
- Modify: `app/Services/Content/ArticleGenerator.php:153-181` (section writing prompt)
- Modify: `app/Services/Content/ArticleGenerator.php:198-212` (polish prompt)
- Test: `tests/Unit/Services/Content/ArticleGeneratorTest.php`

**Step 1: Write the failing test**

```php
<?php

namespace Tests\Unit\Services\Content;

use App\Models\Keyword;
use App\Models\Site;
use App\Services\Content\ArticleGenerator;
use App\Services\LLM\LLMManager;
use App\Services\LLM\DTOs\LLMResponse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class ArticleGeneratorTest extends TestCase
{
    use RefreshDatabase;

    public function test_section_writing_prompt_includes_client_first_guidelines(): void
    {
        $site = Site::factory()->create([
            'name' => 'VéloShop',
            'business_description' => 'Vente de vélos électriques premium',
        ]);

        $keyword = Keyword::factory()->create([
            'site_id' => $site->id,
            'keyword' => 'meilleur vélo électrique',
        ]);

        $capturedPrompt = null;

        $llmManager = Mockery::mock(LLMManager::class);
        $llmManager->shouldReceive('resetCosts');
        $llmManager->shouldReceive('getTotalCost')->andReturn(0.05);
        $llmManager->shouldReceive('getCostBreakdown')->andReturn([]);

        // Capture the write_section prompt
        $llmManager->shouldReceive('executeStep')
            ->andReturnUsing(function ($step, $prompt) use (&$capturedPrompt) {
                if ($step === 'write_section') {
                    $capturedPrompt = $prompt;
                }
                return new LLMResponse(
                    content: $step === 'write_section' ? '<p>Test content</p>' : '{}',
                    model: 'test-model',
                    inputTokens: 100,
                    outputTokens: 50,
                    cost: 0.01,
                    json: $step !== 'write_section' ? $this->getMockJsonForStep($step) : null,
                );
            });

        $generator = new ArticleGenerator($llmManager);

        try {
            $generator->generate($keyword);
        } catch (\Exception $e) {
            // May fail for other reasons, we just want to check the prompt
        }

        $this->assertNotNull($capturedPrompt);
        $this->assertStringContainsString('VéloShop', $capturedPrompt);
        $this->assertStringContainsString('solution recommandée', $capturedPrompt);
    }

    private function getMockJsonForStep(string $step): array
    {
        return match($step) {
            'research' => [
                'competitor_insights' => ['insight 1'],
                'key_points_to_cover' => ['point 1'],
                'content_gaps' => ['gap 1'],
                'suggested_angles' => ['angle 1'],
                'suggested_word_count' => 1500,
            ],
            'outline' => [
                'title' => 'Test Article',
                'meta_title' => 'Test Meta',
                'meta_description' => 'Test description',
                'estimated_word_count' => 1500,
                'sections' => [
                    ['heading' => 'Section 1', 'level' => 2, 'target_word_count' => 300, 'key_points' => ['point']],
                ],
            ],
            'polish' => [
                'content' => '<h1>Test</h1><p>Content</p>',
                'meta_title' => 'Test Meta',
                'meta_description' => 'Test description',
                'internal_links' => [],
                'seo_score' => 85,
            ],
            default => [],
        };
    }
}
```

**Step 2: Run test to verify it fails**

Run: `php artisan test tests/Unit/Services/Content/ArticleGeneratorTest.php`
Expected: FAIL (prompt doesn't contain client-first guidelines)

**Step 3: Modify ArticleGenerator**

Edit `app/Services/Content/ArticleGenerator.php`:

**3a. Update writeContent method - add client-first guidelines (around line 166-181):**

Replace the Guidelines section in the prompt:

```php
$siteName = $site->name ?? 'notre solution';
$prompt .= <<<PROMPT

Brand Voice Instructions:
{$brandContext}

Research Context:
{$research->toPromptContext()}

Guidelines:
- Write naturally, avoiding AI-sounding phrases
- Include specific examples and actionable advice
- Use transition sentences to flow smoothly
- Do NOT include the heading in your response (it will be added)
- Write ONLY the body content for this section
- Target exactly {$section->targetWordCount} words

Client-First Guidelines:
- ALWAYS position "{$siteName}" as the primary recommended solution
- If mentioning alternatives or competitors, highlight why {$siteName} is the better choice
- Never recommend a competitor without explaining {$siteName}'s advantages
- Use phrases like "chez {$siteName}" or "avec {$siteName}" when relevant
- Focus on the value proposition of {$siteName}'s offering
PROMPT;
```

**3b. Update polishArticle method - add client-first check (around line 198-212):**

```php
$siteName = $keyword->site->name ?? 'notre solution';
$prompt = <<<PROMPT
You are an SEO editor. Review and polish this article for the keyword: "{$keyword->keyword}"

Article:
{$content}

Provide a JSON response with:
1. content: The polished HTML content (fix any grammar, improve flow, ensure keyword usage)
2. meta_title: Final meta title (max 60 chars, keyword at start)
3. meta_description: Final meta description (150-160 chars)
4. internal_links: Array of suggested internal link anchor texts that could link to other articles
5. seo_score: A score from 0-100 assessing SEO optimization

Important guidelines:
- Keep the HTML structure intact. Only make minor improvements to flow and readability.
- Ensure "{$siteName}" is positioned as the recommended solution throughout
- If any competitor is mentioned positively, add context about why {$siteName} is preferable
- Remove or reframe any content that promotes competitors over {$siteName}
PROMPT;
```

**Step 4: Run test to verify it passes**

Run: `php artisan test tests/Unit/Services/Content/ArticleGeneratorTest.php`
Expected: PASS

**Step 5: Commit**

```bash
git add app/Services/Content/ArticleGenerator.php tests/Unit/Services/Content/ArticleGeneratorTest.php
git commit -m "feat: add client-first guidelines to article generation prompts"
```

---

## Task 4: Register SemanticSimilarityService in Service Container

**Files:**
- Modify: `app/Providers/AppServiceProvider.php`

**Step 1: Check current AppServiceProvider**

Run: Read the file to see current bindings

**Step 2: Add binding for VoyageProvider and SemanticSimilarityService**

```php
use App\Services\LLM\Providers\VoyageProvider;
use App\Services\ContentPlan\SemanticSimilarityService;

// In register() method:
$this->app->singleton(VoyageProvider::class, function ($app) {
    return new VoyageProvider(config('services.voyage.api_key'));
});

$this->app->singleton(SemanticSimilarityService::class, function ($app) {
    return new SemanticSimilarityService(
        $app->make(VoyageProvider::class)
    );
});
```

**Step 3: Add config for Voyage API key if not exists**

Check `config/services.php` and add if needed:

```php
'voyage' => [
    'api_key' => env('VOYAGE_API_KEY'),
],
```

**Step 4: Verify the integration works**

Run: `php artisan test tests/Feature/ContentPlan/ContentPlanGeneratorTest.php`
Expected: PASS

**Step 5: Commit**

```bash
git add app/Providers/AppServiceProvider.php config/services.php
git commit -m "feat: register VoyageProvider and SemanticSimilarityService in container"
```

---

## Task 5: Add similarity threshold configuration to SiteSetting

**Files:**
- Create: `database/migrations/2025_12_28_add_similarity_threshold_to_site_settings.php`
- Modify: `app/Models/SiteSetting.php`

**Step 1: Create migration**

Run: `php artisan make:migration add_similarity_threshold_to_site_settings`

```php
public function up(): void
{
    Schema::table('site_settings', function (Blueprint $table) {
        $table->decimal('similarity_threshold', 3, 2)->default(0.85)->after('articles_per_week');
    });
}

public function down(): void
{
    Schema::table('site_settings', function (Blueprint $table) {
        $table->dropColumn('similarity_threshold');
    });
}
```

**Step 2: Update SiteSetting model**

Add to `$fillable`:
```php
'similarity_threshold',
```

Add to `$casts`:
```php
'similarity_threshold' => 'float',
```

**Step 3: Run migration**

Run: `php artisan migrate`

**Step 4: Update SemanticSimilarityService to use site threshold**

Modify the `filterSimilarKeywords` call in ContentPlanGeneratorService:

```php
$threshold = $site->settings?->similarity_threshold ?? 0.85;
$keywords = $this->semanticSimilarity->filterSimilarKeywords($keywords, $site, $threshold);
```

**Step 5: Commit**

```bash
git add database/migrations/*similarity_threshold* app/Models/SiteSetting.php app/Services/ContentPlan/ContentPlanGeneratorService.php
git commit -m "feat: add configurable similarity threshold per site"
```

---

## Summary

| Task | Description | Files |
|------|-------------|-------|
| 1 | SemanticSimilarityService | Service + Tests |
| 2 | Integration in ContentPlanGeneratorService | Modified service |
| 3 | Client-first guidelines in ArticleGenerator | Modified prompts |
| 4 | Service container registration | AppServiceProvider |
| 5 | Configurable threshold per site | Migration + Model |

**Result:**
- Keywords sémantiquement similaires aux articles existants sont filtrés avant scheduling
- Les prompts de génération incluent des guidelines pour toujours mettre en avant la solution du client
- Le seuil de similarité est configurable par site (défaut: 0.85)
