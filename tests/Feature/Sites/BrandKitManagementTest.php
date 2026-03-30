<?php

namespace Tests\Feature\Sites;

use App\Models\Article;
use App\Models\BrandAsset;
use App\Models\BrandRule;
use App\Jobs\RefreshSiteArticleScoresJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\CreatesTeams;
use Tests\TestCase;

class BrandKitManagementTest extends TestCase
{
    use CreatesTeams;
    use RefreshDatabase;

    public function test_authorized_user_can_view_brand_kit_page(): void
    {
        $user = $this->createUserWithTeam();
        $site = $this->createSiteForUser($user, [
            'name' => 'Acme',
            'business_description' => 'Acme builds industrial widgets.',
        ]);

        $site->brandAssets()->create([
            'type' => BrandAsset::TYPE_PROOF,
            'title' => 'Customer proof',
            'content' => 'Trusted by 500+ manufacturers.',
            'priority' => 90,
        ]);

        $site->brandRules()->create([
            'category' => BrandRule::CATEGORY_MUST_AVOID,
            'label' => 'No guarantees',
            'value' => 'Never promise guaranteed rankings.',
            'priority' => 95,
        ]);

        $response = $this->actingAs($user)->get(route('sites.brand-kit.show', $site));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Sites/BrandKit')
            ->where('site.id', $site->id)
            ->where('site.name', 'Acme')
            ->has('brandAssets', 1)
            ->has('brandRules', 1)
        );
    }

    public function test_authorized_user_can_manage_brand_assets_and_rules(): void
    {
        Queue::fake();

        $user = $this->createUserWithTeam();
        $site = $this->createSiteForUser($user);

        $this->actingAs($user)->post(route('sites.brand-assets.store', $site), [
            'type' => BrandAsset::TYPE_FAQ,
            'title' => 'FAQ block',
            'source_url' => 'https://example.com/faq',
            'content' => 'Answer objections clearly and mention implementation constraints.',
            'priority' => 80,
            'is_active' => true,
        ])->assertRedirect();

        $asset = BrandAsset::query()->firstOrFail();

        $this->assertDatabaseHas('brand_assets', [
            'site_id' => $site->id,
            'type' => BrandAsset::TYPE_FAQ,
            'title' => 'FAQ block',
        ]);

        $this->actingAs($user)->patch(route('sites.brand-assets.update', ['site' => $site, 'brandAsset' => $asset]), [
            'type' => BrandAsset::TYPE_FAQ,
            'title' => 'Updated FAQ block',
            'source_url' => 'https://example.com/faq',
            'content' => 'Updated FAQ content.',
            'priority' => 60,
            'is_active' => false,
        ])->assertRedirect();

        $this->actingAs($user)->post(route('sites.brand-rules.store', $site), [
            'category' => BrandRule::CATEGORY_MUST_INCLUDE,
            'label' => 'Mention audience',
            'value' => 'Speak directly to operations leaders.',
            'priority' => 85,
            'is_active' => true,
        ])->assertRedirect();

        $rule = BrandRule::query()->firstOrFail();

        $this->assertDatabaseHas('brand_rules', [
            'site_id' => $site->id,
            'category' => BrandRule::CATEGORY_MUST_INCLUDE,
            'label' => 'Mention audience',
        ]);

        $this->actingAs($user)->patch(route('sites.brand-rules.update', ['site' => $site, 'brandRule' => $rule]), [
            'category' => BrandRule::CATEGORY_COMPLIANCE,
            'label' => 'Compliance note',
            'value' => 'Avoid legal guarantees.',
            'priority' => 70,
            'is_active' => false,
        ])->assertRedirect();

        $this->assertDatabaseHas('brand_assets', [
            'id' => $asset->id,
            'title' => 'Updated FAQ block',
            'is_active' => false,
        ]);

        $this->assertDatabaseHas('brand_rules', [
            'id' => $rule->id,
            'category' => BrandRule::CATEGORY_COMPLIANCE,
            'label' => 'Compliance note',
            'is_active' => false,
        ]);

        Queue::assertPushed(RefreshSiteArticleScoresJob::class, 4);
    }

    public function test_hosted_site_can_import_hosted_pages_into_brand_assets_idempotently(): void
    {
        Queue::fake();

        $user = $this->createUserWithTeam();
        $site = $this->createSiteForUser($user, [
            'mode' => 'hosted',
            'domain' => 'acme.test',
        ]);

        $site->hosting()->create([
            'staging_domain' => 'acme-demo.autoblog.test',
            'canonical_domain' => 'blog.acme.test',
        ]);

        $site->hostedPages()->createMany([
            [
                'kind' => 'home',
                'title' => 'Acme Automation Blog',
                'body_html' => '<h1>Automation insights</h1><p>We help operations teams reduce downtime with practical automation systems.</p>',
                'is_published' => true,
            ],
            [
                'kind' => 'about',
                'title' => 'About Acme',
                'body_html' => '<p>Acme serves industrial teams with implementation-first guidance and measurable outcomes.</p>',
                'is_published' => true,
            ],
            [
                'kind' => 'legal',
                'title' => 'Legal',
                'body_html' => '',
                'is_published' => true,
            ],
        ]);

        $this->actingAs($user)
            ->post(route('sites.brand-kit.import-hosted-pages', $site))
            ->assertRedirect();

        $this->assertDatabaseCount('brand_assets', 2);
        $this->assertDatabaseHas('brand_assets', [
            'site_id' => $site->id,
            'type' => BrandAsset::TYPE_STYLE_SAMPLE,
            'title' => 'Acme Automation Blog',
            'source_url' => 'https://blog.acme.test',
        ]);
        $this->assertDatabaseHas('brand_assets', [
            'site_id' => $site->id,
            'type' => BrandAsset::TYPE_PILLAR_PAGE,
            'title' => 'About Acme',
            'source_url' => 'https://blog.acme.test/about',
        ]);

        $this->actingAs($user)
            ->post(route('sites.brand-kit.import-hosted-pages', $site))
            ->assertRedirect();

        $this->assertDatabaseCount('brand_assets', 2);
        Queue::assertPushed(RefreshSiteArticleScoresJob::class, 2);
    }

    public function test_site_can_import_published_articles_into_brand_assets_idempotently(): void
    {
        Queue::fake();

        $user = $this->createUserWithTeam();
        $site = $this->createSiteForUser($user, [
            'mode' => 'hosted',
            'domain' => 'acme.test',
        ]);

        $site->hosting()->create([
            'canonical_domain' => 'blog.acme.test',
        ]);

        Article::factory()->create([
            'site_id' => $site->id,
            'title' => 'Automation Rollout Playbook',
            'slug' => 'automation-rollout-playbook',
            'content' => '<p>This guide explains rollout sequencing, training, and downtime prevention for operations teams.</p>',
            'status' => Article::STATUS_PUBLISHED,
            'published_url' => 'https://blog.acme.test/blog/automation-rollout-playbook',
            'published_via' => 'hosted',
        ]);

        Article::factory()->create([
            'site_id' => $site->id,
            'title' => 'Empty Draft',
            'slug' => 'empty-draft',
            'content' => null,
            'status' => Article::STATUS_PUBLISHED,
        ]);

        $this->actingAs($user)
            ->post(route('sites.brand-kit.import-published-articles', $site))
            ->assertRedirect();

        $this->assertDatabaseHas('brand_assets', [
            'site_id' => $site->id,
            'type' => BrandAsset::TYPE_STYLE_SAMPLE,
            'title' => 'Automation Rollout Playbook',
            'source_url' => 'https://blog.acme.test/blog/automation-rollout-playbook',
        ]);
        $this->assertDatabaseCount('brand_assets', 1);

        $this->actingAs($user)
            ->post(route('sites.brand-kit.import-published-articles', $site))
            ->assertRedirect();

        $this->assertDatabaseCount('brand_assets', 1);
        Queue::assertPushed(RefreshSiteArticleScoresJob::class, 2);
    }

    public function test_user_cannot_manage_foreign_site_brand_kit(): void
    {
        $owner = $this->createUserWithTeam();
        $site = $this->createSiteForUser($owner);
        $otherUser = $this->createUserWithTeam();

        $this->actingAs($otherUser)
            ->get(route('sites.brand-kit.show', $site))
            ->assertForbidden();

        $this->actingAs($otherUser)
            ->post(route('sites.brand-assets.store', $site), [
                'type' => BrandAsset::TYPE_OFFER,
                'title' => 'Offer',
                'content' => 'Test content',
            ])
            ->assertForbidden();

        $this->actingAs($otherUser)
            ->post(route('sites.brand-kit.import-hosted-pages', $site))
            ->assertForbidden();

        $this->actingAs($otherUser)
            ->post(route('sites.brand-kit.import-published-articles', $site))
            ->assertForbidden();

        $this->assertDatabaseCount('brand_assets', 0);
        $this->assertDatabaseCount('brand_rules', 0);
    }
}
