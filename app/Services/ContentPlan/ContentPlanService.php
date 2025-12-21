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

        $sortedKeywords = $keywords->sortByDesc('score')->values();
        $publishDates = $this->getPublishDates($publishDays, $days, $articlesPerWeek);

        Log::info("Creating content plan", [
            'site_id' => $site->id,
            'keywords_available' => $sortedKeywords->count(),
            'publish_dates' => count($publishDates),
        ]);

        $site->scheduledArticles()->where('status', 'planned')->delete();

        $scheduled = collect();

        foreach ($publishDates as $index => $date) {
            if (!isset($sortedKeywords[$index])) break;

            $keywordData = $sortedKeywords[$index];

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
