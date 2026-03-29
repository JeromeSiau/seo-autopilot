<?php

namespace App\Services\Hosting;

use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\Response;
use RuntimeException;

class PloiTenantService
{
    public function __construct(
        private readonly HttpFactory $http,
    ) {}

    public function createTenant(string $tenant): array
    {
        $response = $this->request()
            ->post($this->endpoint('/tenants'), [
                'tenants' => [$tenant],
            ]);

        $this->ensureSuccess($response, 'Unable to create Ploi tenant.');

        return $response->json('data') ?? [];
    }

    public function deleteTenant(string $tenant): void
    {
        $response = $this->request()
            ->delete($this->endpoint('/tenants/' . urlencode($tenant)));

        $this->ensureSuccess($response, 'Unable to delete Ploi tenant.');
    }

    public function requestCertificate(string $tenant, array $domains, ?string $webhook = null, bool $force = false): array
    {
        $payload = [
            'domains' => implode(',', $domains),
            'force' => $force,
        ];

        if ($webhook) {
            $payload['webhook'] = $webhook;
        }

        $response = $this->request()
            ->post($this->endpoint('/tenants/' . urlencode($tenant) . '/request-certificate'), $payload);

        $this->ensureSuccess($response, 'Unable to request tenant certificate.');

        return $response->json('data') ?? [];
    }

    public function deleteCertificate(string $tenant): void
    {
        $response = $this->request()
            ->delete($this->endpoint('/tenants/' . urlencode($tenant) . '/certificate'));

        $this->ensureSuccess($response, 'Unable to delete tenant certificate.');
    }

    private function request()
    {
        $token = config('services.ploi.token');

        if (!$token) {
            throw new RuntimeException('Ploi API token is not configured.');
        }

        return $this->http->baseUrl('https://ploi.io/api')
            ->acceptJson()
            ->asJson()
            ->withToken($token)
            ->timeout(30);
    }

    private function endpoint(string $path): string
    {
        $serverId = config('services.ploi.server_id');
        $siteId = config('services.ploi.site_id');

        if (!$serverId || !$siteId) {
            throw new RuntimeException('Ploi server/site identifiers are not configured.');
        }

        return "/servers/{$serverId}/sites/{$siteId}{$path}";
    }

    private function ensureSuccess(Response $response, string $message): void
    {
        if ($response->successful()) {
            return;
        }

        throw new RuntimeException($message . ' ' . $response->body());
    }
}
