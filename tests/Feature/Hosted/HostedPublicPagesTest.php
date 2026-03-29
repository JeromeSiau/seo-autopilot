<?php

namespace Tests\Feature\Hosted;

use App\Models\Article;
use App\Models\HostedPage;
use App\Models\Site;
use App\Models\SiteHosting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTeams;
use Tests\TestCase;

class HostedPublicPagesTest extends TestCase
{
    use CreatesTeams;
    use RefreshDatabase;

    public function test_hosted_domain_serves_public_pages_and_articles(): void
    {
        config()->set('services.hosted.primary_domains', ['app.example.test']);

        $site = $this->createHostedSite();
        $article = Article::factory()->published()->create([
            'site_id' => $site->id,
            'title' => 'Hosted article',
            'slug' => 'hosted-article',
            'content' => '<p>Hosted content</p>',
            'meta_description' => 'Hosted article description',
        ]);

        $homeResponse = $this->get('http://preview.acme.test/');
        $homeResponse->assertOk()->assertSee('Acme Hosted');

        $articleResponse = $this->get("http://preview.acme.test/blog/{$article->slug}");
        $articleResponse->assertOk()
            ->assertSee('Hosted article')
            ->assertSee('Hosted content', false);

        $aboutResponse = $this->get('http://preview.acme.test/about');
        $aboutResponse->assertOk()->assertSee('About Acme');
    }

    public function test_staging_domain_redirects_to_active_custom_domain(): void
    {
        config()->set('services.hosted.primary_domains', ['app.example.test']);

        $site = $this->createHostedSite([
            'custom_domain' => 'blog.acme.test',
            'canonical_domain' => 'blog.acme.test',
            'domain_status' => SiteHosting::DOMAIN_STATUS_ACTIVE,
        ]);

        $response = $this->get('http://preview.acme.test/about');

        $response->assertRedirect('https://blog.acme.test/about');
    }

    private function createHostedSite(array $hostingAttributes = []): Site
    {
        $user = $this->createUserWithTeam();
        $site = $this->createSiteForUser($user, [
            'mode' => Site::MODE_HOSTED,
            'name' => 'Acme Hosted',
            'domain' => 'acme.test',
            'language' => 'en',
            'business_description' => 'Hosted publication for Acme.',
        ]);

        $site->hosting()->create(array_merge([
            'staging_domain' => 'preview.acme.test',
            'canonical_domain' => 'preview.acme.test',
            'domain_status' => SiteHosting::DOMAIN_STATUS_ACTIVE,
            'ssl_status' => SiteHosting::SSL_STATUS_ACTIVE,
            'template_key' => SiteHosting::TEMPLATE_EDITORIAL,
            'theme_settings' => [
                'brand_name' => 'Acme Hosted',
            ],
        ], $hostingAttributes));

        $site->hostedPages()->createMany([
            [
                'kind' => HostedPage::KIND_HOME,
                'title' => 'Home',
                'body_html' => '<p>Welcome to Acme.</p>',
                'meta_title' => 'Acme Hosted',
                'meta_description' => 'Home page',
                'is_published' => true,
            ],
            [
                'kind' => HostedPage::KIND_ABOUT,
                'title' => 'About Acme',
                'body_html' => '<p>About the brand.</p>',
                'meta_title' => 'About Acme',
                'meta_description' => 'About page',
                'is_published' => true,
            ],
            [
                'kind' => HostedPage::KIND_LEGAL,
                'title' => 'Legal',
                'body_html' => '<p>Legal notice.</p>',
                'meta_title' => 'Legal',
                'meta_description' => 'Legal page',
                'is_published' => true,
            ],
        ]);

        return $site->fresh(['hosting', 'hostedPages']);
    }
}
