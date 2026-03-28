<?php

namespace App\Services\Crawler;

use App\Models\Site;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

class SiteIndexService
{
    public function indexSite(Site $site, bool $delta = false): array
    {
        $seedUrls = $this->buildSeedUrls($site);
        $command = [
            'uv', 'run',
            '--project', base_path('agents-python'),
            'site-indexer',
            '--siteId', (string) $site->id,
            '--siteUrl', $site->url,
            '--maxPages', '500',
            '--seedUrls', json_encode($seedUrls, JSON_THROW_ON_ERROR),
            '--storagePath', $this->getStorageDirectory(),
        ];

        if ($delta) {
            $command[] = '--delta';
        }

        Log::info('SiteIndexService: Starting indexation', [
            'site_id' => $site->id,
            'domain' => $site->domain,
            'delta' => $delta,
            'seed_urls_count' => count($seedUrls),
        ]);

        $result = Process::path(base_path('agents-python'))
            ->timeout(600) // 10 minutes
            ->run($command);

        if (!$result->successful()) {
            Log::error('SiteIndexService: Indexation failed', [
                'site_id' => $site->id,
                'error' => $result->errorOutput(),
            ]);
            throw new \RuntimeException('Site indexation failed: ' . $result->errorOutput());
        }

        // Extract the last line which contains the JSON output
        $lines = array_filter(explode("\n", trim($result->output())));
        $jsonLine = end($lines);
        $output = json_decode($jsonLine, true);
        if (!is_array($output)) {
            Log::error('SiteIndexService: Invalid JSON output', [
                'site_id' => $site->id,
                'output' => $result->output(),
            ]);
            throw new \RuntimeException('Invalid JSON output from site-indexer');
        }

        $site->update(['last_crawled_at' => now()]);

        Log::info('SiteIndexService: Indexation completed', [
            'site_id' => $site->id,
            'pages_indexed' => $output['pages_indexed'] ?? 0,
        ]);

        return $output;
    }

    public function getIndexPath(Site $site): string
    {
        return $this->getStorageDirectory() . DIRECTORY_SEPARATOR . "site_{$site->id}.sqlite";
    }

    public function hasIndex(Site $site): bool
    {
        return file_exists($this->getIndexPath($site));
    }

    public function deleteIndex(Site $site): bool
    {
        $path = $this->getIndexPath($site);
        if (file_exists($path)) {
            return unlink($path);
        }
        return true;
    }

    /**
     * Récupère toutes les pages depuis l'index SQLite.
     */
    public function getAllPages(Site $site): array
    {
        $path = $this->getIndexPath($site);
        if (!file_exists($path)) {
            return [];
        }

        try {
            $db = new \SQLite3($path, SQLITE3_OPEN_READONLY);
            $result = $db->query('SELECT url, title, h1, meta_description, category, tags, internal_links FROM pages ORDER BY updated_at DESC');

            $pages = [];
            while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                $pages[] = $row;
            }

            $db->close();
            return $pages;
        } catch (\Exception $e) {
            Log::error('SiteIndexService: Failed to read SQLite', ['error' => $e->getMessage()]);
            return [];
        }
    }

    public function buildSeedUrls(Site $site, int $limit = 100): array
    {
        $urls = $site->pages()
            ->whereNotNull('url')
            ->orderByDesc('last_seen_at')
            ->limit($limit)
            ->pluck('url')
            ->filter(fn (?string $url) => filled($url))
            ->map(fn (string $url) => rtrim($url, '/'))
            ->unique()
            ->values()
            ->all();

        if (empty($urls)) {
            return [$site->url];
        }

        return $urls;
    }

    private function getStorageDirectory(): string
    {
        return config('services.site_indexer.storage_path', storage_path('indexes'));
    }
}
