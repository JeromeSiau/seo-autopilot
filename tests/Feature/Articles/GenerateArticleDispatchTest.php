<?php

namespace Tests\Feature\Articles;

use App\Jobs\GenerateArticleJob;
use App\Models\Keyword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreatesTeams;
use Tests\TestCase;

class GenerateArticleDispatchTest extends TestCase
{
    use CreatesTeams;
    use RefreshDatabase;

    public function test_web_article_generation_dispatches_with_canonical_signature(): void
    {
        Queue::fake();

        $user = $this->createUserWithTeam();
        $site = $this->createSiteForUser($user);
        $keyword = Keyword::factory()->create([
            'site_id' => $site->id,
            'status' => Keyword::STATUS_PENDING,
        ]);

        $response = $this->actingAs($user)->post(route('articles.store'), [
            'keyword_id' => $keyword->id,
            'generate_images' => false,
        ]);

        $response->assertRedirect(route('articles.index'));
        $this->assertSame(Keyword::STATUS_QUEUED, $keyword->fresh()->status);

        Queue::assertPushed(GenerateArticleJob::class, function (GenerateArticleJob $job) use ($keyword) {
            return $job->keyword->is($keyword)
                && $job->generateImages === false
                && $job->sectionImageCount === 2;
        });
    }

    public function test_api_article_generation_dispatches_with_canonical_signature(): void
    {
        Queue::fake();

        $user = $this->createUserWithTeam();
        $site = $this->createSiteForUser($user);
        $keyword = Keyword::factory()->create([
            'site_id' => $site->id,
            'status' => Keyword::STATUS_PENDING,
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson("/api/keywords/{$keyword->id}/generate", [
            'generate_images' => true,
        ]);

        $response->assertOk()
            ->assertJson(['message' => 'Article generation started']);

        $this->assertSame(Keyword::STATUS_QUEUED, $keyword->fresh()->status);

        Queue::assertPushed(GenerateArticleJob::class, function (GenerateArticleJob $job) use ($keyword) {
            return $job->keyword->is($keyword)
                && $job->generateImages === true
                && $job->sectionImageCount === 2;
        });
    }
}
