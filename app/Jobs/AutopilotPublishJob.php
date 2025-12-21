<?php

namespace App\Jobs;

use App\Models\Article;
use App\Models\AutopilotLog;
use App\Services\Notification\NotificationService;
use App\Services\Publisher\PublisherManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class AutopilotPublishJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 120;

    public function handle(PublisherManager $publisher, NotificationService $notifications): void
    {
        Log::info('AutopilotPublishJob: Checking for articles to publish');

        $articles = Article::where('status', 'ready')
            ->whereHas('site.settings', fn($q) => $q->where('auto_publish', true))
            ->get();

        foreach ($articles as $article) {
            try {
                $this->publishArticle($article, $publisher, $notifications);
            } catch (\Exception $e) {
                Log::error("AutopilotPublishJob: Failed for article {$article->id}", [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::info("AutopilotPublishJob: Processed {$articles->count()} articles");
    }

    private function publishArticle(
        Article $article,
        PublisherManager $publisher,
        NotificationService $notifications
    ): void {
        $site = $article->site;
        $integration = $site->integration;

        if (!$integration || !$integration->is_active) {
            Log::info("AutopilotPublishJob: No integration for site {$site->id}, keeping as ready");
            return;
        }

        // Dispatch the existing PublishArticleJob
        PublishArticleJob::dispatch($article, $integration);

        AutopilotLog::log($site->id, AutopilotLog::TYPE_ARTICLE_PUBLISHED, [
            'article_id' => $article->id,
            'title' => $article->title,
        ]);
    }

    public function tags(): array
    {
        return ['autopilot', 'publish'];
    }
}
