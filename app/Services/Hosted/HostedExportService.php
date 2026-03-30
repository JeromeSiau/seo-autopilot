<?php

namespace App\Services\Hosted;

use App\Models\Article;
use App\Models\Site;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

class HostedExportService
{
    public function __construct(
        private readonly HostedSiteViewFactory $views,
    ) {}

    public function renderArticleHtml(Article $article): string
    {
        $site = $article->site->loadMissing(['hosting', 'hostedPages.socialImageAsset', 'hostedAuthors', 'hostedCategories', 'hostedTags', 'hostedAssets', 'hostedNavigationItems']);
        $article->loadMissing(['hostedAuthor', 'hostedCategory', 'hostedTags']);
        $baseUrl = $site->getPublicUrlAttribute();

        return view('hosted.article', $this->views->article($site, $article, $baseUrl))->render();
    }

    public function createSiteExport(Site $site, string $targetPath): string
    {
        $site->loadMissing(['hosting', 'hostedPages.socialImageAsset', 'hostedAuthors', 'hostedCategories', 'hostedTags', 'hostedAssets', 'hostedNavigationItems']);
        File::ensureDirectoryExists(dirname($targetPath));
        @unlink($targetPath);

        $zip = new ZipArchive();
        $zip->open($targetPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        $baseUrl = '';

        $zip->addFromString('index.html', view('hosted.home', $this->views->home($site, $baseUrl))->render());
        $zip->addFromString('blog/index.html', view('hosted.blog-index', $this->views->blogIndex($site, $baseUrl))->render());

        foreach ($site->articles()->with(['hostedAuthor', 'hostedCategory', 'hostedTags'])->where('status', Article::STATUS_PUBLISHED)->get() as $article) {
            $zip->addFromString(
                "blog/{$article->slug}.html",
                view('hosted.article', $this->views->article($site, $article, $baseUrl))->render()
            );
        }

        foreach ($site->hostedPages as $page) {
            if (!$page->is_published) {
                continue;
            }

            $relative = $page->exportPath();

            if ($relative) {
                $path = $page->path();
                $zip->addFromString(
                    $relative,
                    view('hosted.static-page', $this->views->staticPage($site, $page, $path, $baseUrl))->render()
                );
            }
        }

        foreach ($site->hostedAuthors()->where('is_active', true)->get() as $author) {
            $articles = $site->articles()
                ->with(['hostedAuthor', 'hostedCategory', 'hostedTags'])
                ->where('status', Article::STATUS_PUBLISHED)
                ->where('hosted_author_id', $author->id)
                ->latest('published_at')
                ->get();

            if ($articles->isEmpty()) {
                continue;
            }

            $zip->addFromString(
                $author->exportPath(),
                view('hosted.archive', $this->views->authorArchive($site, $author, $articles, $baseUrl))->render()
            );
        }

        foreach ($site->hostedCategories()->where('is_active', true)->get() as $category) {
            $articles = $site->articles()
                ->with(['hostedAuthor', 'hostedCategory', 'hostedTags'])
                ->where('status', Article::STATUS_PUBLISHED)
                ->where('hosted_category_id', $category->id)
                ->latest('published_at')
                ->get();

            if ($articles->isEmpty()) {
                continue;
            }

            $zip->addFromString(
                $category->exportPath(),
                view('hosted.archive', $this->views->categoryArchive($site, $category, $articles, $baseUrl))->render()
            );
        }

        foreach ($site->hostedTags()->where('is_active', true)->get() as $tag) {
            $articles = $site->articles()
                ->with(['hostedAuthor', 'hostedCategory', 'hostedTags'])
                ->where('status', Article::STATUS_PUBLISHED)
                ->whereHas('hostedTags', fn ($query) => $query->where('hosted_tags.id', $tag->id))
                ->latest('published_at')
                ->get();

            if ($articles->isEmpty()) {
                continue;
            }

            $zip->addFromString(
                $tag->exportPath(),
                view('hosted.archive', $this->views->tagArchive($site, $tag, $articles, $baseUrl))->render()
            );
        }

        foreach ($site->hostedAssets as $asset) {
            if (!$asset->is_active || !Storage::disk($asset->disk)->exists($asset->path)) {
                continue;
            }

            $zip->addFromString($asset->export_path, Storage::disk($asset->disk)->get($asset->path));
        }

        foreach ($site->articles()->where('status', Article::STATUS_PUBLISHED)->get() as $article) {
            $featured = $article->images['featured']['local_path'] ?? null;

            if (!$featured || !Storage::disk('public')->exists($featured)) {
                continue;
            }

            $zip->addFromString('storage/' . ltrim($featured, '/'), Storage::disk('public')->get($featured));
        }

        $zip->addFromString('sitemap.xml', view('hosted.sitemap', $this->views->sitemap($site, $baseUrl))->render());
        $zip->addFromString('robots.txt', view('hosted.robots', $this->views->robots($site, $baseUrl))->render());
        $zip->addFromString('feed.xml', view('hosted.feed', $this->views->feed($site, $baseUrl))->render());
        $zip->close();

        return $targetPath;
    }
}
