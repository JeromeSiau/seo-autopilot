<?php

namespace App\Services\Publisher\Providers;

use App\Models\Integration;
use App\Services\Hosted\HostedSiteService;
use App\Services\Publisher\Contracts\PublisherInterface;
use App\Services\Publisher\DTOs\PublishRequest;
use App\Services\Publisher\DTOs\PublishResult;

class HostedPublisher implements PublisherInterface
{
    public function __construct(
        private readonly Integration $integration,
    ) {}

    public function publish(PublishRequest $request): PublishResult
    {
        $site = $this->integration->site()->with('hosting')->firstOrFail();

        /** @var HostedSiteService $hosting */
        $hosting = app(HostedSiteService::class);
        $domain = $site->getPrimaryHostedDomain();

        if (!$domain) {
            $hosting->provisionStaging($site);
            $site = $site->fresh(['hosting']);
            $domain = $site->getPrimaryHostedDomain();
        }

        $hosting->recordArticlePage($site, $request->slug ?? 'article', $request->title);

        return PublishResult::success(
            "https://{$domain}/blog/{$request->slug}",
            $request->slug,
        );
    }

    public function update(string $remoteId, PublishRequest $request): PublishResult
    {
        return $this->publish($request);
    }

    public function delete(string $remoteId): bool
    {
        $site = $this->integration->site()->with('hosting')->first();

        if (!$site) {
            return true;
        }

        app(HostedSiteService::class)->removeArticlePage($site, $remoteId);

        return true;
    }

    public function testConnection(): bool
    {
        return true;
    }

    public function getType(): string
    {
        return 'hosted';
    }

    public function getCategories(): array
    {
        return [];
    }
}
