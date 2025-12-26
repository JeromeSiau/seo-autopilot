<?php

namespace App\Services\LLM;

use App\Services\LLM\Contracts\LLMProviderInterface;
use App\Services\LLM\DTOs\LLMResponse;
use App\Services\LLM\Providers\OpenRouterProvider;
use Illuminate\Support\Facades\Log;

class LLMManager
{
    private array $providers = [];
    private array $costs = [];

    public function __construct()
    {
        $this->registerProviders();
    }

    private function registerProviders(): void
    {
        // OpenRouter (unified provider for all LLMs)
        if ($key = config('services.openrouter.api_key')) {
            $this->providers['openrouter'] = new OpenRouterProvider($key);
        }
    }

    public function getProvider(string $name): LLMProviderInterface
    {
        if (!isset($this->providers[$name])) {
            throw new \InvalidArgumentException("LLM provider '{$name}' not found or not configured.");
        }

        return $this->providers[$name];
    }

    public function complete(
        string $provider,
        string $prompt,
        array $options = []
    ): LLMResponse {
        $llm = $this->getProvider($provider);
        $response = $llm->complete($prompt, $options);

        $this->trackCost($provider, $response->model, $response->cost);

        return $response;
    }

    public function completeJson(
        string $provider,
        string $prompt,
        array $schema = [],
        array $options = []
    ): LLMResponse {
        $llm = $this->getProvider($provider);
        $response = $llm->completeJson($prompt, $schema, $options);

        $this->trackCost($provider, $response->model, $response->cost);

        return $response;
    }

    /**
     * Execute a step in the article generation pipeline with the recommended model.
     */
    public function executeStep(string $step, string $prompt, array $options = []): LLMResponse
    {
        $config = $this->getStepConfig($step);

        Log::info("LLM Pipeline: Executing step '{$step}'", [
            'provider' => $config['provider'],
            'model' => $config['model'],
        ]);

        $options = array_merge(['model' => $config['model']], $options);

        if ($config['json'] ?? false) {
            return $this->completeJson($config['provider'], $prompt, [], $options);
        }

        return $this->complete($config['provider'], $prompt, $options);
    }

    /**
     * Get the recommended provider/model for each pipeline step.
     * All models are routed through OpenRouter for unified billing.
     */
    private function getStepConfig(string $step): array
    {
        $steps = [
            'research' => [
                'provider' => 'openrouter',
                'model' => 'google/gemini-2.5-flash',
                'json' => true,
            ],
            'outline' => [
                'provider' => 'openrouter',
                'model' => 'deepseek/deepseek-v3.2',
                'json' => true,
            ],
            'write_section' => [
                'provider' => 'openrouter',
                'model' => 'anthropic/claude-sonnet-4-5',
                'json' => false,
            ],
            'polish' => [
                'provider' => 'openrouter',
                'model' => 'deepseek/deepseek-v3.2',
                'json' => true,
            ],
        ];

        if (!isset($steps[$step])) {
            throw new \InvalidArgumentException("Unknown pipeline step: {$step}");
        }

        return $steps[$step];
    }

    private function trackCost(string $provider, string $model, float $cost): void
    {
        $key = "{$provider}:{$model}";
        $this->costs[$key] = ($this->costs[$key] ?? 0) + $cost;
    }

    public function getTotalCost(): float
    {
        return array_sum($this->costs);
    }

    public function getCostBreakdown(): array
    {
        return $this->costs;
    }

    public function resetCosts(): void
    {
        $this->costs = [];
    }

    public function getAvailableProviders(): array
    {
        return array_keys($this->providers);
    }

    public function hasProvider(string $name): bool
    {
        return isset($this->providers[$name]);
    }
}
