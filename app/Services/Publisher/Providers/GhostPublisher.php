<?php

namespace App\Services\Publisher\Providers;

use App\Models\Integration;
use App\Services\Publisher\Contracts\PublisherInterface;
use App\Services\Publisher\DTOs\PublishRequest;
use App\Services\Publisher\DTOs\PublishResult;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GhostPublisher implements PublisherInterface
{
    private string $blogUrl;
    private string $keyId;
    private string $secret;

    public function __construct(Integration $integration)
    {
        $credentials = $integration->credentials;

        $this->blogUrl = rtrim($credentials['blog_url'] ?? '', '/');
        $adminApiKey = $credentials['admin_api_key'] ?? '';

        if (!$this->blogUrl || !$adminApiKey) {
            throw new \InvalidArgumentException('Ghost credentials incomplete');
        }

        // Parse admin API key: {id}:{secret}
        $parts = explode(':', $adminApiKey);
        if (count($parts) !== 2 || strlen($parts[0]) !== 24 || strlen($parts[1]) !== 64) {
            throw new \InvalidArgumentException('Invalid Ghost Admin API key format');
        }

        $this->keyId = $parts[0];
        $this->secret = $parts[1];
    }

    private function generateJwt(): string
    {
        $header = json_encode(['alg' => 'HS256', 'typ' => 'JWT', 'kid' => $this->keyId]);
        $now = time();
        $payload = json_encode([
            'iat' => $now,
            'exp' => $now + 300, // 5 minutes
            'aud' => '/admin/',
        ]);

        $base64Header = rtrim(strtr(base64_encode($header), '+/', '-_'), '=');
        $base64Payload = rtrim(strtr(base64_encode($payload), '+/', '-_'), '=');

        $signature = hash_hmac('sha256', "$base64Header.$base64Payload", hex2bin($this->secret), true);
        $base64Signature = rtrim(strtr(base64_encode($signature), '+/', '-_'), '=');

        return "$base64Header.$base64Payload.$base64Signature";
    }

    public function testConnection(): bool
    {
        try {
            $response = $this->request('GET', '/ghost/api/admin/site/');
            return isset($response['site']['title']);
        } catch (\Exception $e) {
            Log::warning('Ghost connection test failed', ['error' => $e->getMessage()]);
            return false;
        }
    }

    public function getType(): string
    {
        return 'ghost';
    }

    public function getCategories(): array
    {
        try {
            $response = $this->request('GET', '/ghost/api/admin/tags/', ['limit' => 'all']);
            return array_map(fn($tag) => [
                'id' => $tag['id'],
                'name' => $tag['name'],
                'slug' => $tag['slug'],
            ], $response['tags'] ?? []);
        } catch (\Exception $e) {
            return [];
        }
    }

    public function publish(PublishRequest $request): PublishResult
    {
        // Placeholder - implemented in Task 5
        return PublishResult::failure('Not implemented');
    }

    public function update(string $remoteId, PublishRequest $request): PublishResult
    {
        // Placeholder - implemented in Task 6
        return PublishResult::failure('Not implemented');
    }

    public function delete(string $remoteId): bool
    {
        // Placeholder - implemented in Task 6
        return false;
    }

    private function request(string $method, string $endpoint, array $data = []): array
    {
        $url = $this->blogUrl . $endpoint;
        $jwt = $this->generateJwt();

        $request = Http::withHeaders([
            'Authorization' => "Ghost {$jwt}",
        ])->timeout(30);

        $response = match (strtoupper($method)) {
            'GET' => $request->get($url, $data),
            'POST' => $request->post($url, $data),
            'PUT' => $request->put($url, $data),
            'DELETE' => $request->delete($url, $data),
            default => throw new \InvalidArgumentException("Unsupported method: {$method}"),
        };

        if (!$response->successful()) {
            throw new \RuntimeException("Ghost API error: " . $response->body());
        }

        return $response->json() ?? [];
    }
}
