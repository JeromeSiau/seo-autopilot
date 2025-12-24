<?php

namespace App\Services\Category;

use App\Models\Article;
use App\Models\Integration;
use App\Services\LLM\LLMManager;
use App\Services\Publisher\PublisherManager;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class CategoryMappingService
{
    public function __construct(
        private readonly LLMManager $llm,
        private readonly PublisherManager $publisherManager,
    ) {}

    /**
     * Map an article to the best matching category from the integration.
     *
     * @return array<int> Category IDs to assign
     */
    public function mapArticleToCategories(Article $article, Integration $integration): array
    {
        try {
            $categories = $this->getAvailableCategories($integration);

            if (empty($categories)) {
                Log::info('CategoryMappingService: No categories available', [
                    'integration_id' => $integration->id,
                ]);
                return [];
            }

            $keyword = $article->keyword;
            $keywordText = $keyword?->keyword ?? $article->title;
            $cluster = $keyword?->cluster_id;

            return $this->findBestCategory($keywordText, $cluster, $categories, $integration);
        } catch (\Exception $e) {
            Log::error('CategoryMappingService: Failed to map category', [
                'article_id' => $article->id,
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * Get available categories from the publisher, with caching.
     */
    private function getAvailableCategories(Integration $integration): array
    {
        $cacheKey = "integration:{$integration->id}:categories";

        return Cache::remember($cacheKey, now()->addHours(6), function () use ($integration) {
            $publisher = $this->publisherManager->getPublisher($integration);
            return $publisher->getCategories();
        });
    }

    /**
     * Use AI to find the best matching category.
     *
     * @return array<int> Category IDs
     */
    private function findBestCategory(
        string $keyword,
        ?string $cluster,
        array $categories,
        Integration $integration
    ): array {
        // Format categories for the prompt
        $categoryList = collect($categories)
            ->map(fn($cat) => "- ID: {$cat['id']}, Name: {$cat['name']}")
            ->implode("\n");

        $prompt = <<<PROMPT
You are a content categorization assistant. Given an article keyword and available blog categories, select the single most appropriate category.

Keyword: {$keyword}
Cluster/Topic: {$cluster}

Available categories:
{$categoryList}

Rules:
- Select exactly ONE category that best matches the keyword topic
- If no category is a good match, return null
- Consider the semantic meaning, not just keyword matching

Respond with JSON:
{
    "category_id": <number or null>,
    "reason": "<brief explanation>"
}
PROMPT;

        $response = $this->llm->completeJson('openai', $prompt, [], [
            'model' => 'gpt-4o-mini',
            'temperature' => 0.1,
        ]);

        $result = $response->json;

        if (!empty($result['category_id'])) {
            Log::info('CategoryMappingService: Mapped category', [
                'keyword' => $keyword,
                'category_id' => $result['category_id'],
                'reason' => $result['reason'] ?? '',
            ]);
            return [(int) $result['category_id']];
        }

        return [];
    }

    /**
     * Clear cached categories for an integration.
     */
    public function clearCache(Integration $integration): void
    {
        Cache::forget("integration:{$integration->id}:categories");
    }
}
