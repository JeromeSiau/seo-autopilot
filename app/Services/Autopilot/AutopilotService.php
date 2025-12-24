<?php

namespace App\Services\Autopilot;

use App\Models\Site;
use App\Models\Keyword;
use App\Models\AutopilotLog;
use App\Models\ScheduledArticle;
use App\Jobs\GenerateArticleJob;
use Illuminate\Support\Facades\Log;

class AutopilotService
{
    public function processKeywordDiscovery(Site $site): int
    {
        if (!$site->isAutopilotActive()) {
            return 0;
        }

        $discoveredCount = 0;

        // Import from GSC if connected
        if ($site->isGscConnected()) {
            $discoveredCount += $this->importFromSearchConsole($site);
        }

        // Generate via LLM if we have business description
        if ($site->business_description) {
            $discoveredCount += $this->generateKeywordSuggestions($site);
        }

        if ($discoveredCount > 0) {
            AutopilotLog::log($site->id, AutopilotLog::TYPE_KEYWORDS_IMPORTED, [
                'count' => $discoveredCount,
            ]);
        }

        return $discoveredCount;
    }

    public function processArticleGeneration(Site $site): bool
    {
        $settings = $site->settings;

        if (!$settings?->autopilot_enabled) {
            return false;
        }

        // Check weekly quota
        $articlesThisWeek = $site->articles()
            ->where('created_at', '>=', now()->startOfWeek())
            ->count();

        if ($articlesThisWeek >= $settings->articles_per_week) {
            Log::info("AutopilotService: Weekly quota reached for site {$site->id}");
            return false;
        }

        // Get next scheduled article for today or earlier (catch up on missed days)
        $scheduledArticle = $site->scheduledArticles()
            ->where('status', 'planned')
            ->whereDate('scheduled_date', '<=', now())
            ->with('keyword')
            ->orderBy('scheduled_date')
            ->first();

        if (!$scheduledArticle) {
            Log::info("AutopilotService: No scheduled articles for today for site {$site->id}");
            return false;
        }

        $keyword = $scheduledArticle->keyword;

        if (!$keyword) {
            Log::warning("AutopilotService: Scheduled article has no keyword", [
                'scheduled_article_id' => $scheduledArticle->id,
            ]);
            $scheduledArticle->update(['status' => 'failed']);
            return false;
        }

        // Update statuses
        $keyword->markAsGenerating();
        $scheduledArticle->update(['status' => 'generating']);

        // Dispatch generation job
        GenerateArticleJob::dispatch($keyword);

        AutopilotLog::log($site->id, AutopilotLog::TYPE_ARTICLE_GENERATED, [
            'keyword_id' => $keyword->id,
            'keyword' => $keyword->keyword,
            'scheduled_article_id' => $scheduledArticle->id,
        ]);

        return true;
    }

    public function getActiveSites(): \Illuminate\Database\Eloquent\Collection
    {
        return Site::whereHas('settings', function ($query) {
            $query->where('autopilot_enabled', true);
        })->get();
    }

    private function importFromSearchConsole(Site $site): int
    {
        try {
            $service = app(\App\Services\Keyword\KeywordDiscoveryService::class);
            return $service->discoverFromSearchConsole($site);
        } catch (\Exception $e) {
            Log::error("AutopilotService: GSC import failed", ['error' => $e->getMessage()]);
            return 0;
        }
    }

    private function generateKeywordSuggestions(Site $site): int
    {
        try {
            $service = app(\App\Services\Keyword\KeywordDiscoveryService::class);
            return $service->generateFromBusinessDescription($site);
        } catch (\Exception $e) {
            Log::error("AutopilotService: Keyword generation failed", ['error' => $e->getMessage()]);
            return 0;
        }
    }
}
