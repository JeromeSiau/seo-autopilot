<?php

namespace App\Services\LLM;

use App\Services\LLM\Contracts\LLMProviderInterface;
use App\Services\LLM\DTOs\LLMResponse;
use App\Services\LLM\Providers\AnthropicProvider;
use App\Services\LLM\Providers\GoogleProvider;
use App\Services\LLM\Providers\OpenAIProvider;
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
        // OpenAI
        if ($key = config('services.openai.api_key')) {
            $this->providers['openai'] = new OpenAIProvider($key);
        }

        // Anthropic
        if ($key = config('services.anthropic.api_key')) {
            $this->providers['anthropic'] = new AnthropicProvider($key);
        }

        // Google
        if ($key = config('services.google.ai_api_key')) {
            $this->providers['google'] = new GoogleProvider($key);
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
     */
    private function getStepConfig(string $step): array
    {
        // Our optimized pipeline from the design
        $steps = [
            'research' => [
                'provider' => 'google',
                'model' => 'gemini-2.5-flash-lite',
                'json' => true,
            ],
            'outline' => [
                'provider' => 'openai',
                'model' => 'gpt-4o',
                'json' => true,
            ],
            'write_section' => [
                'provider' => 'anthropic',
                'model' => 'claude-sonnet-4-5-20241022',
                'json' => false,
            ],
            'polish' => [
                'provider' => 'openai',
                'model' => 'gpt-4o-mini',
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
