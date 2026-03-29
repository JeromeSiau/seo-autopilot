<?php

namespace App\Services\Hosted;

use App\Models\Article;
use App\Models\HostedPage;
use App\Models\Site;
use Illuminate\Support\Facades\File;
use ZipArchive;

class HostedExportService
{
    public function __construct(
        private readonly HostedSiteViewFactory $views,
    ) {}

    public function renderArticleHtml(Article $article): string
    {
        $site = $article->site->loadMissing(['hosting', 'hostedPages']);
        $baseUrl = $site->getPublicUrlAttribute();

        return view('hosted.article', $this->views->article($site, $article, $baseUrl))->render();
    }

    public function createSiteExport(Site $site, string $targetPath): string
    {
        $site->loadMissing(['hosting', 'hostedPages']);
        File::ensureDirectoryExists(dirname($targetPath));
        @unlink($targetPath);

        $zip = new ZipArchive();
        $zip->open($targetPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        $baseUrl = '';

        $zip->addFromString('index.html', view('hosted.home', $this->views->home($site, $baseUrl))->render());
        $zip->addFromString('blog/index.html', view('hosted.blog-index', $this->views->blogIndex($site, $baseUrl))->render());

        foreach ($site->articles()->where('status', Article::STATUS_PUBLISHED)->get() as $article) {
            $zip->addFromString(
                "blog/{$article->slug}.html",
                view('hosted.article', $this->views->article($site, $article, $baseUrl))->render()
            );
        }

        foreach ($site->hostedPages as $page) {
            $relative = match ($page->kind) {
                HostedPage::KIND_HOME => null,
                HostedPage::KIND_ABOUT => 'about/index.html',
                HostedPage::KIND_LEGAL => 'legal/index.html',
                default => null,
            };

            if ($relative) {
                $path = '/' . trim(dirname($relative), '.');
                $zip->addFromString(
                    $relative,
                    view('hosted.static-page', $this->views->staticPage($site, $page, $path === '/' ? '' : $path, $baseUrl))->render()
                );
            }
        }

        $zip->addFromString('sitemap.xml', view('hosted.sitemap', $this->views->sitemap($site, $baseUrl))->render());
        $zip->addFromString('robots.txt', view('hosted.robots', $this->views->robots($site, $baseUrl))->render());
        $zip->addFromString('feed.xml', view('hosted.feed', $this->views->feed($site, $baseUrl))->render());
        $zip->close();

        return $targetPath;
    }
}
