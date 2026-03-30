<?php

namespace Tests\Feature\Hosted;

use App\Models\Article;
use App\Models\HostedPage;
use App\Models\Site;
use App\Models\SiteHosting;
use App\Services\Hosted\HostedExportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesTeams;
use Tests\TestCase;
use ZipArchive;

class HostedExportTest extends TestCase
{
    use CreatesTeams;
    use RefreshDatabase;

    public function test_article_html_export_downloads_markup(): void
    {
        $user = $this->createUserWithTeam();
        $site = $this->createHostedSite($user);
        $article = Article::factory()->published()->create([
            'site_id' => $site->id,
            'title' => 'Exportable article',
            'slug' => 'exportable-article',
            'content' => '<h2>Intro</h2><p>Export body</p>',
        ]);

        $response = $this->actingAs($user)->get(route('articles.export-html', $article));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/html; charset=UTF-8');
        $response->assertHeader('Content-Disposition', 'attachment; filename="exportable-article.html"');
        $response->assertSee('Exportable article');
        $response->assertSee('Export body', false);
    }

    public function test_site_export_service_creates_zip_with_expected_files(): void
    {
        Storage::fake('public');

        $user = $this->createUserWithTeam();
        $site = $this->createHostedSite($user);
        $author = $site->hostedAuthors()->create([
            'name' => 'Jane Doe',
            'slug' => 'jane-doe',
            'sort_order' => 10,
            'is_active' => true,
        ]);
        $category = $site->hostedCategories()->create([
            'name' => 'SEO Systems',
            'slug' => 'seo-systems',
            'sort_order' => 10,
            'is_active' => true,
        ]);
        $tag = $site->hostedTags()->create([
            'name' => 'AI Overviews',
            'slug' => 'ai-overviews',
            'sort_order' => 10,
            'is_active' => true,
        ]);
        $article = Article::factory()->published()->create([
            'site_id' => $site->id,
            'hosted_author_id' => $author->id,
            'hosted_category_id' => $category->id,
            'title' => 'ZIP article',
            'slug' => 'zip-article',
            'content' => '<p>ZIP content</p>',
        ]);
        $article->hostedTags()->attach($tag->id);
        Storage::disk('public')->put('hosted-assets/site-' . $site->id . '/logo.png', 'logo-binary');
        $asset = $site->hostedAssets()->create([
            'type' => 'logo',
            'name' => 'Export logo',
            'disk' => 'public',
            'path' => 'hosted-assets/site-' . $site->id . '/logo.png',
            'mime_type' => 'image/png',
            'size_bytes' => 11,
            'alt_text' => 'Export logo',
            'is_active' => true,
        ]);
        $site->hosting()->update([
            'theme_settings' => array_replace($site->hosting->theme_settings ?? [], [
                'logo_asset_id' => $asset->id,
                'social_image_asset_id' => $asset->id,
            ]),
        ]);
        $site->hostedPages()->where('slug', 'pricing')->update([
            'sections' => [
                [
                    'type' => HostedPage::SECTION_CALLOUT,
                    'eyebrow' => 'Scale safely',
                    'title' => 'Built for hosted growth',
                    'body' => 'Use reusable sections to shape the hosted lane.',
                    'cta_label' => 'Read the blog',
                    'cta_href' => '/blog',
                ],
            ],
        ]);
        $site->hostedNavigationItems()->createMany([
            [
                'placement' => 'header',
                'type' => 'path',
                'label' => 'Pricing',
                'path' => '/pricing',
                'open_in_new_tab' => false,
                'is_active' => true,
                'sort_order' => 120,
            ],
            [
                'placement' => 'footer',
                'type' => 'url',
                'label' => 'GitHub',
                'url' => 'https://github.com/acme/export-site',
                'open_in_new_tab' => true,
                'is_active' => true,
                'sort_order' => 220,
            ],
        ]);

        $path = storage_path('framework/testing/site-export.zip');
        File::delete($path);

        app(HostedExportService::class)->createSiteExport($site->fresh(['hosting', 'hostedPages']), $path);

        $this->assertFileExists($path);

        $zip = new ZipArchive();
        $zip->open($path);

        $this->assertNotFalse($zip->locateName('index.html'));
        $this->assertNotFalse($zip->locateName('blog/index.html'));
        $this->assertNotFalse($zip->locateName("blog/{$article->slug}.html"));
        $this->assertNotFalse($zip->locateName('authors/jane-doe/index.html'));
        $this->assertNotFalse($zip->locateName('categories/seo-systems/index.html'));
        $this->assertNotFalse($zip->locateName('tags/ai-overviews/index.html'));
        $this->assertNotFalse($zip->locateName('storage/hosted-assets/site-' . $site->id . '/logo.png'));
        $this->assertNotFalse($zip->locateName('pricing/index.html'));
        $this->assertNotFalse($zip->locateName('sitemap.xml'));
        $this->assertNotFalse($zip->locateName('robots.txt'));
        $this->assertNotFalse($zip->locateName('feed.xml'));
        $this->assertStringContainsString('Export Site', (string) $zip->getFromName('index.html'));
        $this->assertStringContainsString('ZIP article', (string) $zip->getFromName('authors/jane-doe/index.html'));
        $this->assertStringContainsString('pricing/index.html', (string) $zip->getFromName('index.html'));
        $this->assertStringContainsString('https://github.com/acme/export-site', (string) $zip->getFromName('index.html'));
        $this->assertStringContainsString('Built for hosted growth', (string) $zip->getFromName('pricing/index.html'));
        $this->assertStringContainsString('blog/index.html', (string) $zip->getFromName('pricing/index.html'));

        $zip->close();
        File::delete($path);
    }

