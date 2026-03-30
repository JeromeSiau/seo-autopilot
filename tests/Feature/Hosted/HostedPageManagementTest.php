<?php

namespace Tests\Feature\Hosted;

use App\Models\HostedPage;
use App\Models\Site;
use App\Models\SiteHosting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesTeams;
use Tests\TestCase;

class HostedPageManagementTest extends TestCase
{
    use CreatesTeams;
    use RefreshDatabase;

    public function test_owner_can_manage_custom_hosted_pages(): void
    {
        Storage::fake('public');

        $user = $this->createUserWithTeam();
        $site = $this->createHostedSite($user);
        Storage::disk('public')->put('hosted-assets/site-' . $site->id . '/pricing-social.png', 'png');
        $asset = $site->hostedAssets()->create([
            'type' => 'social',
            'name' => 'Pricing social',
            'disk' => 'public',
            'path' => 'hosted-assets/site-' . $site->id . '/pricing-social.png',
            'mime_type' => 'image/png',
            'size_bytes' => 3,
            'is_active' => true,
        ]);

        $this->actingAs($user)->post(route('sites.hosting.custom-pages.store', $site), [
            'title' => 'Pricing',
            'slug' => 'pricing',
            'navigation_label' => 'Pricing',
            'body_html' => '<p>Pricing body</p>',
            'meta_title' => 'Pricing',
            'meta_description' => 'Pricing page',
            'canonical_url' => 'https://www.example.com/pricing',
            'social_title' => 'Pricing social',
            'social_description' => 'Pricing social description',
            'social_image_asset_id' => $asset->id,
            'show_in_navigation' => true,
            'sort_order' => 320,
            'robots_noindex' => false,
            'schema_enabled' => true,
            'is_published' => true,
        ])->assertRedirect();

        $page = $site->hostedPages()->where('slug', 'pricing')->firstOrFail();

        $this->assertDatabaseHas('site_pages', [
            'site_id' => $site->id,
            'url' => 'https://preview.manage.test/pricing',
            'source' => 'hosted',
        ]);

        $this->actingAs($user)->patch(route('sites.hosting.custom-pages.update', [
            'site' => $site,
            'hostedPage' => $page,
        ]), [
            'title' => 'Pricing plans',
            'slug' => 'pricing-plans',
            'navigation_label' => 'Plans',
            'body_html' => '<p>Updated pricing body</p>',
            'meta_title' => 'Pricing plans',
            'meta_description' => 'Updated pricing page',
            'canonical_url' => 'https://www.example.com/pricing-plans',
            'social_title' => 'Pricing plans social',
            'social_description' => 'Updated social description',
            'social_image_asset_id' => $asset->id,
            'show_in_navigation' => false,
            'sort_order' => 330,
            'robots_noindex' => true,
            'schema_enabled' => false,
            'is_published' => true,
        ])->assertRedirect();

        $this->assertDatabaseHas('hosted_pages', [
            'id' => $page->id,
            'title' => 'Pricing plans',
            'slug' => 'pricing-plans',
            'navigation_label' => 'Plans',
            'canonical_url' => 'https://www.example.com/pricing-plans',
            'social_title' => 'Pricing plans social',
            'show_in_navigation' => false,
            'robots_noindex' => true,
            'schema_enabled' => false,
            'sort_order' => 330,
        ]);
        $this->assertDatabaseHas('hosted_redirects', [
            'site_id' => $site->id,
            'source_path' => '/pricing',
            'destination_url' => '/pricing-plans',
            'http_status' => 301,
        ]);
        $this->assertDatabaseHas('site_pages', [
            'site_id' => $site->id,
            'url' => 'https://preview.manage.test/pricing-plans',
            'source' => 'hosted',
        ]);

        $this->actingAs($user)->delete(route('sites.hosting.custom-pages.destroy', [
            'site' => $site,
            'hostedPage' => $page,
        ]))->assertRedirect();

        $this->assertDatabaseMissing('hosted_pages', [
            'id' => $page->id,
        ]);
        $this->assertDatabaseMissing('site_pages', [
            'site_id' => $site->id,
            'url' => 'https://preview.manage.test/pricing-plans',
            'source' => 'hosted',
        ]);
    }

