<?php

namespace Tests\Feature\Integrations;

use App\Models\Integration;
use App\Models\Site;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\CreatesTeams;
use Tests\TestCase;

class IntegrationManagementTest extends TestCase
{
    use CreatesTeams;
    use RefreshDatabase;

    #[DataProvider('integrationProvider')]
    public function test_web_can_create_each_supported_integration_type(string $type, array $credentials, array $expectedCredentials): void
    {
        $user = $this->createUserWithTeam();
        $site = $this->createSiteForUser($user, [
            'onboarding_completed_at' => now(),
        ]);

        $response = $this->actingAs($user)->post(route('integrations.store'), [
            'site_id' => $site->id,
            'type' => $type,
            'name' => strtoupper($type) . ' integration',
            'credentials' => $credentials,
        ]);

        $response->assertRedirect(route('integrations.index'));

        $integration = Integration::firstOrFail();
        $this->assertSame($type, $integration->type);
        $this->assertSame($site->id, $integration->site_id);
        $this->assertSame($expectedCredentials, $integration->credentials);
    }

    #[DataProvider('editableIntegrationProvider')]
    public function test_edit_flow_never_exposes_secrets_and_preserves_existing_secret(
        string $type,
        array $storedCredentials,
        array $expectedSafeCredentials,
        array $updateCredentials,
        array $expectedMergedCredentials,
        string $secretField,
    ): void {
        $user = $this->createUserWithTeam();
        $site = $this->createSiteForUser($user, [
            'onboarding_completed_at' => now(),
        ]);

        $integration = Integration::create([
            'team_id' => $user->currentTeam->id,
            'site_id' => $site->id,
            'type' => $type,
            'name' => 'Existing integration',
            'credentials' => $storedCredentials,
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)->get(route('integrations.edit', $integration));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Integrations/Edit')
            ->where('integration.credentials', $expectedSafeCredentials)
            ->where("integration.secret_fields.has_{$secretField}", true)
        );

        $updateResponse = $this->actingAs($user)->patch(route('integrations.update', $integration), [
            'name' => 'Updated integration',
            'credentials' => $updateCredentials,
        ]);

        $updateResponse->assertRedirect(route('integrations.index'));

        $this->assertSame($expectedMergedCredentials, $integration->fresh()->credentials);
    }

    public static function integrationProvider(): array
    {
        return [
            'wordpress' => [
                'wordpress',
                [
                    'site_url' => 'https://example.com/',
                    'username' => 'editor',
                    'app_password' => 'app-secret',
                ],
                [
                    'site_url' => 'https://example.com',
                    'username' => 'editor',
                    'app_password' => 'app-secret',
                ],
            ],
            'webflow' => [
                'webflow',
                [
                    'api_token' => 'wf-secret',
                    'site_id' => 'site_123',
                    'collection_id' => 'collection_456',
                ],
                [
                    'api_token' => 'wf-secret',
                    'site_id' => 'site_123',
                    'collection_id' => 'collection_456',
                ],
            ],
            'shopify' => [
                'shopify',
                [
                    'shop_domain' => 'https://acme.myshopify.com/',
                    'access_token' => 'shop-secret',
                    'blog_id' => '42',
                ],
                [
                    'shop_domain' => 'acme.myshopify.com',
                    'access_token' => 'shop-secret',
                    'blog_id' => '42',
                ],
            ],
            'ghost' => [
                'ghost',
                [
                    'blog_url' => 'https://ghost.example.com/',
                    'admin_api_key' => 'aaaaaaaaaaaaaaaaaaaaaaaa:bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb',
                ],
                [
                    'blog_url' => 'https://ghost.example.com',
                    'admin_api_key' => 'aaaaaaaaaaaaaaaaaaaaaaaa:bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb',
                ],
            ],
        ];
    }

    public static function editableIntegrationProvider(): array
    {
        return [
            'wordpress' => [
                'wordpress',
                [
                    'site_url' => 'https://example.com',
                    'username' => 'editor',
                    'app_password' => 'app-secret',
                ],
                [
                    'site_url' => 'https://example.com',
                    'username' => 'editor',
                ],
                [
                    'site_url' => 'https://new-example.com',
                    'username' => 'chief-editor',
                    'app_password' => '',
                ],
                [
                    'site_url' => 'https://new-example.com',
                    'username' => 'chief-editor',
                    'app_password' => 'app-secret',
                ],
                'app_password',
            ],
            'webflow' => [
                'webflow',
                [
                    'api_token' => 'wf-secret',
                    'site_id' => 'site_123',
                    'collection_id' => 'collection_456',
                ],
                [
                    'site_id' => 'site_123',
                    'collection_id' => 'collection_456',
                ],
                [
                    'site_id' => 'site_999',
                    'collection_id' => 'collection_999',
                    'api_token' => '',
                ],
                [
                    'api_token' => 'wf-secret',
                    'site_id' => 'site_999',
                    'collection_id' => 'collection_999',
                ],
                'api_token',
            ],
            'shopify' => [
                'shopify',
                [
                    'shop_domain' => 'acme.myshopify.com',
                    'access_token' => 'shop-secret',
                    'blog_id' => '42',
                ],
                [
                    'shop_domain' => 'acme.myshopify.com',
                    'blog_id' => '42',
                ],
                [
                    'shop_domain' => 'new-shop.myshopify.com',
                    'blog_id' => '',
                    'access_token' => '',
                ],
                [
                    'shop_domain' => 'new-shop.myshopify.com',
                    'access_token' => 'shop-secret',
                    'blog_id' => null,
                ],
                'access_token',
            ],
            'ghost' => [
                'ghost',
                [
                    'blog_url' => 'https://ghost.example.com',
                    'admin_api_key' => 'aaaaaaaaaaaaaaaaaaaaaaaa:bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb',
                ],
                [
                    'blog_url' => 'https://ghost.example.com',
                ],
                [
                    'blog_url' => 'https://news.example.com',
                    'admin_api_key' => '',
                ],
                [
                    'blog_url' => 'https://news.example.com',
                    'admin_api_key' => 'aaaaaaaaaaaaaaaaaaaaaaaa:bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb',
                ],
                'admin_api_key',
            ],
        ];
    }

    public function test_web_cannot_add_cms_integration_to_hosted_site(): void
    {
        $user = $this->createUserWithTeam();
        $site = $this->createSiteForUser($user, [
            'mode' => Site::MODE_HOSTED,
        ]);

        $response = $this->actingAs($user)->post(route('integrations.store'), [
            'site_id' => $site->id,
            'type' => 'wordpress',
            'name' => 'Blocked integration',
            'credentials' => [
                'site_url' => 'https://example.com',
                'username' => 'editor',
                'app_password' => 'secret',
            ],
        ]);

        $response->assertSessionHasErrors('site_id');
        $this->assertDatabaseCount('integrations', 0);
    }
}
