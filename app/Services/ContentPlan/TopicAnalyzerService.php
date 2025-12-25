<?php

namespace App\Services\ContentPlan;

use App\Models\Site;
use App\Services\Crawler\SiteIndexService;
use App\Services\Google\SearchConsoleService;
use App\Services\LLM\LLMManager;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class TopicAnalyzerService
{
    public function __construct(
        private readonly LLMManager $llm,
        private readonly SiteIndexService $indexService,
        private readonly SearchConsoleService $searchConsole,
    ) {}

    /**
     * Extrait les thématiques principales du site à partir des pages crawlées.
     */
    public function extractTopics(Site $site): array
    {
        $pages = $this->loadPagesWithContent($site);

        if ($pages->isEmpty()) {
            Log::warning('TopicAnalyzer: No pages to analyze', ['site_id' => $site->id]);
            return [];
        }

        $pagesContext = $pages->take(100)->map(fn ($p) => [
            'title' => $p['title'] ?? '',
            'category' => $p['category'] ?? null,
            'url' => $p['url'] ?? '',
        ])->filter(fn ($p) => ! empty($p['title']))->values();

        if ($pagesContext->isEmpty()) {
            return [];
        }

        $prompt = "Analyse ces {$pagesContext->count()} pages d'un site web et identifie les 5-10 thématiques principales.

Pages du site:
{$pagesContext->toJson(JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)}

Retourne un JSON avec ce format exact:
{
    \"topics\": [
        {\"name\": \"nom de la thématique\", \"count\": nombre_de_pages, \"examples\": [\"titre exemple 1\", \"titre exemple 2\"]}
    ]
}";

        try {
            $response = $this->llm->completeJson('openai', $prompt, [], ['model' => 'gpt-4o-mini']);
            $result = json_decode($response->content, true);

            return $result['topics'] ?? [];
        } catch (\Exception $e) {
            Log::error('TopicAnalyzer: LLM failed', ['error' => $e->getMessage()]);

            return [];
        }
    }

    /**
     * Trouve les keywords GSC qui n'ont pas de page dédiée (gaps).
     */
    public function findGaps(Site $site): array
    {
        if (! $site->isGscConnected()) {
            return [];
        }

        try {
            $gscKeywords = $this->searchConsole->getSearchAnalytics(
                $site,
                now()->subDays(28)->format('Y-m-d'),
                now()->subDay()->format('Y-m-d'),
                ['query'],
                200
            );

            $existingTitles = $site->pages()
                ->whereNotNull('title')
                ->pluck('title')
                ->map(fn ($t) => strtolower($t));

            $gaps = collect($gscKeywords)->filter(function ($row) use ($existingTitles) {
                $keyword = strtolower($row->keys[0] ?? '');
                if (strlen($keyword) < 3) {
                    return false;
                }

                // Garder si aucune page n'a ce keyword dans le titre
                return ! $existingTitles->contains(fn ($t) => str_contains($t, $keyword));
            })->take(50)->map(fn ($row) => [
                'keyword' => $row->keys[0],
                'impressions' => $row->impressions ?? 0,
                'clicks' => $row->clicks ?? 0,
                'position' => $row->position ?? null,
            ])->values()->toArray();

            return $gaps;
        } catch (\Exception $e) {
            Log::warning('TopicAnalyzer: GSC gaps analysis failed', ['error' => $e->getMessage()]);

            return [];
        }
    }

    /**
     * Charge les pages avec contenu depuis MySQL + SQLite si disponible.
     */
    private function loadPagesWithContent(Site $site): Collection
    {
        // 1. Charger depuis MySQL (toujours disponible)
        $mysqlPages = $site->pages()->get()->map(fn ($p) => [
            'url' => $p->url,
            'title' => $p->title,
            'category' => null,
            'source' => 'mysql',
        ]);

        // 2. Enrichir avec SQLite si disponible
        if ($this->indexService->hasIndex($site)) {
            try {
                $sqlitePages = $this->indexService->getAllPages($site);

                // Créer un map URL -> données SQLite
                $sqliteMap = collect($sqlitePages)->keyBy('url');

                // Merger les données
                $mysqlPages = $mysqlPages->map(function ($page) use ($sqliteMap) {
                    if (isset($sqliteMap[$page['url']])) {
                        $sqlite = $sqliteMap[$page['url']];
                        $page['title'] = $sqlite['title'] ?: $page['title'];
                        $page['category'] = $sqlite['category'] ?? null;
                        $page['source'] = 'sqlite';
                    }

                    return $page;
                });
            } catch (\Exception $e) {
                Log::warning('TopicAnalyzer: SQLite read failed', ['error' => $e->getMessage()]);
            }
        }

        return $mysqlPages;
    }
}
