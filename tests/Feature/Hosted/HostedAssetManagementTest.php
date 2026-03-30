<?php

namespace Tests\Feature\Hosted;

use App\Models\HostedPage;
use App\Models\Site;
use App\Models\SiteHosting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesTeams;
use Tests\TestCase;

class HostedAssetManagementTest extends TestCase
{
    use CreatesTeams;
    use RefreshDatabase;

    public function test_owner_can_upload_manage_and_use_hosted_assets_in_theme(): void
    {
        Storage::fake('public');

        $user = $this->createUserWithTeam();
        $site = $this->createHostedSite($user);

        $this->actingAs($user)->post(route('sites.hosting.assets.store', $site), [
            'type' => 'logo',
            'name' => 'Brand logo',
            'alt_text' => 'Acme logo',
            'is_active' => true,
            'asset' => UploadedFile::fake()->image('logo.png', 320, 320),
        ])->assertRedirect();

        $asset = $site->hostedAssets()->firstOrFail();

        Storage::disk('public')->assertExists($asset->path);
        $this->assertDatabaseHas('hosted_assets', [
            'id' => $asset->id,
            'site_id' => $site->id,
            'type' => 'logo',
            'name' => 'Brand logo',
        ]);

        $this->actingAs($user)->patch(route('sites.hosting.assets.update', [
            'site' => $site,
            'hostedAsset' => $asset,
        ]), [
            'type' => 'social',
            'name' => 'Social card',
            'alt_text' => 'Social preview',
            'is_active' => true,
        ])->assertRedirect();

        $asset = $asset->fresh();
        $this->assertSame('social', $asset->type);
        $this->assertSame('Social card', $asset->name);

        $themeSettings = array_replace($site->hosting->theme_settings ?? [], [
            'logo_asset_id' => $asset->id,
            'social_image_asset_id' => $asset->id,
        ]);

        $this->actingAs($user)->patch(route('sites.hosting.theme', $site), [
            'template_key' => SiteHosting::TEMPLATE_EDITORIAL,
            'theme_settings' => $themeSettings,
        ])->assertRedirect();

        $response = $this->get('http://preview.asset.test/');
        $response->assertOk()
            ->assertSee('/storage/' . $asset->path, false);

        $this->actingAs($user)->delete(route('sites.hosting.assets.destroy', [
            'site' => $site,
            'hostedAsset' => $asset,
        ]))->assertRedirect();

        Storage::disk('public')->assertMissing($asset->path);
        $this->assertDatabaseMissing('hosted_assets', [
            'id' => $asset->id,
        ]);
    }

    private function createHostedSite($user): Site
    {
        config()->set('services.hosted.primary_domains', ['app.example.test']);

        $site = $this->createSiteForUser($user, [
            'mode' => Site::MODE_HOSTED,
            'name' => 'Asset Site',
            'domain' => 'asset.test',
            'language' => 'en',
            'business_description' => 'Hosted asset test site.',
        ]);

        $site->hosting()->create([
            'staging_domain' => 'preview.asset.test',
            'canonical_domain' => 'preview.asset.test',
            'domain_status' => SiteHosting::DOMAIN_STATUS_ACTIVE,
            'ssl_status' => SiteHosting::SSL_STATUS_ACTIVE,
            'template_key' => SiteHosting::TEMPLATE_EDITORIAL,
            'theme_settings' => [
                'brand_name' => 'Asset Site',
            ],
        ]);

        $site->hostedPages()->createMany([
            [
                'kind' => HostedPage::KIND_HOME,
                'slug' => 'home',
                'title' => 'Home',
                'navigation_label' => 'Home',
                'body_html' => '<p>Home body</p>',
                'meta_title' => 'Asset Site',
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
