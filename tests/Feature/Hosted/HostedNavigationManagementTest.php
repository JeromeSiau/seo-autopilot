<?php

namespace Tests\Feature\Hosted;

use App\Models\HostedPage;
use App\Models\Site;
use App\Models\SiteHosting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\CreatesTeams;
use Tests\TestCase;

class HostedNavigationManagementTest extends TestCase
{
    use CreatesTeams;
    use RefreshDatabase;

    public function test_owner_can_manage_hosted_navigation_items(): void
    {
        $this->withoutVite();

        $user = $this->createUserWithTeam();
        $site = $this->createHostedSite($user);

        $this->actingAs($user)->post(route('sites.hosting.navigation-items.store', $site), [
            'placement' => 'header',
            'type' => 'path',
            'label' => 'Pricing',
            'path' => 'pricing',
            'open_in_new_tab' => false,
            'is_active' => true,
            'sort_order' => 120,
        ])->assertRedirect();

        $externalLink = $site->hostedNavigationItems()->create([
            'placement' => 'footer',
            'type' => 'url',
            'label' => 'LinkedIn',
            'url' => 'https://linkedin.com/company/acme',
            'open_in_new_tab' => true,
            'is_active' => true,
            'sort_order' => 200,
        ]);

        $headerLink = $site->hostedNavigationItems()->where('label', 'Pricing')->firstOrFail();

        $this->actingAs($user)->patch(route('sites.hosting.navigation-items.update', [
            'site' => $site,
            'hostedNavigationItem' => $headerLink,
        ]), [
            'placement' => 'header',
            'type' => 'path',
            'label' => 'Resources',
            'path' => '/resources',
            'open_in_new_tab' => false,
            'is_active' => true,
            'sort_order' => 140,
        ])->assertRedirect();

        $this->actingAs($user)->delete(route('sites.hosting.navigation-items.destroy', [
            'site' => $site,
            'hostedNavigationItem' => $externalLink,
        ]))->assertRedirect();

        $this->assertDatabaseHas('hosted_navigation_items', [
            'id' => $headerLink->id,
            'site_id' => $site->id,
            'label' => 'Resources',
            'path' => '/resources',
            'placement' => 'header',
            'type' => 'path',
            'sort_order' => 140,
        ]);
        $this->assertDatabaseMissing('hosted_navigation_items', [
            'id' => $externalLink->id,
        ]);

        $this->actingAs($user)
            ->get(route('sites.hosting.show', $site))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Sites/Hosting')
                ->has('site.hosted_navigation_items', 1)
                ->where('site.hosted_navigation_items.0.label', 'Resources')
                ->where('site.hosted_navigation_items.0.path', '/resources')
            );
    }

    public function test_navigation_path_requires_valid_internal_path(): void
    {
        $user = $this->createUserWithTeam();
        $site = $this->createHostedSite($user);

        $this->actingAs($user)->post(route('sites.hosting.navigation-items.store', $site), [
            'placement' => 'header',
            'type' => 'path',
            'label' => 'Invalid',
            'path' => 'https://example.com/pricing',
            'open_in_new_tab' => false,
            'is_active' => true,
            'sort_order' => 120,
        ])->assertSessionHasErrors('path');
    }

    private function createHostedSite($user): Site
    {
        $site = $this->createSiteForUser($user, [
            'mode' => Site::MODE_HOSTED,
            'name' => 'Navigation Site',
            'domain' => 'navigation.test',
            'language' => 'en',
            'business_description' => 'Navigation management site.',
        ]);

        $site->hosting()->create([
            'staging_domain' => 'preview.navigation.test',
            'canonical_domain' => 'preview.navigation.test',
            'domain_status' => SiteHosting::DOMAIN_STATUS_ACTIVE,
            'ssl_status' => SiteHosting::SSL_STATUS_ACTIVE,
            'template_key' => SiteHosting::TEMPLATE_EDITORIAL,
            'theme_settings' => [
                'brand_name' => 'Navigation Site',
            ],
        ]);

        $site->hostedPages()->createMany([
            [
                'kind' => HostedPage::KIND_HOME,
                'slug' => 'home',
                'title' => 'Home',
                'navigation_label' => 'Home',
                'body_html' => '<p>Home body</p>',
                'meta_title' => 'Navigation Site',
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
        ]);

        return $site->fresh(['hosting', 'hostedPages']);
    }
}
