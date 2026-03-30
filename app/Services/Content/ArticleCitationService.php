<?php

namespace App\Services\Content;

use App\Models\Article;
use App\Models\BrandAsset;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class ArticleCitationService
{
    public function syncReferences(Article $article, array $referenceUrls = [], ?Collection $brandAssets = null): void
    {
        $article->citations()->delete();

        $records = [];
        $seenUrls = [];

        $brandAssets ??= $article->site->brandAssets()->active()->orderByDesc('priority')->get();

        foreach ($brandAssets->take(4) as $asset) {
            $records[] = [
                'source_type' => 'brand',
                'title' => $asset->title,
                'url' => $asset->source_url,
                'domain' => $this->extractDomain($asset->source_url),
                'excerpt' => Str::limit(trim(preg_replace('/\s+/', ' ', $asset->content)), 240),
                'metadata' => [
                    'asset_type' => $asset->type,
                    'priority' => $asset->priority,
                ],
            ];

            if ($asset->source_url) {
                $seenUrls[$asset->source_url] = true;
            }
        }

        foreach (array_slice(array_values(array_filter(array_unique($referenceUrls))), 0, 8) as $url) {
            if (isset($seenUrls[$url])) {
                continue;
            }

            $records[] = [
                'source_type' => 'serp',
                'title' => $this->humanizeUrl($url),
                'url' => $url,
                'domain' => $this->extractDomain($url),
                'excerpt' => null,
                'metadata' => [],
            ];
        }

        if (!empty($records)) {
            $article->citations()->createMany($records);
        }
    }

    private function extractDomain(?string $url): ?string
    {
        if (!$url) {
            return null;
        }

        return parse_url($url, PHP_URL_HOST) ?: null;
    }

    private function humanizeUrl(string $url): string
    {
        $host = parse_url($url, PHP_URL_HOST) ?: $url;
        $path = parse_url($url, PHP_URL_PATH) ?: '';
        $slug = trim($path, '/');

        if ($slug === '') {
            return $host;
        }

        return $host . ' / ' . Str::headline(str_replace('/', ' ', $slug));
    }
}
