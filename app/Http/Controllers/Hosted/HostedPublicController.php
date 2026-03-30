<?php

namespace App\Http\Controllers\Hosted;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\HostedAuthor;
use App\Models\HostedCategory;
use App\Models\HostedPage;
use App\Models\HostedTag;
use App\Services\Hosted\HostedSiteResolver;
use App\Services\Hosted\HostedSiteViewFactory;
use Illuminate\Http\Request;

class HostedPublicController extends Controller
{
    public function __construct(
        private readonly HostedSiteResolver $resolver,
        private readonly HostedSiteViewFactory $views,
    ) {}

    public function root(Request $request)
    {
        $site = $this->resolver->resolve($request);

        if (!$site) {
            return app(\App\Http\Controllers\LandingController::class)->redirect($request);
        }

        return $this->home($request);
    }

    public function home(Request $request)
    {
        $site = $this->resolveSite($request);
        if ($redirect = $this->canonicalRedirect($site, $request)) {
            return $redirect;
        }
        if ($redirect = $this->pathRedirect($site, $request)) {
            return $redirect;
        }

        return response()->view('hosted.home', $this->views->home($site));
    }

    public function blog(Request $request)
    {
        $site = $this->resolveSite($request);
        if ($redirect = $this->canonicalRedirect($site, $request)) {
            return $redirect;
        }
        if ($redirect = $this->pathRedirect($site, $request)) {
            return $redirect;
        }

        return response()->view('hosted.blog-index', $this->views->blogIndex($site));
    }

    public function article(Request $request, string $slug)
    {
        $site = $this->resolveSite($request);
        if ($redirect = $this->canonicalRedirect($site, $request)) {
            return $redirect;
        }
        if ($redirect = $this->pathRedirect($site, $request)) {
            return $redirect;
        }
        $article = $site->articles()
            ->with(['hostedAuthor', 'hostedCategory', 'hostedTags'])
            ->where('status', Article::STATUS_PUBLISHED)
            ->where('slug', $slug)
            ->firstOrFail();

        return response()->view('hosted.article', $this->views->article($site, $article));
    }

    public function author(Request $request, string $slug)
    {
        $site = $this->resolveSite($request);
        if ($redirect = $this->canonicalRedirect($site, $request)) {
            return $redirect;
        }
        if ($redirect = $this->pathRedirect($site, $request)) {
            return $redirect;
        }

        $author = $site->hostedAuthors()
            ->where('slug', $slug)
            ->where('is_active', true)
            ->firstOrFail();

        $articles = $site->articles()
            ->with(['hostedAuthor', 'hostedCategory', 'hostedTags'])
            ->where('status', Article::STATUS_PUBLISHED)
            ->where('hosted_author_id', $author->id)
            ->latest('published_at')
            ->get();

        abort_if($articles->isEmpty(), 404);

        return response()->view('hosted.archive', $this->views->authorArchive($site, $author, $articles));
    }

    public function category(Request $request, string $slug)
    {
        $site = $this->resolveSite($request);
        if ($redirect = $this->canonicalRedirect($site, $request)) {
            return $redirect;
        }
        if ($redirect = $this->pathRedirect($site, $request)) {
            return $redirect;
        }

        $category = $site->hostedCategories()
            ->where('slug', $slug)
            ->where('is_active', true)
            ->firstOrFail();

        $articles = $site->articles()
            ->with(['hostedAuthor', 'hostedCategory', 'hostedTags'])
            ->where('status', Article::STATUS_PUBLISHED)
            ->where('hosted_category_id', $category->id)
            ->latest('published_at')
            ->get();

        abort_if($articles->isEmpty(), 404);

        return response()->view('hosted.archive', $this->views->categoryArchive($site, $category, $articles));
    }

    public function tag(Request $request, string $slug)
    {
        $site = $this->resolveSite($request);
        if ($redirect = $this->canonicalRedirect($site, $request)) {
            return $redirect;
        }
        if ($redirect = $this->pathRedirect($site, $request)) {
            return $redirect;
        }

        $tag = $site->hostedTags()
            ->where('slug', $slug)
            ->where('is_active', true)
            ->firstOrFail();

        $articles = $site->articles()
            ->with(['hostedAuthor', 'hostedCategory', 'hostedTags'])
            ->where('status', Article::STATUS_PUBLISHED)
            ->whereHas('hostedTags', fn ($query) => $query->where('hosted_tags.id', $tag->id))
            ->latest('published_at')
            ->get();

        abort_if($articles->isEmpty(), 404);

        return response()->view('hosted.archive', $this->views->tagArchive($site, $tag, $articles));
    }