    public function test_owner_can_update_system_page_seo_controls(): void
    {
        Storage::fake('public');

        $user = $this->createUserWithTeam();
        $site = $this->createHostedSite($user);
        Storage::disk('public')->put('hosted-assets/site-' . $site->id . '/about-social.png', 'png');
        $asset = $site->hostedAssets()->create([
            'type' => 'social',
            'name' => 'About social',
            'disk' => 'public',
            'path' => 'hosted-assets/site-' . $site->id . '/about-social.png',
            'mime_type' => 'image/png',
            'size_bytes' => 3,
            'is_active' => true,
        ]);

        $this->actingAs($user)->patch(route('sites.hosting.pages.update', [
            'site' => $site,
            'kind' => HostedPage::KIND_ABOUT,
        ]), [
            'title' => 'About us',
            'navigation_label' => 'About',
            'body_html' => '<p>About body</p>',
            'sections' => [
                [
                    'type' => HostedPage::SECTION_CALLOUT,
                    'eyebrow' => 'Trusted by teams',
                    'title' => 'Why brands choose us',
                    'body' => 'Clear hosted publishing with first-party SEO controls.',
                    'cta_label' => 'View pricing',
                    'cta_href' => '/pricing',
                ],
            ],
            'meta_title' => 'About us',
            'meta_description' => 'About meta description',
            'canonical_url' => 'https://www.example.com/about-us',
            'social_title' => 'About social title',
            'social_description' => 'About social description',
            'social_image_asset_id' => $asset->id,
            'social_image_url' => '',
            'robots_noindex' => true,
            'schema_enabled' => false,
            'show_in_sitemap' => false,
            'show_in_feed' => true,
            'breadcrumbs_enabled' => false,
            'show_in_navigation' => true,
            'sort_order' => 200,
            'is_published' => true,
        ])->assertRedirect();

        $this->assertDatabaseHas('hosted_pages', [
            'site_id' => $site->id,
            'kind' => HostedPage::KIND_ABOUT,
            'canonical_url' => 'https://www.example.com/about-us',
            'social_title' => 'About social title',
            'robots_noindex' => true,
            'schema_enabled' => false,
            'show_in_sitemap' => false,
            'show_in_feed' => true,
            'breadcrumbs_enabled' => false,
        ]);

        $page = $site->hostedPages()->where('kind', HostedPage::KIND_ABOUT)->firstOrFail();
        $this->assertSame(HostedPage::SECTION_CALLOUT, $page->sections[0]['type']);
        $this->assertSame('/pricing', $page->sections[0]['cta_href']);
    }

    public function test_owner_can_manage_structured_sections_on_custom_pages(): void
    {
        $user = $this->createUserWithTeam();
        $site = $this->createHostedSite($user);

        $this->actingAs($user)->post(route('sites.hosting.custom-pages.store', $site), [
            'title' => 'Services',
            'slug' => 'services',
            'navigation_label' => 'Services',
            'body_html' => '<p>Services body</p>',
            'sections' => [
                [
                    'type' => HostedPage::SECTION_HERO,
                    'eyebrow' => 'Hosted first',
                    'title' => 'Operate the blog like a product',
                    'body' => 'Launch and iterate without a CMS dependency.',
                    'cta_label' => 'Book a demo',
                    'cta_href' => '/demo',
                    'secondary_cta_label' => 'Read docs',
                    'secondary_cta_href' => 'https://docs.example.test',
                ],
                [
                    'type' => HostedPage::SECTION_FEATURE_GRID,
                    'title' => 'What we do',
                    'items' => [
                        ['title' => 'Strategy', 'body' => 'Editorial systems and planning.'],
                        ['title' => 'Publishing', 'body' => 'Hosted and external distribution.'],
                    ],
                ],
                [
                    'type' => HostedPage::SECTION_STAT_GRID,
                    'title' => 'Outcomes',
                    'items' => [
                        ['title' => '+48%', 'body' => 'Organic clicks'],
                    ],
                ],
                [
                    'type' => HostedPage::SECTION_TESTIMONIAL_GRID,
                    'title' => 'Loved by teams',
                    'items' => [
                        ['title' => 'Acme SEO', 'body' => 'Hosted mode removed our CMS bottleneck.'],
                    ],
                ],
                [
                    'type' => HostedPage::SECTION_FAQ,
                    'title' => 'FAQ',
                    'items' => [
                        ['question' => 'Do you support hosted blogs?', 'answer' => 'Yes, first-party hosted blogs are supported.'],
                    ],
                ],
            ],
            'meta_title' => 'Services',
            'meta_description' => 'Services page',
            'show_in_navigation' => true,
            'sort_order' => 320,
            'is_published' => true,
        ])->assertRedirect();

        $page = $site->hostedPages()->where('slug', 'services')->firstOrFail();

        $this->assertCount(5, $page->sections);
        $this->assertSame(HostedPage::SECTION_HERO, $page->sections[0]['type']);
        $this->assertSame(HostedPage::SECTION_FEATURE_GRID, $page->sections[1]['type']);
        $this->assertSame('Strategy', $page->sections[1]['items'][0]['title']);
        $this->assertSame(HostedPage::SECTION_STAT_GRID, $page->sections[2]['type']);
        $this->assertSame(HostedPage::SECTION_TESTIMONIAL_GRID, $page->sections[3]['type']);
        $this->assertSame(HostedPage::SECTION_FAQ, $page->sections[4]['type']);
    }

