<?php

namespace App\Services\LLM\Providers;

use App\Services\LLM\Contracts\LLMProviderInterface;
use App\Services\LLM\DTOs\LLMResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OpenRouterProvider implements LLMProviderInterface
{
    private const API_URL = 'https://openrouter.ai/api/v1/chat/completions';

    // Pricing per 1M tokens (December 2025)
    private const PRICING = [
        // DeepSeek
        'deepseek/deepseek-v3.2' => ['input' => 0.26, 'output' => 0.38],
        // Anthropic
        'anthropic/claude-sonnet-4-5' => ['input' => 3.00, 'output' => 15.00],
        'anthropic/claude-sonnet-4' => ['input' => 3.00, 'output' => 15.00],
        'anthropic/claude-haiku-3.5' => ['input' => 0.80, 'output' => 4.00],
        // Google
        'google/gemini-2.5-flash' => ['input' => 0.15, 'output' => 0.60],
        'google/gemini-2.5-pro' => ['input' => 1.25, 'output' => 10.00],
        // OpenAI
        'openai/gpt-4o' => ['input' => 2.50, 'output' => 10.00],
        'openai/gpt-4o-mini' => ['input' => 0.15, 'output' => 0.60],
    ];

    private const DEFAULT_MODEL = 'deepseek/deepseek-v3.2';

    public function __construct(
        private readonly string $apiKey,
        private readonly string $defaultModel = self::DEFAULT_MODEL,
        private readonly string $siteUrl = 'https://rankcruise.io',
        private readonly string $siteName = 'RankCruise',
    ) {}

    public function complete(string $prompt, array $options = []): LLMResponse
    {
        $model = $options['model'] ?? $this->defaultModel;
        $startTime = microtime(true);

        $requestBody = [
            'model' => $model,
            'messages' => [
                ['role' => 'user', 'content' => $prompt],
            ],
            'temperature' => $options['temperature'] ?? 0.7,
            'max_tokens' => $options['max_tokens'] ?? 4096,
        ];

        // Add provider preferences for reliability
        if (!empty($options['provider_order'])) {
            $requestBody['provider'] = [
                'order' => $options['provider_order'],
                'allow_fallbacks' => $options['allow_fallbacks'] ?? true,
            ];
        }

        $response = Http::withHeaders([
            'Authorization' => "Bearer {$this->apiKey}",
            'Content-Type' => 'application/json',
            'HTTP-Referer' => $this->siteUrl,
            'X-Title' => $this->siteName,
        ])->timeout(120)->post(self::API_URL, $requestBody);

        $latencyMs = (microtime(true) - $startTime) * 1000;

        if (!$response->successful()) {
            Log::error('OpenRouter API error', [
                'status' => $response->status(),
                'body' => $response->body(),
                'model' => $model,
            ]);
            throw new \RuntimeException('OpenRouter API error: ' . $response->body());
        }

        $data = $response->json();
        $usage = $data['usage'] ?? [];
        $inputTokens = $usage['prompt_tokens'] ?? 0;
        $outputTokens = $usage['completion_tokens'] ?? 0;

        return new LLMResponse(
            content: $data['choices'][0]['message']['content'] ?? '',
            model: $model,
            inputTokens: $inputTokens,
            outputTokens: $outputTokens,
            cost: $this->calculateCost($model, $inputTokens, $outputTokens),
            latencyMs: $latencyMs,
            finishReason: $data['choices'][0]['finish_reason'] ?? null,
        );
    }

    public function completeJson(string $prompt, array $schema = [], array $options = []): LLMResponse
    {
        $model = $options['model'] ?? $this->defaultModel;
        $startTime = microtime(true);

        $requestBody = [
            'model' => $model,
            'messages' => [
                ['role' => 'user', 'content' => $prompt],
            ],
            'response_format' => ['type' => 'json_object'],
            'temperature' => $options['temperature'] ?? 0.3,
            'max_tokens' => $options['max_tokens'] ?? 4096,
        ];

        // Add provider preferences for reliability
        if (!empty($options['provider_order'])) {
            $requestBody['provider'] = [
                'order' => $options['provider_order'],
                'allow_fallbacks' => $options['allow_fallbacks'] ?? true,
            ];
        }

        $response = Http::withHeaders([
            'Authorization' => "Bearer {$this->apiKey}",
            'Content-Type' => 'application/json',
            'HTTP-Referer' => $this->siteUrl,
            'X-Title' => $this->siteName,
        ])->timeout(120)->post(self::API_URL, $requestBody);

        $latencyMs = (microtime(true) - $startTime) * 1000;

        if (!$response->successful()) {
            Log::error('OpenRouter API error', [
                'status' => $response->status(),
                'body' => $response->body(),
                'model' => $model,
            ]);
            throw new \RuntimeException('OpenRouter API error: ' . $response->body());
        }

        $data = $response->json();
        $usage = $data['usage'] ?? [];
        $inputTokens = $usage['prompt_tokens'] ?? 0;
        $outputTokens = $usage['completion_tokens'] ?? 0;

        return new LLMResponse(
            content: $data['choices'][0]['message']['content'] ?? '',
            model: $model,
            inputTokens: $inputTokens,
            outputTokens: $outputTokens,
            cost: $this->calculateCost($model, $inputTokens, $outputTokens),
            latencyMs: $latencyMs,
            finishReason: $data['choices'][0]['finish_reason'] ?? null,
        );
    }

    public function getName(): string
    {
        return 'openrouter';
    }

    public function getAvailableModels(): array
    {
        return array_keys(self::PRICING);
    }

    public function calculateCost(string $model, int $inputTokens, int $outputTokens): float
    {
        $pricing = self::PRICING[$model] ?? self::PRICING[self::DEFAULT_MODEL];

        $inputCost = ($inputTokens / 1_000_000) * $pricing['input'];
        $outputCost = ($outputTokens / 1_000_000) * $pricing['output'];

        return round($inputCost + $outputCost, 6);
    }
}
