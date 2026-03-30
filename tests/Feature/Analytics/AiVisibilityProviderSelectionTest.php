<?php

namespace Tests\Feature\Analytics;

use App\Jobs\GenerateAiPromptSetJob;
use App\Jobs\RunAiVisibilityChecksJob;
use App\Models\AiPrompt;
use App\Models\AiVisibilityCheck;
use App\Models\Article;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreatesTeams;
use Tests\TestCase;

class AiVisibilityProviderSelectionTest extends TestCase
{
    use CreatesTeams;
    use RefreshDatabase;

    public function test_runner_prefers_dataforseo_for_ai_overviews_and_keeps_estimated_for_other_engines(): void
    {
        config([
            'services.dataforseo.login' => 'login',
            'services.dataforseo.password' => 'password',
        ]);

        Http::fake([
            'https://api.dataforseo.com/v3/serp/google/organic/live/advanced' => Http::response([
                'status_code' => 20000,
                'tasks' => [[
                    'result' => [[
                        'check_url' => 'https://www.google.com/search?q=industrial+automation+software',
                        'item_types' => ['ai_overview', 'organic'],
                        'items' => [
                            [
                                'type' => 'ai_overview',
                                'references' => [
                                    [
                                        'url' => 'https://acme.test/blog/industrial-automation-software-guide',
                                        'domain' => 'acme.test',
                                        'title' => 'Industrial Automation Software Guide',
                                    ],
                                    [
                                        'url' => 'https://competitor.test/industrial-automation',
                                        'domain' => 'competitor.test',
                                        'title' => 'Competitor automation guide',
                                    ],
                                ],
                            ],
                            [
                                'type' => 'organic',
                                'rank_absolute' => 1,
                                'url' => 'https://acme.test/blog/industrial-automation-software-guide',
                                'domain' => 'acme.test',
                                'title' => 'Industrial Automation Software Guide',
                            ],
                            [
                                'type' => 'organic',
                                'rank_absolute' => 2,
                                'url' => 'https://competitor.test/industrial-automation',
                                'domain' => 'competitor.test',
                                'title' => 'Competitor automation guide',
                            ],
                        ],
                    ]],
                ]],
            ], 200),
        ]);

        $user = $this->createUserWithTeam();
        $site = $this->createSiteForUser($user, [
            'name' => 'Acme',
            'domain' => 'acme.test',
            'language' => 'en',
        ]);

        $article = Article::factory()->published()->create([
            'site_id' => $site->id,
            'title' => 'Industrial Automation Software Guide',
            'content' => '<p>Industrial automation software buyer guide.</p>',
            'published_url' => 'https://acme.test/blog/industrial-automation-software-guide',
        ]);

        $article->score()->create([
            'readiness_score' => 84,
            'brand_fit_score' => 81,
            'seo_score' => 85,
            'citation_score' => 74,
            'internal_link_score' => 72,
            'fact_confidence_score' => 80,
            'warnings' => [],
            'checklist' => [],
        ]);

        $site->brandAssets()->create([
            'type' => 'proof',
            'title' => 'Automation proof',
            'content' => 'Industrial automation software rollout improved throughput.',
            'priority' => 90,
            'is_active' => true,
        ]);

        $prompt = AiPrompt::create([
            'site_id' => $site->id,
            'prompt' => 'industrial automation software',
            'topic' => 'industrial automation software',
            'intent' => 'commercial',
            'priority' => 90,
            'locale' => 'en',
            'is_active' => true,
            'metadata' => ['source_type' => 'keyword'],
        ]);

        $checks = app(\App\Services\AiVisibility\AiVisibilityRunner::class)->runForSite(
            $site,
            [AiVisibilityCheck::ENGINE_AI_OVERVIEWS, AiVisibilityCheck::ENGINE_CHATGPT],
            1,
        );

        $this->assertCount(2, $checks);

        $aiOverviewCheck = $checks->firstWhere('engine', AiVisibilityCheck::ENGINE_AI_OVERVIEWS);
        $chatgptCheck = $checks->firstWhere('engine', AiVisibilityCheck::ENGINE_CHATGPT);

        $this->assertSame('dataforseo_ai_overview', $aiOverviewCheck->provider);
        $this->assertTrue((bool) $aiOverviewCheck->appears);
        $this->assertFalse((bool) data_get($aiOverviewCheck->metadata, 'estimated', true));
        $this->assertSame($prompt->id, $aiOverviewCheck->ai_prompt_id);
        $this->assertSame('estimated', $chatgptCheck->provider);

        Http::assertSent(function ($request) {
            $payload = $request->data();

            return str_contains($request->url(), '/serp/google/organic/live/advanced')
                && data_get($payload, '0.keyword') === 'industrial automation software'
                && data_get($payload, '0.load_async_ai_overview') === true
                && data_get($payload, '0.expand_ai_overview') === true;
        });
    }

    public function test_api_sync_accepts_provider_override(): void
    {
        Queue::fake();

        $user = $this->createUserWithTeam();
        $site = $this->createSiteForUser($user);

        Sanctum::actingAs($user);

        $response = $this->postJson(route('api.analytics.ai-visibility.sync', $site), [
            'provider' => 'dataforseo_ai_overview',
        ]);

        $response->assertOk()
            ->assertJson(['message' => 'AI visibility sync started']);

        Queue::assertPushed(GenerateAiPromptSetJob::class);
        Queue::assertPushed(RunAiVisibilityChecksJob::class, function (RunAiVisibilityChecksJob $job) use ($site) {
            return $job->site->is($site)
                && $job->provider === 'dataforseo_ai_overview';
        });
    }
}
