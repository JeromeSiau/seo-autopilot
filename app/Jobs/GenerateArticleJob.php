<?php

namespace App\Jobs;

use App\Models\Article;
use App\Models\BrandVoice;
use App\Models\Keyword;
use App\Models\ScheduledArticle;
use App\Services\Agent\AgentEventService;
use App\Services\Agent\AgentRunner;
use App\Services\Content\ArticleGenerator;
use App\Services\Image\ImageGenerator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class GenerateArticleJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 900; // 15 minutes for full agent pipeline

    /**
     * The number of seconds to wait before retrying the job.
     */
    public int $backoff = 60;

    public function __construct(
        public readonly Keyword $keyword,
        public readonly ?int $brandVoiceId = null,
        public readonly bool $generateImages = true,
        public readonly int $sectionImageCount = 2,
    ) {}

    public function handle(
        ArticleGenerator $generator,
        ImageGenerator $imageGenerator,
        AgentRunner $agentRunner,
        AgentEventService $eventService
    ): void
    {
        Log::info("GenerateArticleJob: Starting for keyword '{$this->keyword->keyword}'");

        // Check if team can still generate articles
        $team = $this->keyword->site->team;
        if (!$team->canGenerateArticle()) {
            Log::warning("GenerateArticleJob: Team has reached article limit", [
                'team_id' => $team->id,
                'limit' => $team->articles_limit,
            ]);
            $this->keyword->update(['status' => 'pending']);
            return;
        }

        $brandVoice = $this->brandVoiceId
            ? BrandVoice::find($this->brandVoiceId)
            : $team->brandVoices()->where('is_default', true)->first();

        // Create draft article to track events
        $article = Article::create([
            'site_id' => $this->keyword->site_id,
            'keyword_id' => $this->keyword->id,
            'brand_voice_id' => $brandVoice?->id,
            'title' => $this->keyword->keyword,
            'slug' => 'draft-' . time(),
            'status' => 'generating',
        ]);

        try {
            // Emit generation started event
            $eventService->started($article, 'orchestrator', 'Début de la génération de l\'article', 'Préparation du pipeline de génération');

            // Phase 1: Research
            $eventService->started($article, 'research', 'Recherche d\'informations sur le sujet', 'Analyse des sources et du contexte');

            try {
                $researchData = $agentRunner->runResearchAgent($article, $this->keyword->keyword);
                $eventService->completed($article, 'research', 'Recherche terminée', 'Informations collectées avec succès', $researchData);
            } catch (\Exception $e) {
                $eventService->error($article, 'research', 'Échec de la recherche', $e->getMessage());
                // Continue with generation even if research fails
                $researchData = [];
            }

            // Phase 1: Competitor Analysis
            $eventService->started($article, 'competitor', 'Analyse de la concurrence', 'Étude des contenus concurrents');

            try {
                $competitorData = $agentRunner->runCompetitorAgent(
                    $article,
                    $this->keyword->keyword,
                    $researchData['top_urls'] ?? []
                );
                $eventService->completed($article, 'competitor', 'Analyse concurrentielle terminée', 'Insights extraits', $competitorData);
            } catch (\Exception $e) {
                $eventService->error($article, 'competitor', 'Échec de l\'analyse', $e->getMessage());
                $competitorData = [];
            }

            // Phase 2: Content Generation with LLM
            $eventService->started($article, 'writing', 'Rédaction de l\'article', 'Génération du contenu via LLM');

            $generated = $generator->generate($this->keyword, $brandVoice);

            $eventService->completed($article, 'writing', 'Rédaction terminée', 'Article généré avec succès');

            // Phase 3: Fact Checking
            $eventService->started($article, 'fact_checker', 'Vérification des faits', 'Analyse des affirmations');

            try {
                $factCheckResult = $agentRunner->runFactCheckerAgent($article, $generated->content);
                $eventService->completed($article, 'fact_checker', 'Vérification terminée', 'Faits vérifiés', $factCheckResult);
            } catch (\Exception $e) {
                $eventService->error($article, 'fact_checker', 'Échec de la vérification', $e->getMessage());
            }

            // Phase 4: Internal Linking
            $eventService->started($article, 'internal_linking', 'Ajout des liens internes', 'Analyse du maillage interne');

            try {
                $linkingResult = $agentRunner->runInternalLinkingAgent($article, $generated->content);

                // Use the linked content if available
                $finalContent = $linkingResult['linked_content'] ?? $generated->content;

                $eventService->completed($article, 'internal_linking', 'Liens internes ajoutés', 'Maillage interne optimisé', $linkingResult);
            } catch (\Exception $e) {
                $eventService->error($article, 'internal_linking', 'Échec du maillage', $e->getMessage());
                $finalContent = $generated->content;
            }

            // Update article with final content
            $article->update([
                'title' => $generated->title,
                'slug' => Str::slug($generated->title),
                'content' => $finalContent,
                'meta_title' => $generated->metaTitle,
                'meta_description' => $generated->metaDescription,
                'status' => 'ready',
                'llm_used' => implode(', ', array_keys($generated->llmsUsed)),
                'generation_cost' => $generated->totalCost,
                'word_count' => str_word_count(strip_tags($finalContent)),
                'generation_time_seconds' => $generated->generationTimeSeconds,
            ]);

            $this->keyword->markAsCompleted();

            $eventService->completed($article, 'orchestrator', 'Article prêt', 'Génération terminée avec succès');

            // Generate images if enabled
            if ($this->generateImages) {
                $this->generateArticleImages($article, $imageGenerator);
            }

            // Update scheduled article if exists
            $scheduledArticle = ScheduledArticle::where('keyword_id', $this->keyword->id)
                ->where('status', 'generating')
                ->first();

            if ($scheduledArticle) {
                $scheduledArticle->update([
                    'status' => 'ready',
                    'article_id' => $article->id,
                ]);
            }

            Log::info("GenerateArticleJob: Completed successfully", [
                'article_id' => $article->id,
                'word_count' => $article->word_count,
                'cost' => $article->generation_cost,
            ]);
        } catch (\Exception $e) {
            $eventService->error($article, 'orchestrator', 'Échec de la génération', $e->getMessage());

            $article->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            Log::error("GenerateArticleJob: Failed", [
                'keyword' => $this->keyword->keyword,
                'error' => $e->getMessage(),
                'attempt' => $this->attempts(),
            ]);

            // Update scheduled article if exists
            ScheduledArticle::where('keyword_id', $this->keyword->id)
                ->where('status', 'generating')
                ->update([
                    'status' => 'failed',
                    'article_id' => $article->id,
                ]);

            throw $e;
        }
    }

    private function generateArticleImages(Article $article, ImageGenerator $imageGenerator): void
    {
        try {
            Log::info("GenerateArticleJob: Generating images for article", [
                'article_id' => $article->id,
            ]);

            $images = $imageGenerator->generateAllImages(
                $article,
                $this->sectionImageCount
            );

            // Store image paths in article
            $imageData = [
                'featured' => $images['featured']->toArray(),
                'sections' => array_map(fn($img) => $img->toArray(), $images['sections']),
            ];

            $article->update([
                'images' => $imageData,
                'generation_cost' => $article->generation_cost + $imageGenerator->getTotalCost(),
            ]);

            Log::info("GenerateArticleJob: Images generated successfully", [
                'article_id' => $article->id,
                'image_cost' => $imageGenerator->getTotalCost(),
            ]);
        } catch (\Exception $e) {
            // Image generation failure shouldn't fail the whole job
            Log::warning("GenerateArticleJob: Image generation failed", [
                'article_id' => $article->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error("GenerateArticleJob: Job failed permanently", [
            'keyword' => $this->keyword->keyword,
            'error' => $exception->getMessage(),
        ]);

        $this->keyword->update(['status' => 'pending']);
    }

    /**
     * Get the tags that should be assigned to the job.
     */
    public function tags(): array
    {
        return [
            'article-generation',
            'keyword:' . $this->keyword->id,
            'site:' . $this->keyword->site_id,
        ];
    }
}
