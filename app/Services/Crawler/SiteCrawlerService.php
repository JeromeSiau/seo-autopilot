<?php

namespace App\Services\Crawler;

use App\Models\Site;
use App\Models\SitePage;
use App\Services\Google\SearchConsoleService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SiteCrawlerService
{
    public function __construct(
        private readonly SearchConsoleService $searchConsole,
    ) {}

    public function crawl(Site $site): int
    {
        $pagesFound = 0;
        $pagesFound += $this->crawlSitemap($site);

        if ($site->isGscConnected()) {
            $pagesFound += $this->importFromGSC($site);
        }

        $site->update(['last_crawled_at' => now()]);

        Log::info("Site crawl completed", [
            'site_id' => $site->id,
            'pages_found' => $pagesFound,
        ]);

        return $pagesFound;
    }

    private function crawlSitemap(Site $site): int
    {
        $sitemapUrls = [
            "https://{$site->domain}/sitemap.xml",
            "https://{$site->domain}/sitemap_index.xml",
            "https://www.{$site->domain}/sitemap.xml",
        ];

        foreach ($sitemapUrls as $sitemapUrl) {
            try {
                $response = Http::timeout(10)->get($sitemapUrl);
                if (!$response->successful()) continue;

                $xml = @simplexml_load_string($response->body());
                if (!$xml) continue;

                return $this->parseSitemap($site, $xml);
            } catch (\Exception $e) {
                Log::debug("Sitemap fetch failed: {$sitemapUrl}", ['error' => $e->getMessage()]);
                continue;
            }
        }

        Log::info("No sitemap found for {$site->domain}");
        return 0;
    }

    private function parseSitemap(Site $site, \SimpleXMLElement $xml): int
    {
        $count = 0;

        if (isset($xml->sitemap)) {
            foreach ($xml->sitemap as $sitemap) {
                $sitemapUrl = (string) $sitemap->loc;
                try {
                    $response = Http::timeout(10)->get($sitemapUrl);
                    if ($response->successful()) {
                        $childXml = @simplexml_load_string($response->body());
                        if ($childXml) {
                            $count += $this->parseSitemap($site, $childXml);
                        }
                    }
                } catch (\Exception $e) {
                    continue;
                }
            }
            return $count;
        }

        foreach ($xml->url as $url) {
            $pageUrl = (string) $url->loc;

            SitePage::updateOrCreate(
                ['site_id' => $site->id, 'url' => $pageUrl],
                ['source' => 'sitemap', 'last_seen_at' => now()]
            );
            $count++;

            if ($count >= 500) break;
        }

        return $count;
    }

    private function importFromGSC(Site $site): int
    {
        try {
            $endDate = now()->subDay()->format('Y-m-d');
            $startDate = now()->subDays(28)->format('Y-m-d');

            $pages = $this->searchConsole->getSearchAnalytics(
                $site,
                $startDate,
                $endDate,
                ['page'],
                100
            );

            $count = 0;

            foreach ($pages as $row) {
                $pageUrl = $row->keys[0] ?? null;
                if (!$pageUrl) continue;

                SitePage::updateOrCreate(
                    ['site_id' => $site->id, 'url' => $pageUrl],
                    ['source' => 'gsc', 'last_seen_at' => now()]
                );
                $count++;
            }

            return $count;
        } catch (\Exception $e) {
            Log::warning("GSC import failed for site {$site->id}", ['error' => $e->getMessage()]);
            return 0;
        }
    }

    public function extractTitlesForPages(Site $site, int $limit = 50): int
    {
        $pages = $site->pages()->whereNull('title')->limit($limit)->get();
        $updated = 0;

        foreach ($pages as $page) {
            $title = $this->extractTitle($page->url);
            if ($title) {
                $page->update(['title' => $title]);
                $updated++;
            }
        }

        return $updated;
    }

    private function extractTitle(string $url): ?string
    {
        try {
            $response = Http::timeout(5)
                ->withHeaders(['User-Agent' => 'Mozilla/5.0 (compatible; SEOAutopilot/1.0)'])
                ->get($url);

            if (!$response->successful()) return null;

            if (preg_match('/<title[^>]*>(.+?)<\/title>/is', $response->body(), $matches)) {
                $title = html_entity_decode(trim($matches[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
                $title = preg_replace('/\s*[-|–]\s*[^-|–]+$/', '', $title);
                return mb_substr($title, 0, 255);
            }
        } catch (\Exception $e) {}

        return null;
    }
}
