<?php

namespace App\Services\Google\DTOs;

readonly class GoogleTokens
{
    public function __construct(
        public string $accessToken,
        public ?string $refreshToken = null,
        public ?int $expiresAt = null,
        public ?string $scope = null,
    ) {}

    public function isExpired(): bool
    {
        if (!$this->expiresAt) {
            return false;
        }

        // Consider expired 5 minutes before actual expiration
        return time() >= ($this->expiresAt - 300);
    }

    public function toArray(): array
    {
        return [
            'access_token' => $this->accessToken,
            'refresh_token' => $this->refreshToken,
            'expires_at' => $this->expiresAt,
            'scope' => $this->scope,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            accessToken: $data['access_token'],
            refreshToken: $data['refresh_token'] ?? null,
            expiresAt: $data['expires_at'] ?? null,
            scope: $data['scope'] ?? null,
        );
    }

    public static function fromOAuthResponse(array $response): self
    {
        $expiresAt = isset($response['expires_in'])
            ? time() + $response['expires_in']
            : null;

        return new self(
            accessToken: $response['access_token'],
            refreshToken: $response['refresh_token'] ?? null,
            expiresAt: $expiresAt,
            scope: $response['scope'] ?? null,
        );
    }
}
