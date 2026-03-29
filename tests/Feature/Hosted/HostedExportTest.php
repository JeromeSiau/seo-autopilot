<?php

namespace Tests\Feature\Hosted;

use App\Models\Article;
use App\Models\HostedPage;
use App\Models\Site;
use App\Models\SiteHosting;
use App\Services\Hosted\HostedExportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
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
        $user = $this->createUserWithTeam();
        $site = $this->createHostedSite($user);
        $article = Article::factory()->published()->create([
            'site_id' => $site->id,
            'title' => 'ZIP article',
            'slug' => 'zip-article',
            'content' => '<p>ZIP content</p>',
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
        $this->assertNotFalse($zip->locateName('sitemap.xml'));
        $this->assertNotFalse($zip->locateName('robots.txt'));
        $this->assertNotFalse($zip->locateName('feed.xml'));
        $this->assertStringContainsString('Export Site', (string) $zip->getFromName('index.html'));

        $zip->close();
        File::delete($path);
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
                'title' => 'Home',
                'body_html' => '<p>Home body</p>',
                'meta_title' => 'Export Site',
                'meta_description' => 'Home',
                'is_published' => true,
            ],
            [
                'kind' => HostedPage::KIND_ABOUT,
                'title' => 'About',
                'body_html' => '<p>About body</p>',
                'meta_title' => 'About',
                'meta_description' => 'About',
                'is_published' => true,
            ],
            [
                'kind' => HostedPage::KIND_LEGAL,
                'title' => 'Legal',
                'body_html' => '<p>Legal body</p>',
                'meta_title' => 'Legal',
                'meta_description' => 'Legal',
                'is_published' => true,
            ],
        ]);

        return $site->fresh(['hosting', 'hostedPages']);
    }
}