    public function about(Request $request)
    {
        return $this->page($request, HostedPage::KIND_ABOUT, '/about');
    }

    public function legal(Request $request)
    {
        return $this->page($request, HostedPage::KIND_LEGAL, '/legal');
    }

    public function customPage(Request $request, string $pageSlug)
    {
        $site = $this->resolveSite($request);
        if ($redirect = $this->canonicalRedirect($site, $request)) {
            return $redirect;
        }
        if ($redirect = $this->pathRedirect($site, $request)) {
            return $redirect;
        }

        $page = $site->hostedPages
            ->first(fn (HostedPage $candidate) => $candidate->isCustom()
                && $candidate->is_published
                && $candidate->slug === $pageSlug);

        abort_if(!$page, 404);

        return response()->view('hosted.static-page', $this->views->staticPage($site, $page, $page->path()));
    }

    public function sitemap(Request $request)
    {
        $site = $this->resolveSite($request);
        if ($redirect = $this->canonicalRedirect($site, $request)) {
            return $redirect;
        }
        if ($redirect = $this->pathRedirect($site, $request)) {
            return $redirect;
        }

        return response(view('hosted.sitemap', $this->views->sitemap($site)), 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
        ]);
    }

    public function robots(Request $request)
    {
        $site = $this->resolveSite($request);
        if ($redirect = $this->canonicalRedirect($site, $request)) {
            return $redirect;
        }
        if ($redirect = $this->pathRedirect($site, $request)) {
            return $redirect;
        }

        return response(view('hosted.robots', $this->views->robots($site)), 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
        ]);
    }

    public function feed(Request $request)
    {
        $site = $this->resolveSite($request);
        if ($redirect = $this->canonicalRedirect($site, $request)) {
            return $redirect;
        }
        if ($redirect = $this->pathRedirect($site, $request)) {
            return $redirect;
        }

        return response(view('hosted.feed', $this->views->feed($site)), 200, [
            'Content-Type' => 'application/rss+xml; charset=UTF-8',
        ]);
    }

    private function page(Request $request, string $kind, string $path)
    {
        $site = $this->resolveSite($request);
        if ($redirect = $this->canonicalRedirect($site, $request)) {
            return $redirect;
        }
        if ($redirect = $this->pathRedirect($site, $request)) {
            return $redirect;
        }
        $page = $site->hostedPages->firstWhere('kind', $kind);

        abort_if(!$page || !$page->is_published, 404);

        return response()->view('hosted.static-page', $this->views->staticPage($site, $page, $page->path()));
    }

    private function resolveSite(Request $request)
    {
        $site = $this->resolver->resolve($request);
        abort_if(!$site, 404);

        return $site;
    }

    private function canonicalRedirect($site, Request $request)
    {
        if ($site->hosting?->canonical_domain
            && $site->hosting->canonical_domain !== $request->getHost()
            && $site->hosting->staging_domain === $request->getHost()) {
            return redirect()->away("https://{$site->hosting->canonical_domain}{$request->getRequestUri()}", 301);
        }

        return null;
    }

    private function pathRedirect($site, Request $request)
    {
        $path = '/' . ltrim($request->path(), '/');
        $path = $path === '//' ? '/' : $path;

        $redirect = $site->relationLoaded('hostedRedirects')
            ? $site->hostedRedirects->firstWhere('source_path', $path)
            : $site->hostedRedirects()->where('source_path', $path)->first();

        if (!$redirect) {
            return null;
        }

        $redirect->forceFill([
            'hit_count' => $redirect->hit_count + 1,
            'last_used_at' => now(),
        ])->save();

        $destination = str_starts_with($redirect->destination_url, '/')
            ? rtrim($request->getSchemeAndHttpHost(), '/') . $redirect->destination_url
            : $redirect->destination_url;

        return redirect()->away($destination, $redirect->http_status);
    }
}