    public function test_export_request_tracks_runs_and_deploy_events(): void
    {
        $user = $this->createUserWithTeam();
        $site = $this->createHostedSite($user);

        $this->actingAs($user)
            ->post(route('sites.export-site', $site))
            ->assertRedirect();

        $hosting = $site->fresh('hosting')->hosting;

        $this->assertNotNull($hosting);
        $this->assertDatabaseHas('hosted_export_runs', [
            'site_hosting_id' => $hosting->id,
            'status' => 'completed',
        ]);
        $this->assertDatabaseHas('hosted_deploy_events', [
            'site_hosting_id' => $hosting->id,
            'type' => 'export_requested',
            'status' => 'info',
        ]);
        $this->assertDatabaseHas('hosted_deploy_events', [
            'site_hosting_id' => $hosting->id,
            'type' => 'export_completed',
            'status' => 'success',
        ]);
    }

    private function createHostedSite($user): Site
    {
        $site = $this->createSiteForUser($user, [
            'mode' => Site::MODE_HOSTED,
            'name' => 'Export Site',
            'domain' => 'export.test',
            'language' => 'en',
            'business_description' => 'Static export ready.',
        ]);

        $site->hosting()->create([
            'staging_domain' => 'preview.export.test',
            'canonical_domain' => 'preview.export.test',
            'domain_status' => SiteHosting::DOMAIN_STATUS_ACTIVE,
            'ssl_status' => SiteHosting::SSL_STATUS_ACTIVE,
            'template_key' => SiteHosting::TEMPLATE_EDITORIAL,
            'theme_settings' => [
                'brand_name' => 'Export Site',
            ],
        ]);

        $site->hostedPages()->createMany([
            [
                'kind' => HostedPage::KIND_HOME,
                'slug' => 'home',
                'title' => 'Home',
                'navigation_label' => 'Home',
                'body_html' => '<p>Home body</p>',
                'meta_title' => 'Export Site',
                'meta_description' => 'Home',
                'show_in_navigation' => true,
                'sort_order' => 0,
                'is_published' => true,
            ],
            [
                'kind' => HostedPage::KIND_ABOUT,
                'slug' => 'about',
                'title' => 'About',
                'navigation_label' => 'About',
                'body_html' => '<p>About body</p>',
                'meta_title' => 'About',
                'meta_description' => 'About',
                'show_in_navigation' => true,
                'sort_order' => 200,
                'is_published' => true,
            ],
            [
                'kind' => HostedPage::KIND_LEGAL,
                'slug' => 'legal',
                'title' => 'Legal',
                'navigation_label' => 'Legal',
                'body_html' => '<p>Legal body</p>',
                'meta_title' => 'Legal',
                'meta_description' => 'Legal',
                'show_in_navigation' => false,
                'sort_order' => 900,
                'is_published' => true,
            ],
            [
                'kind' => HostedPage::KIND_CUSTOM,
                'slug' => 'pricing',
                'title' => 'Pricing',
                'navigation_label' => 'Pricing',
                'body_html' => '<p>Pricing body</p>',
                'meta_title' => 'Pricing',
                'meta_description' => 'Pricing',
                'show_in_navigation' => true,
                'sort_order' => 350,
                'is_published' => true,
            ],
        ]);

        return $site->fresh(['hosting', 'hostedPages']);
    }
}
