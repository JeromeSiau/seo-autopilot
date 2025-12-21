<?php

namespace App\Services\Keyword;

use App\Models\Keyword;
use App\Models\Site;
use App\Services\Google\SearchConsoleService;
use App\Services\LLM\LLMManager;
use App\Services\SEO\DataForSEOService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class KeywordDiscoveryService
{
    public function __construct(
        private readonly SearchConsoleService $searchConsole,
        private readonly DataForSEOService $dataForSEO,
        private readonly KeywordScoringService $scoring,
        private readonly LLMManager $llm,
    ) {}

    /**
     * Discover keywords from all sources for a site.
     */
    public function discoverKeywords(Site $site, array $options = []): Collection
    {
        Log::info("Starting keyword discovery for site: {$site->domain}");

        $keywords = collect();

        // 1. Mine from Search Console
        if ($site->isGscConnected()) {
            $gscKeywords = $this->mineFromSearchConsole($site, $options);
            $keywords = $keywords->merge($gscKeywords);
            Log::info("Found {$gscKeywords->count()} keywords from Search Console");
        }

        // 2. Generate topic ideas via LLM
        if ($options['use_ai'] ?? true) {
            $aiKeywords = $this->generateTopicIdeas($site, $options);
            $keywords = $keywords->merge($aiKeywords);
            Log::info("Generated {$aiKeywords->count()} keywords via AI");
        }

        // 3. Enrich with DataForSEO data
        if ($options['enrich'] ?? true) {
            $keywords = $this->enrichWithSeoData($keywords, $site->language);
        }

        // 4. Calculate scores
        $keywords = $keywords->map(function ($kw) {
            if (!isset($kw['score'])) {
                $kw['score'] = $this->scoring->calculateScoreFromData(
                    new \App\Services\SEO\DTOs\KeywordData(
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

        // 5. Remove duplicates
        $keywords = $keywords->unique('keyword')->values();

        Log::info("Keyword discovery complete", [
            'site_id' => $site->id,
            'total_keywords' => $keywords->count(),
        ]);

        return $keywords;
    }

    /**
     * Mine keywords from Google Search Console.
     */
    public function mineFromSearchConsole(Site $site, array $options = []): Collection
    {
        $days = $options['days'] ?? 28;
        $minImpressions = $options['min_impressions'] ?? 10;

        // Get quick-win keywords (positions 5-30)
        $quickWins = $this->searchConsole->getQuickWinKeywords($site, $days, $minImpressions);

        // Get opportunity keywords (high impressions, low CTR)
        $opportunities = $this->searchConsole->getOpportunityKeywords($site, $days);

        return $quickWins->merge($opportunities)->map(fn($row) => [
            'keyword' => $row->query,
            'source' => 'search_console',
            'position' => $row->position,
            'impressions' => $row->impressions,
            'clicks' => $row->clicks,
            'ctr' => $row->ctr,
            'is_quick_win' => $row->isQuickWin(),
        ])->unique('keyword')->values();
    }

    /**
     * Generate topic ideas using AI.
     */
    public function generateTopicIdeas(Site $site, array $options = []): Collection
    {
        $businessDescription = $options['business_description'] ?? null;
        $existingKeywords = $options['existing_keywords'] ?? [];
        $count = $options['count'] ?? 50;

        $existingList = implode(', ', array_slice($existingKeywords, 0, 20));

        $prompt = <<<PROMPT
You are an SEO expert. Generate {$count} keyword ideas for a website.

Domain: {$site->domain}
Language: {$site->language}
PROMPT;

        if ($businessDescription) {
            $prompt .= "\nBusiness: {$businessDescription}";
        }

        if ($existingList) {
            $prompt .= "\n\nExisting keywords (avoid duplicates): {$existingList}";
        }

        $prompt .= <<<PROMPT


IMPORTANT: Generate keywords that real people actually search for. Focus on:

1. HEAD TERMS (30% - high volume, 1-3 words):
   - Core industry terms people search
   - Popular product/service categories
   - Example: "trail running", "vélo VTT", "randonnée montagne"

2. MEDIUM-TAIL (40% - moderate volume, 2-4 words):
   - Specific topics with good search volume
   - Commercial intent keywords
   - Example: "chaussures trail running", "meilleur VTT électrique"

3. LONG-TAIL (30% - lower volume but targeted, 4+ words):
   - Specific questions or comparisons
   - "How to" guides, reviews, comparisons
   - Example: "comment choisir chaussures trail"

AVOID:
- Overly long question sentences (7+ words)
- Keywords that are too specific to have any search volume
- Generic phrases that don't match real searches

Respond with a JSON object containing a "keywords" array. Each keyword object should have:
- keyword: the search term (keep it concise, 1-5 words preferred)
- intent: informational, commercial, or transactional
- topic_cluster: a category/cluster name for grouping

Example format:
{"keywords": [{"keyword": "trail running", "intent": "informational", "topic_cluster": "sports outdoor"}]}
PROMPT;

        try {
            $response = $this->llm->completeJson('openai', $prompt, [], [
                'model' => 'gpt-4o-mini',
                'temperature' => 0.8,
            ]);

            $result = $response->getJson() ?? [];
            $ideas = $result['keywords'] ?? [];

            return collect($ideas)->map(fn($idea) => [
                'keyword' => $idea['keyword'] ?? '',
                'source' => 'ai_generated',
                'intent' => $idea['intent'] ?? 'informational',
                'cluster' => $idea['topic_cluster'] ?? null,
            ])->filter(fn($kw) => !empty($kw['keyword']));
        } catch (\Exception $e) {
            Log::warning("AI keyword generation failed: {$e->getMessage()}");
            return collect();
        }
    }

    /**
     * Enrich keywords with SEO data from DataForSEO.
     */
    public function enrichWithSeoData(Collection $keywords, string $language = 'en'): Collection
    {
        $keywordTexts = $keywords->pluck('keyword')->unique()->toArray();

        if (empty($keywordTexts)) {
            return $keywords;
        }

        // Map language to location
        $location = match ($language) {
            'fr' => 'France',
            'de' => 'Germany',
            'es' => 'Spain',
            'it' => 'Italy',
            'pt' => 'Brazil',
            default => 'United States',
        };

        try {
            // Get volume data
            $seoData = $this->dataForSEO->getKeywordData($keywordTexts, $language, $location);
            $seoDataMap = $seoData->keyBy('keyword');

            // Get difficulty data (separate API call)
            $difficultyData = collect();
            try {
                $difficultyData = $this->dataForSEO->getKeywordDifficulty($keywordTexts, $language, $location);
            } catch (\Exception $e) {
                Log::warning("DataForSEO difficulty fetch failed: {$e->getMessage()}");
            }

            // Normalize difficulty keys to lowercase for matching
            $difficultyDataLower = $difficultyData->mapWithKeys(fn($item, $key) => [mb_strtolower($key) => $item]);

            return $keywords->map(function ($kw) use ($seoDataMap, $difficultyDataLower) {
                $data = $seoDataMap->get($kw['keyword']);
                $difficulty = $difficultyDataLower->get(mb_strtolower($kw['keyword']));

                if ($data) {
                    $kw['volume'] = $data->volume;
                    $kw['cpc'] = $data->cpc;
                    $kw['trend'] = $data->trend;
                }

                // Use difficulty from dedicated endpoint if available
                if ($difficulty) {
                    $kw['difficulty'] = $difficulty['difficulty'] ?? 0;
                } elseif ($data) {
                    $kw['difficulty'] = $data->difficulty;
                }

                return $kw;
            });
        } catch (\Exception $e) {
            Log::warning("DataForSEO enrichment failed: {$e->getMessage()}");
            return $keywords;
        }
    }

    /**
     * Save discovered keywords to database.
     */
    public function saveKeywords(Site $site, Collection $keywords): int
    {
        $saved = 0;

        foreach ($keywords as $kw) {
            $existing = Keyword::where('site_id', $site->id)
                ->where('keyword', $kw['keyword'])
                ->first();

            if ($existing) {
                // Update existing keyword with new data
                $existing->update([
                    'volume' => $kw['volume'] ?? $existing->volume,
                    'difficulty' => $kw['difficulty'] ?? $existing->difficulty,
                    'score' => $kw['score'] ?? $existing->score,
                ]);
            } else {
                // Create new keyword
                Keyword::create([
                    'site_id' => $site->id,
                    'keyword' => $kw['keyword'],
                    'volume' => $kw['volume'] ?? null,
                    'difficulty' => $kw['difficulty'] ?? null,
                    'score' => $kw['score'] ?? 0,
                    'source' => $kw['source'] ?? 'manual',
                    'cluster_id' => null, // Will be set by clustering
                    'status' => 'pending',
                ]);
                $saved++;
            }
        }

        return $saved;
    }

    /**
     * Cluster keywords by topic.
     */
    public function clusterKeywords(Site $site): array
    {
        $keywords = $site->keywords()->whereNull('cluster_id')->get();

        if ($keywords->isEmpty()) {
            return [];
        }

        $keywordList = $keywords->pluck('keyword')->implode(', ');

        $prompt = <<<PROMPT
Group these keywords into logical topic clusters for SEO content planning.

Keywords: {$keywordList}

Create 5-10 clusters. Each cluster should have:
- A pillar topic (main topic)
- Related subtopics (supporting content)

Respond with JSON:
{
  "clusters": [
    {
      "name": "Cluster Name",
      "pillar_keyword": "main keyword",
      "keywords": ["keyword1", "keyword2", ...]
    }
  ]
}
PROMPT;

        try {
            $response = $this->llm->completeJson('openai', $prompt, [], [
                'model' => 'gpt-4o',
                'temperature' => 0.3,
            ]);

            $result = $response->getJson();
            $clusters = $result['clusters'] ?? [];

            // Update keywords with cluster assignments
            foreach ($clusters as $index => $cluster) {
                $clusterId = $index + 1;

                Keyword::where('site_id', $site->id)
                    ->whereIn('keyword', $cluster['keywords'] ?? [])
                    ->update(['cluster_id' => $clusterId]);
            }

            return $clusters;
        } catch (\Exception $e) {
            Log::warning("Keyword clustering failed: {$e->getMessage()}");
            return [];
        }
    }
}
