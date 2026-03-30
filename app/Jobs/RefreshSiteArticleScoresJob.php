<?php

namespace App\Jobs;

use App\Models\Article;
use App\Models\Site;
use App\Services\Content\ArticleScoringService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RefreshSiteArticleScoresJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 300;

    public function __construct(
        public readonly Site $site,
    ) {}

    public function handle(ArticleScoringService $scoringService): void
    {
        Article::query()
            ->where('site_id', $this->site->id)
            ->whereIn('status', [
                Article::STATUS_DRAFT,
                Article::STATUS_REVIEW,
                Article::STATUS_APPROVED,
                Article::STATUS_PUBLISHED,
            ])
            ->whereNotNull('content')
            ->with([
                'site.brandAssets',
                'site.brandRules',
                'keyword',
                'citations',
                'agentEvents',
            ])
            ->chunkById(50, function ($articles) use ($scoringService): void {
                foreach ($articles as $article) {
                    $scoringService->scoreAndSave($article);
                }
            });
    }
}