    public function test_custom_hosted_page_slug_rejects_reserved_paths(): void
    {
        $user = $this->createUserWithTeam();
        $site = $this->createHostedSite($user);

        $this->actingAs($user)->post(route('sites.hosting.custom-pages.store', $site), [
            'title' => 'Blocked page',
            'slug' => 'dashboard',
            'navigation_label' => 'Blocked',
            'body_html' => '<p>Blocked</p>',
            'meta_title' => 'Blocked page',
            'meta_description' => 'Blocked page',
            'show_in_navigation' => true,
            'sort_order' => 320,
            'is_published' => true,
        ])->assertSessionHasErrors('slug');
    }

    public function test_owner_can_manage_hosted_redirects(): void
    {
        $user = $this->createUserWithTeam();
        $site = $this->createHostedSite($user);

        $this->actingAs($user)->post(route('sites.hosting.redirects.store', $site), [
            'source_path' => '/legacy-pricing',
            'destination_url' => '/pricing',
            'http_status' => 301,
        ])->assertRedirect();

        $redirect = $site->hostedRedirects()->where('source_path', '/legacy-pricing')->firstOrFail();

        $this->actingAs($user)->patch(route('sites.hosting.redirects.update', [
            'site' => $site,
            'hostedRedirect' => $redirect,
        ]), [
            'source_path' => '/legacy-pricing',
            'destination_url' => 'https://example.com/pricing',
            'http_status' => 302,
        ])->assertRedirect();

        $this->assertDatabaseHas('hosted_redirects', [
            'id' => $redirect->id,
            'destination_url' => 'https://example.com/pricing',
            'http_status' => 302,
        ]);

        $this->actingAs($user)->delete(route('sites.hosting.redirects.destroy', [
            'site' => $site,
            'hostedRedirect' => $redirect,
        ]))->assertRedirect();

        $this->assertDatabaseMissing('hosted_redirects', [
            'id' => $redirect->id,
        ]);
    }

    public function test_redirect_destination_must_differ_from_source_path(): void
    {
        $user = $this->createUserWithTeam();
        $site = $this->createHostedSite($user);

        $this->actingAs($user)->post(route('sites.hosting.redirects.store', $site), [
            'source_path' => '/pricing',
            'destination_url' => '/pricing',
            'http_status' => 301,
        ])->assertSessionHasErrors('destination_url');
    }

    private function createHostedSite($user): Site
    {
        $site = $this->createSiteForUser($user, [
            'mode' => Site::MODE_HOSTED,
            'name' => 'Manage Site',
            'domain' => 'manage.test',
            'language' => 'en',
            'business_description' => 'Hosted management site.',
        ]);

        $site->hosting()->create([
            'staging_domain' => 'preview.manage.test',
            'canonical_domain' => 'preview.manage.test',
            'domain_status' => SiteHosting::DOMAIN_STATUS_ACTIVE,
            'ssl_status' => SiteHosting::SSL_STATUS_ACTIVE,
            'template_key' => SiteHosting::TEMPLATE_EDITORIAL,
            'theme_settings' => [
                'brand_name' => 'Manage Site',
            ],
        ]);

        $site->hostedPages()->createMany([
            [
                'kind' => HostedPage::KIND_HOME,
                'slug' => 'home',
                'title' => 'Home',
                'navigation_label' => 'Home',
                'body_html' => '<p>Home body</p>',
                'meta_title' => 'Manage Site',
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
        ]);

        return $site->fresh(['hosting', 'hostedPages']);
    }
}
