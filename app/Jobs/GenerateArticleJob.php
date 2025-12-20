<?php

namespace App\Jobs;

use App\Models\Article;
use App\Models\BrandVoice;
use App\Models\Keyword;
use App\Services\Content\ArticleGenerator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

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
    public int $timeout = 300; // 5 minutes

    /**
     * The number of seconds to wait before retrying the job.
     */
    public int $backoff = 60;

    public function __construct(
        public readonly Keyword $keyword,
        public readonly ?int $brandVoiceId = null,
    ) {}

    public function handle(ArticleGenerator $generator): void
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

        try {
            $article = $generator->generateAndSave($this->keyword, $brandVoice);

            Log::info("GenerateArticleJob: Completed successfully", [
                'article_id' => $article->id,
                'word_count' => $article->word_count,
                'cost' => $article->generation_cost,
            ]);
        } catch (\Exception $e) {
            Log::error("GenerateArticleJob: Failed", [
                'keyword' => $this->keyword->keyword,
                'error' => $e->getMessage(),
                'attempt' => $this->attempts(),
            ]);

            // If this is the last attempt, mark as failed
            if ($this->attempts() >= $this->tries) {
                Article::create([
                    'site_id' => $this->keyword->site_id,
                    'keyword_id' => $this->keyword->id,
                    'title' => "Failed: {$this->keyword->keyword}",
                    'slug' => 'failed-' . time(),
                    'status' => 'failed',
                    'error_message' => $e->getMessage(),
                ]);
            }

            throw $e;
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
