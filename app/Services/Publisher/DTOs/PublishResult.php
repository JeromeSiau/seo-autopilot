<?php

namespace App\Services\Publisher\DTOs;

readonly class PublishResult
{
    public function __construct(
        public bool $success,
        public ?string $url = null,
        public ?string $remoteId = null,
        public ?string $error = null,
        public array $metadata = [],
    ) {}

    public static function success(string $url, ?string $remoteId = null, array $metadata = []): self
    {
        return new self(
            success: true,
            url: $url,
            remoteId: $remoteId,
            metadata: $metadata,
        );
    }

    public static function failure(string $error): self
    {
        return new self(
            success: false,
            error: $error,
        );
    }

    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'url' => $this->url,
            'remote_id' => $this->remoteId,
            'error' => $this->error,
            'metadata' => $this->metadata,
        ];
    }
}
