<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Jobs\GenerateHostedSiteExportJob;
use App\Models\Article;
use App\Models\Site;
use App\Services\Hosted\HostedExportService;
use App\Services\Hosted\HostedSiteService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class HostedSiteController extends Controller
{
    public function __construct(
        private readonly HostedSiteService $hosting,
        private readonly HostedExportService $exports,
    ) {}

    public function show(Site $site): Response
    {
        $this->authorize('view', $site);
        abort_unless($site->isHosted(), 404);

        $site->load(['hosting', 'hostedPages', 'activeIntegration']);

        return Inertia::render('Sites/Hosting', [
            'site' => [
                ...(new \App\Http\Resources\SiteResource($site))->toArray(request()),
                'hosting' => $site->hosting,
                'hosted_pages' => $site->hostedPages,
                'site_export_available' => File::exists($this->hosting->hostedSiteExportPath($site)),
                'dns_expectation' => $site->hosting?->custom_domain
                    ? $this->hosting->expectedDnsRecords($site->hosting->custom_domain)
                    : null,
            ],
        ]);
    }

    public function provisionStaging(Site $site): RedirectResponse
    {
        $this->authorize('update', $site);
        $this->hosting->provisionStaging($site);

        return back()->with('success', 'Staging domain provisioned.');
    }

    public function storeDomain(Request $request, Site $site): RedirectResponse
    {
        $this->authorize('update', $site);

        $validated = $request->validate([
            'custom_domain' => ['required', 'string', 'max:255'],
        ]);

        $this->hosting->updateCustomDomain($site, $validated['custom_domain']);

        return back()->with('success', 'Custom domain saved.');
    }

    public function verifyDns(Site $site): RedirectResponse
    {
        $this->authorize('update', $site);
        $result = $this->hosting->verifyCustomDomain($site);

        return back()->with(
            $result['matched'] ? 'success' : 'error',
            $result['matched'] ? 'DNS verified and certificate request started.' : 'DNS does not match the expected target yet.'
        );
    }

    public function updateTheme(Request $request, Site $site): RedirectResponse
    {
        $this->authorize('update', $site);

        $validated = $request->validate([
            'template_key' => ['required', Rule::in(['editorial', 'magazine', 'minimal'])],
            'theme_settings' => ['required', 'array'],
        ]);

        $this->hosting->updateTheme($site, $validated['theme_settings'], $validated['template_key']);

        return back()->with('success', 'Theme updated.');
    }

    public function updatePage(Request $request, Site $site, string $kind): RedirectResponse
    {
        $this->authorize('update', $site);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'body_html' => ['nullable', 'string'],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:500'],
            'is_published' => ['required', 'boolean'],
        ]);

        $this->hosting->updatePage($site, $kind, $validated);

        return back()->with('success', 'Hosted page updated.');
    }

    public function exportSite(Site $site): RedirectResponse
    {
        $this->authorize('view', $site);
        abort_unless($site->isHosted(), 404);

        GenerateHostedSiteExportJob::dispatch($site);

        return back()->with('success', 'Site export generation started.');
    }

    public function downloadSiteExport(Site $site)
    {
        $this->authorize('view', $site);
        abort_unless($site->isHosted(), 404);

        $path = $this->hosting->hostedSiteExportPath($site);
        abort_unless(File::exists($path), 404);

        return response()->download($path, "site-{$site->id}-export.zip");
    }

    public function downloadArticleHtml(Article $article)
    {
        $this->authorize('view', $article);

        $html = $this->exports->renderArticleHtml($article->loadMissing(['site.hosting', 'site.hostedPages']));

        return response($html, 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $article->slug . '.html"',
        ]);
    }
}
