<?php

namespace App\Services\Crawler;

use App\Models\Site;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Log;

class SiteIndexService
{
    private const AGENT_PATH = 'agents/site-indexer/index.js';

    public function indexSite(Site $site, bool $delta = false): array
    {
        $command = [
            'node',
            base_path(self::AGENT_PATH),
            '--siteId', (string) $site->id,
            '--siteUrl', "https://{$site->domain}",
            '--maxPages', '500',
        ];

        if ($delta) {
            $command[] = '--delta';
        }

        Log::info('SiteIndexService: Starting indexation', [
            'site_id' => $site->id,
            'domain' => $site->domain,
            'delta' => $delta,
        ]);

        $result = Process::path(base_path('agents'))
            ->timeout(600) // 10 minutes
            ->run($command);

        if (!$result->successful()) {
            Log::error('SiteIndexService: Indexation failed', [
                'site_id' => $site->id,
                'error' => $result->errorOutput(),
            ]);
            throw new \RuntimeException('Site indexation failed: ' . $result->errorOutput());
        }

        $output = json_decode($result->output(), true);
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
        return storage_path("indexes/site_{$site->id}.sqlite");
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
            $result = $db->query('SELECT url, title, h1, meta_description, category, tags, inbound_links_count FROM pages ORDER BY crawled_at DESC');

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
}
