<?php

namespace Tests\Feature\Articles;

use App\Models\Article;
use App\Models\Keyword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\CreatesTeams;
use Tests\TestCase;

class ArticleShowPayloadTest extends TestCase
{
    use CreatesTeams;
    use RefreshDatabase;

    public function test_article_show_exposes_score_and_citations(): void
    {
        $user = $this->createUserWithTeam();
        $site = $this->createSiteForUser($user, [
            'name' => 'Acme',
            'domain' => 'acme.test',
        ]);

        $keyword = Keyword::factory()->create([
            'site_id' => $site->id,
            'keyword' => 'industrial automation software',
        ]);

        $article = Article::factory()->create([
            'site_id' => $site->id,
            'keyword_id' => $keyword->id,
            'title' => 'Industrial Automation Software Guide',
            'status' => Article::STATUS_REVIEW,
        ]);

        $article->score()->create([
            'readiness_score' => 84,
            'brand_fit_score' => 80,
            'seo_score' => 90,
            'citation_score' => 75,
            'internal_link_score' => 70,
            'fact_confidence_score' => 85,
            'warnings' => ['Meta description should be tightened.'],
            'checklist' => [
                ['label' => 'Meta title is present and within length', 'done' => true],
            ],
        ]);

        $article->citations()->createMany([
            [
                'source_type' => 'brand',
                'title' => 'Case study',
                'url' => 'https://acme.test/case-study',
                'domain' => 'acme.test',
                'excerpt' => 'Rollout reduced downtime by 18 percent.',
                'metadata' => [],
            ],
            [
                'source_type' => 'serp',
                'title' => 'example.com / Industrial Automation',
                'url' => 'https://example.com/industrial-automation',
                'domain' => 'example.com',
                'excerpt' => null,
                'metadata' => [],
            ],
        ]);

        $response = $this->actingAs($user)->get(route('articles.show', $article));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Articles/Show')
            ->where('article.id', $article->id)
            ->where('article.score.readiness_score', 84)
            ->has('article.citations', 2)
            ->where('article.citations.0.source_type', 'brand')
        );
    }
}
