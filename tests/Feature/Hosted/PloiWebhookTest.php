<?php

namespace Tests\Feature\Hosted;

use App\Models\Article;
use App\Models\Site;
use App\Models\SiteHosting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTeams;
use Tests\TestCase;

class PloiWebhookTest extends TestCase
{
    use CreatesTeams;
    use RefreshDatabase;

    public function test_certificate_webhook_activates_custom_domain_and_updates_published_urls(): void
    {
        config()->set('services.ploi.webhook_token', 'test-token');

        $user = $this->createUserWithTeam();
        $site = $this->createSiteForUser($user, [
            'mode' => Site::MODE_HOSTED,
            'name' => 'Webhook Site',
            'domain' => 'webhook.test',
        ]);

        $site->hosting()->create([
            'staging_domain' => 'preview.webhook.test',
            'custom_domain' => 'blog.webhook.test',
            'canonical_domain' => 'preview.webhook.test',
            'domain_status' => SiteHosting::DOMAIN_STATUS_SSL_PENDING,
            'ssl_status' => SiteHosting::SSL_STATUS_PENDING,
            'template_key' => SiteHosting::TEMPLATE_EDITORIAL,
            'theme_settings' => [],
        ]);

        $article = Article::factory()->published()->create([
            'site_id' => $site->id,
            'slug' => 'launch-post',
            'published_url' => 'https://preview.webhook.test/blog/launch-post',
            'published_via' => 'hosted',
        ]);

        $response = $this->postJson(route('webhooks.ploi.tenant-certificate', ['token' => 'test-token']), [
            'domain' => 'blog.webhook.test',
        ]);

        $response->assertOk()->assertJson(['ok' => true]);

        $hosting = $site->fresh('hosting')->hosting;
        $updatedArticle = $article->fresh();

        $this->assertNotNull($hosting);
        $this->assertSame(SiteHosting::DOMAIN_STATUS_ACTIVE, $hosting->domain_status);
        $this->assertSame(SiteHosting::SSL_STATUS_ACTIVE, $hosting->ssl_status);
        $this->assertSame('blog.webhook.test', $hosting->canonical_domain);
        $this->assertSame('https://blog.webhook.test/blog/launch-post', $updatedArticle->published_url);
        $this->assertDatabaseHas('hosted_deploy_events', [
            'site_hosting_id' => $hosting->id,
            'type' => 'certificate_issued',
            'status' => 'success',
        ]);
    }
}
