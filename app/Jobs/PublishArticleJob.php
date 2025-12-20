<?php

namespace App\Jobs;

use App\Models\Article;
use App\Models\Integration;
use App\Services\Publisher\DTOs\PublishRequest;
use App\Services\Publisher\PublisherManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class PublishArticleJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 120;
    public int $backoff = 60;

    public function __construct(
        public readonly Article $article,
        public readonly Integration $integration,
        public readonly array $options = [],
    ) {}

    public function handle(PublisherManager $publisherManager): void
    {
        Log::info("PublishArticleJob: Starting", [
            'article_id' => $this->article->id,
            'integration_id' => $this->integration->id,
            'integration_type' => $this->integration->type,
        ]);

        try {
            $publisher = $publisherManager->getPublisher($this->integration);

            // Create publish request from article
            $request = PublishRequest::fromArticle($this->article);

            // Apply any custom options
            if (!empty($this->options['categories'])) {
                $request = new PublishRequest(
                    title: $request->title,
                    content: $request->content,
                    slug: $request->slug,
                    excerpt: $request->excerpt,
                    metaTitle: $request->metaTitle,
                    metaDescription: $request->metaDescription,
                    featuredImageUrl: $request->featuredImageUrl,
                    featuredImagePath: $request->featuredImagePath,
                    categories: $this->options['categories'],
                    tags: $this->options['tags'] ?? [],
                    status: $this->options['status'] ?? 'publish',
                );
            }

            // Check if we're updating or creating
            if ($this->article->published_remote_id) {
                $result = $publisher->update($this->article->published_remote_id, $request);
            } else {
                $result = $publisher->publish($request);
            }

            if ($result->success) {
                $this->article->update([
                    'status' => 'published',
                    'published_at' => now(),
                    'published_url' => $result->url,
                    'published_remote_id' => $result->remoteId,
                    'published_via' => $this->integration->type,
                ]);

                Log::info("PublishArticleJob: Success", [
                    'article_id' => $this->article->id,
                    'url' => $result->url,
                ]);
            } else {
                throw new \RuntimeException($result->error ?? 'Unknown publish error');
            }
        } catch (\Exception $e) {
            Log::error("PublishArticleJob: Failed", [
                'article_id' => $this->article->id,
                'error' => $e->getMessage(),
                'attempt' => $this->attempts(),
            ]);

            // Update article status on final failure
            if ($this->attempts() >= $this->tries) {
                $this->article->update([
                    'status' => 'failed',
                    'error_message' => "Publish failed: {$e->getMessage()}",
                ]);
            }

            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("PublishArticleJob: Job failed permanently", [
            'article_id' => $this->article->id,
            'integration_id' => $this->integration->id,
            'error' => $exception->getMessage(),
        ]);

        $this->article->update([
            'status' => 'failed',
            'error_message' => "Publish failed: {$exception->getMessage()}",
        ]);
    }

    public function tags(): array
    {
        return [
            'publish-article',
            'article:' . $this->article->id,
            'integration:' . $this->integration->id,
        ];
    }
}
