<?php

namespace Tests\Unit\Services;

use App\Models\AgentEvent;
use App\Models\Article;
use App\Models\BrandAsset;
use App\Models\BrandRule;
use App\Models\Keyword;
use App\Models\Site;
use App\Services\Content\ArticleScoringService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ArticleScoringServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_scores_an_article_and_builds_checklist(): void
    {
        $site = Site::factory()->create([
            'domain' => 'acme.test',
            'tone' => 'expert',
            'writing_style' => 'Direct and practical',
            'vocabulary' => [
                'use' => ['throughput', 'downtime'],
                'avoid' => ['guaranteed'],
            ],
        ]);

        $site->brandRules()->create([
            'category' => BrandRule::CATEGORY_MUST_INCLUDE,
            'label' => 'Use proof',
            'value' => 'Support claims with operational data.',
            'priority' => 80,
        ]);

        $site->brandAssets()->create([
            'type' => BrandAsset::TYPE_CASE_STUDY,
            'title' => 'Plant rollout',
            'source_url' => 'https://acme.test/case-study',
            'content' => 'A rollout reduced downtime and improved throughput.',
            'priority' => 90,
        ]);

        $keyword = Keyword::factory()->create([
            'site_id' => $site->id,
            'keyword' => 'industrial automation software',
        ]);

        $article = Article::factory()->create([
            'site_id' => $site->id,
            'keyword_id' => $keyword->id,
            'title' => 'Industrial Automation Software',
            'content' => '<h1>Industrial Automation Software</h1><h2>Why it matters</h2><p>This guide explains throughput and downtime improvements.</p><p><a href="https://acme.test/about">About us</a></p>',
            'meta_title' => 'Industrial Automation Software',
            'meta_description' => str_repeat('A', 140),
            'word_count' => 1250,
            'status' => Article::STATUS_REVIEW,
        ]);

        $article->citations()->create([
            'source_type' => 'brand',
            'title' => 'Plant rollout',
            'url' => 'https://acme.test/case-study',
            'domain' => 'acme.test',
            'excerpt' => 'Reduced downtime and improved throughput.',
            'metadata' => [],
        ]);

        $article->agentEvents()->create([
            'agent_type' => AgentEvent::TYPE_FACT_CHECKER,
            'event_type' => AgentEvent::EVENT_COMPLETED,
            'message' => 'Fact checking completed.',
        ]);

        $score = app(ArticleScoringService::class)->scoreAndSave($article->fresh([
            'site.brandAssets',
            'site.brandRules',
            'keyword',
            'citations',
            'agentEvents',
        ]));

        $this->assertGreaterThanOrEqual(70, $score->readiness_score);
        $this->assertGreaterThanOrEqual(70, $score->seo_score);
        $this->assertGreaterThanOrEqual(70, $score->brand_fit_score);
        $this->assertNotEmpty($score->checklist);
        $this->assertIsArray($score->warnings);
    }
}
