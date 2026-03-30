<?php

namespace Tests\Feature\Hosted;

use App\Models\Article;
use App\Models\HostedPage;
use App\Models\Site;
use App\Models\SiteHosting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTeams;
use Tests\TestCase;

class HostedTaxonomyManagementTest extends TestCase
{
    use CreatesTeams;
    use RefreshDatabase;

    public function test_owner_can_manage_hosted_authors_categories_and_tags(): void
    {
        $user = $this->createUserWithTeam();
        $site = $this->createHostedSite($user);

        $this->actingAs($user)->post(route('sites.hosting.authors.store', $site), [
            'name' => 'Jane Doe',
            'slug' => 'jane-doe',
            'bio' => 'Editor focused on SEO systems.',
            'avatar_url' => 'https://cdn.example.test/jane.webp',
            'sort_order' => 10,
            'is_active' => true,
        ])->assertRedirect();

        $author = $site->hostedAuthors()->where('slug', 'jane-doe')->firstOrFail();

        $this->actingAs($user)->patch(route('sites.hosting.authors.update', [
            'site' => $site,
            'hostedAuthor' => $author,
        ]), [
            'name' => 'Jane D.',
            'slug' => 'jane-d',
            'bio' => 'Updated bio',
            'avatar_url' => 'https://cdn.example.test/jane-2.webp',
            'sort_order' => 20,
            'is_active' => false,
        ])->assertRedirect();

        $this->assertDatabaseHas('hosted_authors', [
            'id' => $author->id,
            'name' => 'Jane D.',
            'slug' => 'jane-d',
            'is_active' => false,
        ]);

        $this->actingAs($user)->post(route('sites.hosting.categories.store', $site), [
            'name' => 'SEO Strategy',
            'slug' => 'seo-strategy',
            'description' => 'Strategy content.',
            'sort_order' => 15,
            'is_active' => true,
        ])->assertRedirect();

        $category = $site->hostedCategories()->where('slug', 'seo-strategy')->firstOrFail();

        $this->actingAs($user)->patch(route('sites.hosting.categories.update', [
            'site' => $site,
            'hostedCategory' => $category,
        ]), [
            'name' => 'SEO Systems',
            'slug' => 'seo-systems',
            'description' => 'Updated description.',
            'sort_order' => 25,
            'is_active' => true,
        ])->assertRedirect();

        $this->assertDatabaseHas('hosted_categories', [
            'id' => $category->id,
            'name' => 'SEO Systems',
            'slug' => 'seo-systems',
        ]);

        $this->actingAs($user)->post(route('sites.hosting.tags.store', $site), [
            'name' => 'AI Overviews',
            'slug' => 'ai-overviews',
            'sort_order' => 30,
            'is_active' => true,
        ])->assertRedirect();

        $tag = $site->hostedTags()->where('slug', 'ai-overviews')->firstOrFail();

        $this->actingAs($user)->patch(route('sites.hosting.tags.update', [
            'site' => $site,
            'hostedTag' => $tag,
        ]), [
            'name' => 'Answer Engines',
            'slug' => 'answer-engines',
            'sort_order' => 35,
            'is_active' => true,
        ])->assertRedirect();

        $this->assertDatabaseHas('hosted_tags', [
            'id' => $tag->id,
            'name' => 'Answer Engines',
            'slug' => 'answer-engines',
        ]);

        $this->actingAs($user)->delete(route('sites.hosting.authors.destroy', [
            'site' => $site,
            'hostedAuthor' => $author->fresh(),
        ]))->assertRedirect();
        $this->actingAs($user)->delete(route('sites.hosting.categories.destroy', [
            'site' => $site,
            'hostedCategory' => $category->fresh(),
        ]))->assertRedirect();
        $this->actingAs($user)->delete(route('sites.hosting.tags.destroy', [
            'site' => $site,
            'hostedTag' => $tag->fresh(),
        ]))->assertRedirect();

        $this->assertDatabaseMissing('hosted_authors', ['id' => $author->id]);
        $this->assertDatabaseMissing('hosted_categories', ['id' => $category->id]);
        $this->assertDatabaseMissing('hosted_tags', ['id' => $tag->id]);
    }

    public function test_owner_can_assign_hosted_metadata_to_published_article(): void
    {
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
            'title' => 'Hosted metadata article',
            'slug' => 'hosted-metadata-article',
        ]);

        $this->actingAs($user)->patch(route('articles.hosted-metadata.update', $article), [
            'hosted_author_id' => $author->id,
            'hosted_category_id' => $category->id,
            'hosted_tag_ids' => [$tag->id],
        ])->assertRedirect();

        $this->assertDatabaseHas('articles', [
            'id' => $article->id,
            'hosted_author_id' => $author->id,
            'hosted_category_id' => $category->id,
        ]);
        $this->assertDatabaseHas('article_hosted_tag', [
            'article_id' => $article->id,
            'hosted_tag_id' => $tag->id,
        ]);
        $this->assertDatabaseHas('site_pages', [
            'site_id' => $site->id,
            'url' => 'https://preview.taxonomy.test/authors/jane-doe',
            'source' => 'hosted',
        ]);
        $this->assertDatabaseHas('site_pages', [
            'site_id' => $site->id,
            'url' => 'https://preview.taxonomy.test/categories/seo-systems',
            'source' => 'hosted',
        ]);
        $this->assertDatabaseHas('site_pages', [
            'site_id' => $site->id,
            'url' => 'https://preview.taxonomy.test/tags/ai-overviews',
            'source' => 'hosted',
        ]);
    }

    private function createHostedSite($user): Site
    {
        $site = $this->createSiteForUser($user, [
            'mode' => Site::MODE_HOSTED,
            'name' => 'Taxonomy Site',
            'domain' => 'taxonomy.test',
            'language' => 'en',
            'business_description' => 'Hosted taxonomy test site.',
        ]);

        $site->hosting()->create([
            'staging_domain' => 'preview.taxonomy.test',
            'canonical_domain' => 'preview.taxonomy.test',
            'domain_status' => SiteHosting::DOMAIN_STATUS_ACTIVE,
            'ssl_status' => SiteHosting::SSL_STATUS_ACTIVE,
            'template_key' => SiteHosting::TEMPLATE_EDITORIAL,
            'theme_settings' => [
                'brand_name' => 'Taxonomy Site',
            ],
        ]);

        $site->hostedPages()->createMany([
            [
                'kind' => HostedPage::KIND_HOME,
                'slug' => 'home',
                'title' => 'Home',
                'navigation_label' => 'Home',
                'body_html' => '<p>Home body</p>',
                'meta_title' => 'Taxonomy Site',
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
