<?php

namespace App\Services\Publisher\Contracts;

use App\Services\Publisher\DTOs\PublishRequest;
use App\Services\Publisher\DTOs\PublishResult;

interface PublisherInterface
{
    /**
     * Publish an article.
     */
    public function publish(PublishRequest $request): PublishResult;

    /**
     * Update an existing article.
     */
    public function update(string $remoteId, PublishRequest $request): PublishResult;

    /**
     * Delete an article.
     */
    public function delete(string $remoteId): bool;

    /**
     * Test the connection/credentials.
     */
    public function testConnection(): bool;

    /**
     * Get the publisher type name.
     */
    public function getType(): string;

    /**
     * Get available categories/collections.
     */
    public function getCategories(): array;
}
