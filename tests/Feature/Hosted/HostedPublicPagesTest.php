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
        $author = $site->hostedAuthors()->create([
            'name' => 'Jane Doe',
            'slug' => 'jane-doe',
            'bio' => 'Editorial lead for hosted content.',
            'avatar_url' => 'https://cdn.example.test/jane.webp',
            'sort_order' => 10,
            'is_active' => true,
        ]);
        $category = $site->hostedCategories()->create([
            'name' => 'SEO Systems',
            'slug' => 'seo-systems',
            'description' => 'Systems archive.',
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
            'title' => 'Hosted article',
            'slug' => 'hosted-article',
            'content' => '<p>Hosted content</p>',
            'meta_description' => 'Hosted article description',
            'images' => [
                'featured' => [
                    'url' => 'https://cdn.example.test/article-featured.webp',
                ],
            ],
        ]);
        $article->hostedTags()->attach($tag->id);

        $homeResponse = $this->get('http://preview.acme.test/');
        $homeResponse->assertOk()
            ->assertSee('Acme Hosted')
            ->assertSee('name="robots" content="noindex, nofollow"', false)
            ->assertSee('property="og:image" content="https://cdn.example.test/site-social.webp"', false)
            ->assertSee('Acme Hosted logo', false)
            ->assertSee('SEO Systems')
            ->assertSee('Jane Doe');

        $articleResponse = $this->get("http://preview.acme.test/blog/{$article->slug}");
        $articleResponse->assertOk()
            ->assertSee('Hosted article')
            ->assertSee('Hosted content', false)
            ->assertSee('https://cdn.example.test/article-featured.webp', false)
            ->assertSee('Jane Doe')
            ->assertSee('SEO Systems')
            ->assertSee('AI Overviews')
            ->assertSee('"@type":"BlogPosting"', false)
            ->assertSee('"@type":"BreadcrumbList"', false);

        $this->get('http://preview.acme.test/authors/jane-doe')
            ->assertOk()
            ->assertSee('Jane Doe')
            ->assertSee('Hosted article');

        $this->get('http://preview.acme.test/categories/seo-systems')
            ->assertOk()
            ->assertSee('SEO Systems')
            ->assertSee('Hosted article');

        $this->get('http://preview.acme.test/tags/ai-overviews')
            ->assertOk()
            ->assertSee('AI Overviews')
            ->assertSee('Hosted article');

        $aboutResponse = $this->get('http://preview.acme.test/about');
        $aboutResponse->assertOk()->assertSee('About Acme');
    }

    public function test_hosted_domain_serves_custom_pages(): void
    {
        config()->set('services.hosted.primary_domains', ['app.example.test']);

        $site = $this->createHostedSite();
        $site->hostedPages()->create([
            'kind' => HostedPage::KIND_CUSTOM,
            'slug' => 'pricing',
            'title' => 'Pricing',
            'navigation_label' => 'Pricing',
            'body_html' => '<p>Simple pricing page</p>',
            'meta_title' => 'Pricing',
            'meta_description' => 'Pricing page',
            'show_in_navigation' => true,
            'sort_order' => 300,
            'is_published' => true,
        ]);

        $response = $this->get('http://preview.acme.test/pricing');

        $response->assertOk()
            ->assertSee('Pricing')
            ->assertSee('Simple pricing page', false);
    }

    public function test_hosted_pages_apply_seo_overrides(): void
    {
        config()->set('services.hosted.primary_domains', ['app.example.test']);

        $site = $this->createHostedSite();
        $site->hostedPages()->create([
            'kind' => HostedPage::KIND_CUSTOM,
            'slug' => 'services',
            'title' => 'Services',
            'navigation_label' => 'Services',
            'body_html' => '<p>Services page</p>',
            'meta_title' => 'Services',
            'meta_description' => 'Services meta description',
            'canonical_url' => 'https://www.example.com/services',
            'social_title' => 'Services social title',
            'social_description' => 'Services social description',
            'social_image_url' => 'https://cdn.example.test/services-social.webp',
            'robots_noindex' => true,
            'schema_enabled' => false,
            'show_in_navigation' => true,
            'sort_order' => 320,
            'is_published' => true,
        ]);

        $response = $this->get('http://preview.acme.test/services');

        $response->assertOk()
            ->assertSee('link rel="canonical" href="https://www.example.com/services"', false)
            ->assertSee('meta name="robots" content="noindex, nofollow"', false)
            ->assertSee('property="og:title" content="Services social title"', false)
            ->assertSee('name="twitter:title" content="Services social title"', false)
            ->assertSee('property="og:description" content="Services social description"', false)
            ->assertSee('name="twitter:description" content="Services social description"', false)
            ->assertSee('property="og:image" content="https://cdn.example.test/services-social.webp"', false)
            ->assertDontSee('"@type":"WebPage"', false)
            ->assertDontSee('"@type":"BreadcrumbList"', false);
    }

    public function test_hosted_pages_render_structured_sections(): void
    {
        config()->set('services.hosted.primary_domains', ['app.example.test']);

        $site = $this->createHostedSite();
        $site->hostedPages()->create([
            'kind' => HostedPage::KIND_CUSTOM,
            'slug' => 'services',
            'title' => 'Services',
            'navigation_label' => 'Services',
            'body_html' => '<p>Services page</p>',
            'sections' => [
                [
                    'type' => HostedPage::SECTION_HERO,
                    'eyebrow' => 'Hosted lane',
                    'title' => 'Run a first-party publishing system',
                    'body' => 'Scale landing pages and articles from one code path.',
                    'cta_label' => 'See pricing',
                    'cta_href' => '/pricing',
                    'secondary_cta_label' => 'Talk to sales',
                    'secondary_cta_href' => 'https://sales.example.test',
                ],
                [
                    'type' => HostedPage::SECTION_CALLOUT,
                    'eyebrow' => 'Trusted by operators',
                    'title' => 'Hosted publishing that compounds',
                    'body' => 'Launch a first-party blog without a CMS dependency.',
                    'cta_label' => 'See pricing',
                    'cta_href' => '/pricing',
                ],
                [
                    'type' => HostedPage::SECTION_TESTIMONIAL_GRID,
                    'title' => 'What teams say',
                    'items' => [
                        ['title' => 'Acme SEO', 'body' => 'Hosted mode ships faster than our old stack.'],
                    ],
                ],
                [
                    'type' => HostedPage::SECTION_STAT_GRID,
                    'title' => 'Impact',
                    'items' => [
                        ['title' => '+38%', 'body' => 'Faster launch velocity'],
                    ],
                ],
                [
                    'type' => HostedPage::SECTION_CTA_BANNER,
                    'eyebrow' => 'Start fast',
                    'title' => 'Move your SEO operating system in-house',
                    'body' => 'Go live with a first-party hosted setup and keep publishing velocity.',
                    'cta_label' => 'Book a walkthrough',
                    'cta_href' => '/contact',
                    'secondary_cta_label' => 'Compare plans',
                    'secondary_cta_href' => '/pricing',
                ],
                [
                    'type' => HostedPage::SECTION_PRICING_GRID,
                    'title' => 'Plans',
                    'body' => 'Pick the level that matches your editorial ambition.',
                    'items' => [
                        [
                            'title' => 'Core',
                            'price' => '$990/mo',
                            'meta' => 'Best for lean teams',
                            'body' => 'Hosted blog, AI visibility and refresh automation.',
                            'cta_label' => 'Choose Core',
                            'href' => '/pricing',
                        ],
                    ],
                ],
                [
                    'type' => HostedPage::SECTION_FAQ,
                    'title' => 'Questions',
                    'items' => [
                        ['question' => 'Can I export the site?', 'answer' => 'Yes, a ZIP export is available.'],
                    ],
                ],
            ],
            'meta_title' => 'Services',
            'meta_description' => 'Services meta description',
            'show_in_navigation' => true,
            'sort_order' => 320,
            'is_published' => true,
        ]);

        $response = $this->get('http://preview.acme.test/services');

        $response->assertOk()
            ->assertSee('Run a first-party publishing system')
            ->assertSee('Hosted publishing that compounds')
            ->assertSee('See pricing')
            ->assertSee('Talk to sales')
            ->assertSee('What teams say')
            ->assertSee('Hosted mode ships faster than our old stack.')
            ->assertSee('+38%')
            ->assertSee('Move your SEO operating system in-house')
            ->assertSee('Compare plans')
            ->assertSee('$990/mo')
            ->assertSee('Choose Core')
            ->assertSee('Can I export the site?')
            ->assertSee('Yes, a ZIP export is available.');
    }

    public function test_hosted_page_distribution_flags_affect_sitemap_and_feed(): void
    {
        config()->set('services.hosted.primary_domains', ['app.example.test']);

        $site = $this->createHostedSite();
        $site->hostedPages()->create([
            'kind' => HostedPage::KIND_CUSTOM,
            'slug' => 'playbooks',
            'title' => 'Playbooks',
            'navigation_label' => 'Playbooks',
            'body_html' => '<p>Playbooks page</p>',
            'meta_title' => 'Playbooks',
            'meta_description' => 'Playbooks page',
            'show_in_sitemap' => false,
            'show_in_feed' => true,
            'breadcrumbs_enabled' => false,
            'show_in_navigation' => true,
            'sort_order' => 320,
            'is_published' => true,
        ]);
        $site->hostedPages()->create([
            'kind' => HostedPage::KIND_CUSTOM,
            'slug' => 'pricing',
            'title' => 'Pricing',
            'navigation_label' => 'Pricing',
            'body_html' => '<p>Pricing page</p>',
            'meta_title' => 'Pricing',
            'meta_description' => 'Pricing page',
            'show_in_sitemap' => true,
            'show_in_feed' => false,
            'breadcrumbs_enabled' => true,
            'show_in_navigation' => true,
            'sort_order' => 330,
            'is_published' => true,
        ]);

        $this->get('http://preview.acme.test/sitemap.xml')
            ->assertOk()
            ->assertDontSee('/playbooks', false)
            ->assertSee('/pricing', false);

        $this->get('http://preview.acme.test/feed.xml')
            ->assertOk()
            ->assertSee('/playbooks', false)
            ->assertDontSee('/pricing', false);
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

    public function test_hosted_domain_applies_configured_redirects(): void
    {
        config()->set('services.hosted.primary_domains', ['app.example.test']);

        $site = $this->createHostedSite();
        $site->hostedRedirects()->create([
            'source_path' => '/old-pricing',
            'destination_url' => '/pricing',
            'http_status' => 301,
        ]);

        $response = $this->get('http://preview.acme.test/old-pricing');

        $response->assertRedirect('http://preview.acme.test/pricing');
        $this->assertDatabaseHas('hosted_redirects', [
            'site_id' => $site->id,
            'source_path' => '/old-pricing',
            'destination_url' => '/pricing',
            'http_status' => 301,
            'hit_count' => 1,
        ]);
    }

    public function test_manual_navigation_overrides_automatic_menu_and_supports_footer_links(): void
    {
        config()->set('services.hosted.primary_domains', ['app.example.test']);

        $site = $this->createHostedSite();
        $site->hostedNavigationItems()->createMany([
            [
                'placement' => 'header',
                'type' => 'path',
                'label' => 'Resources',
                'path' => '/resources',
                'open_in_new_tab' => false,
                'is_active' => true,
                'sort_order' => 100,
            ],
            [
                'placement' => 'header',
                'type' => 'path',
                'label' => 'Blog',
                'path' => '/blog',
                'open_in_new_tab' => false,
                'is_active' => true,
                'sort_order' => 110,
            ],
            [
                'placement' => 'footer',
                'type' => 'url',
                'label' => 'LinkedIn',
                'url' => 'https://linkedin.com/company/acme',
                'open_in_new_tab' => true,
                'is_active' => true,
                'sort_order' => 200,
            ],
        ]);

        $response = $this->get('http://preview.acme.test/');

        $response->assertOk()
            ->assertSee('Resources')
            ->assertSee('LinkedIn')
            ->assertDontSee('>About<', false);
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
                'logo_url' => 'https://cdn.example.test/site-logo.webp',
                'social_image_url' => 'https://cdn.example.test/site-social.webp',
            ],
        ], $hostingAttributes));

        $site->hostedPages()->createMany([
            [
                'kind' => HostedPage::KIND_HOME,
                'slug' => 'home',
                'title' => 'Home',
                'navigation_label' => 'Home',
                'body_html' => '<p>Welcome to Acme.</p>',
                'meta_title' => 'Acme Hosted',
                'meta_description' => 'Home page',
                'show_in_navigation' => true,
                'sort_order' => 0,
                'is_published' => true,
            ],
            [
                'kind' => HostedPage::KIND_ABOUT,
                'slug' => 'about',
                'title' => 'About Acme',
                'navigation_label' => 'About',
                'body_html' => '<p>About the brand.</p>',
                'meta_title' => 'About Acme',
                'meta_description' => 'About page',
                'show_in_navigation' => true,
                'sort_order' => 200,
                'is_published' => true,
            ],
            [
                'kind' => HostedPage::KIND_LEGAL,
                'slug' => 'legal',
                'title' => 'Legal',
                'navigation_label' => 'Legal',
                'body_html' => '<p>Legal notice.</p>',
                'meta_title' => 'Legal',
                'meta_description' => 'Legal page',
                'show_in_navigation' => false,
                'sort_order' => 900,
                'is_published' => true,
            ],
        ]);

        return $site->fresh(['hosting', 'hostedPages']);
    }
}
