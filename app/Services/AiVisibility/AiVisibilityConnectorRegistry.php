<?php

namespace App\Services\AiVisibility;

use App\Services\AiVisibility\Connectors\AiVisibilityConnector;
use App\Services\AiVisibility\Connectors\DataForSeoAiOverviewConnector;
use App\Services\AiVisibility\Connectors\EstimatedAiVisibilityConnector;
use App\Models\AiVisibilityCheck;
use RuntimeException;

class AiVisibilityConnectorRegistry
{
    /**
     * @var array<string, AiVisibilityConnector>
     */
    private array $connectors;

    public function __construct(
        DataForSeoAiOverviewConnector $dataForSeoAiOverview,
        EstimatedAiVisibilityConnector $estimated,
    ) {
        $this->connectors = [
            $dataForSeoAiOverview->key() => $dataForSeoAiOverview,
            $estimated->key() => $estimated,
        ];
    }

    public function resolve(?string $key = null): AiVisibilityConnector
    {
        $key ??= 'estimated';

        if (!isset($this->connectors[$key])) {
            throw new RuntimeException("Unsupported AI visibility provider [{$key}].");
        }

        return $this->connectors[$key];
    }

    public function keys(): array
    {
        return array_keys($this->connectors);
    }

    public function resolveForEngine(string $engine, ?string $preferredKey = null): AiVisibilityConnector
    {
        $candidates = [];

        if ($preferredKey !== null) {
            $candidates[] = $preferredKey;
        }

        $candidates = array_merge($candidates, match ($engine) {
            AiVisibilityCheck::ENGINE_AI_OVERVIEWS => ['dataforseo_ai_overview', 'estimated'],
            default => ['estimated'],
        });

        foreach (array_unique($candidates) as $key) {
            $connector = $this->resolve($key);

            if ($connector->isAvailable() && $connector->supportsEngine($engine)) {
                return $connector;
            }
        }

        throw new RuntimeException("No AI visibility provider is available for engine [{$engine}].");
    }
}
