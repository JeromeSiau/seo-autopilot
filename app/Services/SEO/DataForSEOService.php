<?php

namespace App\Services\SEO;

use App\Services\SEO\DTOs\KeywordData;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DataForSEOService
{
    private const BASE_URL = 'https://api.dataforseo.com/v3';

    private string $login;
    private string $password;

    public function __construct()
    {
        $this->login = config('services.dataforseo.login');
        $this->password = config('services.dataforseo.password');

        if (!$this->login || !$this->password) {
            throw new \RuntimeException('DataForSEO credentials not configured');
        }
    }

    /**
     * Get keyword data for multiple keywords.
     */
    public function getKeywordData(
        array $keywords,
        string $language = 'en',
        string $location = 'United States',
    ): Collection {
        $locationCode = $this->getLocationCode($location);
        $languageCode = $this->getLanguageCode($language);

        // DataForSEO accepts max 1000 keywords per request
        $chunks = array_chunk($keywords, 1000);
        $results = collect();

        foreach ($chunks as $chunk) {
            $data = $this->request('POST', '/keywords_data/google_ads/search_volume/live', [
                [
                    'keywords' => $chunk,
                    'location_code' => $locationCode,
                    'language_code' => $languageCode,
                ],
            ]);

            foreach ($data as $task) {
                foreach ($task['result'] ?? [] as $item) {
                    $results->push(KeywordData::fromDataForSEO($item));
                }
            }
        }

        return $results;
    }

    /**
     * Get keyword suggestions/ideas.
     */
    public function getKeywordSuggestions(
        string $seedKeyword,
        string $language = 'en',
        string $location = 'United States',
        int $limit = 100,
    ): Collection {
        $locationCode = $this->getLocationCode($location);
        $languageCode = $this->getLanguageCode($language);

        $data = $this->request('POST', '/keywords_data/google_ads/keywords_for_keywords/live', [
            [
                'keywords' => [$seedKeyword],
                'location_code' => $locationCode,
                'language_code' => $languageCode,
                'limit' => $limit,
            ],
        ]);

        $results = collect();

        foreach ($data as $task) {
            foreach ($task['result'] ?? [] as $item) {
                $results->push(KeywordData::fromDataForSEO($item));
            }
        }

        return $results;
    }

    /**
     * Get related keywords.
     */
    public function getRelatedKeywords(
        string $keyword,
        string $language = 'en',
        string $location = 'United States',
        int $limit = 50,
    ): Collection {
        $locationCode = $this->getLocationCode($location);
        $languageCode = $this->getLanguageCode($language);

        $data = $this->request('POST', '/dataforseo_labs/google/related_keywords/live', [
            [
                'keyword' => $keyword,
                'location_code' => $locationCode,
                'language_code' => $languageCode,
                'limit' => $limit,
                'include_seed_keyword' => false,
            ],
        ]);

        $results = collect();

        foreach ($data as $task) {
            foreach ($task['result'] ?? [] as $result) {
                foreach ($result['items'] ?? [] as $item) {
                    if (isset($item['keyword_data'])) {
                        $results->push(KeywordData::fromDataForSEO($item['keyword_data']));
                    }
                }
            }
        }

        return $results;
    }

    /**
     * Get SERP data for a keyword.
     */
    public function getSerpResults(
        string $keyword,
        string $language = 'en',
        string $location = 'United States',
        int $depth = 10,
    ): Collection {
        $locationCode = $this->getLocationCode($location);
        $languageCode = $this->getLanguageCode($language);

        $data = $this->request('POST', '/serp/google/organic/live/regular', [
            [
                'keyword' => $keyword,
                'location_code' => $locationCode,
                'language_code' => $languageCode,
                'depth' => $depth,
            ],
        ]);

        $results = collect();

        foreach ($data as $task) {
            foreach ($task['result'] ?? [] as $result) {
                foreach ($result['items'] ?? [] as $item) {
                    if ($item['type'] === 'organic') {
                        $results->push([
                            'position' => $item['rank_absolute'] ?? 0,
                            'url' => $item['url'] ?? '',
                            'title' => $item['title'] ?? '',
                            'description' => $item['description'] ?? '',
                            'domain' => $item['domain'] ?? '',
                        ]);
                    }
                }
            }
        }

        return $results;
    }

    /**
     * Get keyword difficulty.
     */
    public function getKeywordDifficulty(
        array $keywords,
        string $language = 'en',
        string $location = 'United States',
    ): Collection {
        $locationCode = $this->getLocationCode($location);
        $languageCode = $this->getLanguageCode($language);

        $data = $this->request('POST', '/dataforseo_labs/google/bulk_keyword_difficulty/live', [
            [
                'keywords' => $keywords,
                'location_code' => $locationCode,
                'language_code' => $languageCode,
            ],
        ]);

        $results = collect();

        foreach ($data as $task) {
            foreach ($task['result'] ?? [] as $result) {
                foreach ($result['items'] ?? [] as $item) {
                    $results->put($item['keyword'] ?? '', [
                        'keyword' => $item['keyword'] ?? '',
                        'difficulty' => $item['keyword_difficulty'] ?? 0,
                    ]);
                }
            }
        }

        return $results;
    }

    /**
     * Make an API request.
     */
    private function request(string $method, string $endpoint, array $body = []): array
    {
        $url = self::BASE_URL . $endpoint;

        $response = Http::withBasicAuth($this->login, $this->password)
            ->timeout(60)
            ->post($url, $body);

        if (!$response->successful()) {
            Log::error('DataForSEO API error', [
                'status' => $response->status(),
                'body' => $response->body(),
                'endpoint' => $endpoint,
            ]);
            throw new \RuntimeException('DataForSEO API error: ' . $response->body());
        }

        $data = $response->json();

        if (($data['status_code'] ?? 0) !== 20000) {
            throw new \RuntimeException('DataForSEO error: ' . ($data['status_message'] ?? 'Unknown error'));
        }

        return $data['tasks'] ?? [];
    }

    /**
     * Get location code from name.
     */
    private function getLocationCode(string $location): int
    {
        // Common locations - extend as needed
        $locations = [
            'United States' => 2840,
            'United Kingdom' => 2826,
            'Canada' => 2124,
            'Australia' => 2036,
            'France' => 2250,
            'Germany' => 2276,
            'Spain' => 2724,
            'Italy' => 2380,
            'Brazil' => 2076,
            'Mexico' => 2484,
            'Japan' => 2392,
        ];

        return $locations[$location] ?? 2840;
    }

    /**
     * Get language code.
     */
    private function getLanguageCode(string $language): string
    {
        // Map common language codes
        $languages = [
            'en' => 'en',
            'fr' => 'fr',
            'de' => 'de',
            'es' => 'es',
            'it' => 'it',
            'pt' => 'pt',
            'ja' => 'ja',
            'english' => 'en',
            'french' => 'fr',
            'german' => 'de',
            'spanish' => 'es',
        ];

        return $languages[strtolower($language)] ?? 'en';
    }
}
