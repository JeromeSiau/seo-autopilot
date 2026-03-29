<?php

namespace App\Http\Controllers\Hosted;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\HostedPage;
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

        return response()->view('hosted.home', $this->views->home($site));
    }

    public function blog(Request $request)
    {
        $site = $this->resolveSite($request);
        if ($redirect = $this->canonicalRedirect($site, $request)) {
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
        $article = $site->articles()
            ->where('status', Article::STATUS_PUBLISHED)
            ->where('slug', $slug)
            ->firstOrFail();

        return response()->view('hosted.article', $this->views->article($site, $article));
    }

    public function about(Request $request)
    {
        return $this->page($request, HostedPage::KIND_ABOUT, '/about');
    }

    public function legal(Request $request)
    {
        return $this->page($request, HostedPage::KIND_LEGAL, '/legal');
    }

    public function sitemap(Request $request)
    {
        $site = $this->resolveSite($request);
        if ($redirect = $this->canonicalRedirect($site, $request)) {
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
        $page = $site->hostedPages->firstWhere('kind', $kind);

        abort_if(!$page || !$page->is_published, 404);

        return response()->view('hosted.static-page', $this->views->staticPage($site, $page, $path));
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
}
