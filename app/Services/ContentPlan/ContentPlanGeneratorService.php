<?php

namespace App\Services\ContentPlan;

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
        private readonly TopicAnalyzerService $topicAnalyzer,
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
}
