<?php

namespace App\Services\AiVisibility;

use App\Services\AiVisibility\Connectors\AiVisibilityConnector;
use App\Services\AiVisibility\Connectors\EstimatedAiVisibilityConnector;
use RuntimeException;

class AiVisibilityConnectorRegistry
{
    /**
     * @var array<string, AiVisibilityConnector>
     */
    private array $connectors;

    public function __construct(
        EstimatedAiVisibilityConnector $estimated,
    ) {
        $this->connectors = [
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
}
