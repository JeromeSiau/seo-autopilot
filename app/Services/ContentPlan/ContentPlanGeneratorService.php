<?php

namespace App\Services\ContentPlan;

use App\Jobs\SiteIndexJob;
use App\Models\ContentPlanGeneration;
use App\Models\Site;
use App\Services\Crawler\SiteCrawlerService;
use App\Services\Keyword\KeywordDiscoveryService;
use App\Services\Keyword\KeywordScoringService;
use App\Services\SEO\DTOs\KeywordData;
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

            // Step 1: Crawl site (always)
            $generation->markStepRunning($stepIndex);
            $this->crawler->crawl($site);
            $this->crawler->extractTitlesForPages($site, 30);

            // Trigger deep indexation with embeddings for internal linking
            dispatch(new SiteIndexJob($site, delta: false))
                ->onQueue('crawl');

            $generation->markStepCompleted($stepIndex);
            $stepIndex++;

            // Step 2: Analyze GSC (if connected)
            if ($site->isGscConnected()) {
                $generation->markStepRunning($stepIndex);
                try {
                    $gscKeywords = $this->keywordDiscovery->mineFromSearchConsole($site);
                    $keywords = $keywords->merge($gscKeywords);
                } catch (\Exception $e) {
                    Log::warning("GSC analysis failed, continuing without", [
                        'site_id' => $site->id,
                        'error' => $e->getMessage(),
                    ]);
                }
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
                Log::info("Generated keywords via AI", ['count' => $aiKeywords->count()]);
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
            $keywords = $keywords->map(function ($kw) {
                if (!isset($kw['score'])) {
                    $kw['score'] = $this->keywordScoring->calculateScoreFromData(
                        new KeywordData(
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

        $steps[] = ['name' => 'Analyse de votre site existant', 'status' => 'pending', 'icon' => 'globe'];

        if ($site->isGscConnected()) {
            $steps[] = ['name' => 'Analyse de vos performances', 'status' => 'pending', 'icon' => 'chart'];
        }

        if ($site->business_description) {
            $steps[] = ['name' => "Génération d'idées avec l'IA", 'status' => 'pending', 'icon' => 'sparkles'];
        }

        $steps[] = ['name' => 'Vérification des sujets existants', 'status' => 'pending', 'icon' => 'search'];
        $steps[] = ['name' => 'Analyse du potentiel de chaque sujet', 'status' => 'pending', 'icon' => 'trending'];
        $steps[] = ['name' => 'Création de votre Content Plan', 'status' => 'pending', 'icon' => 'calendar'];

        return $steps;
    }
}
