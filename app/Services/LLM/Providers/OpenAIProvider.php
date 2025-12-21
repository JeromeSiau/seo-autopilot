<?php

namespace App\Services\LLM\Providers;

use App\Services\LLM\Contracts\LLMProviderInterface;
use App\Services\LLM\DTOs\LLMResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OpenAIProvider implements LLMProviderInterface
{
    private const API_URL = 'https://api.openai.com/v1/chat/completions';

    // Pricing per 1M tokens (December 2025)
    private const PRICING = [
        'gpt-4o' => ['input' => 2.50, 'output' => 10.00],
        'gpt-4o-mini' => ['input' => 0.15, 'output' => 0.60],
        'o1' => ['input' => 15.00, 'output' => 60.00],
        'o1-mini' => ['input' => 3.00, 'output' => 12.00],
    ];

    // Models that use max_completion_tokens instead of max_tokens
    private const REASONING_MODELS = ['o1', 'o1-mini', 'o1-preview'];

    public function __construct(
        private readonly string $apiKey,
        private readonly string $defaultModel = 'gpt-4o',
    ) {}

    public function complete(string $prompt, array $options = []): LLMResponse
    {
        $model = $options['model'] ?? $this->defaultModel;
        $startTime = microtime(true);
        $isReasoningModel = in_array($model, self::REASONING_MODELS);

        $requestBody = [
            'model' => $model,
            'messages' => [
                ['role' => 'user', 'content' => $prompt],
            ],
        ];

        // Reasoning models don't support temperature and use max_completion_tokens
        if ($isReasoningModel) {
            $requestBody['max_completion_tokens'] = $options['max_tokens'] ?? 4096;
        } else {
            $requestBody['temperature'] = $options['temperature'] ?? 0.7;
            $requestBody['max_tokens'] = $options['max_tokens'] ?? 4096;
        }

        $response = Http::withHeaders([
            'Authorization' => "Bearer {$this->apiKey}",
            'Content-Type' => 'application/json',
        ])->timeout(120)->post(self::API_URL, $requestBody);

        $latencyMs = (microtime(true) - $startTime) * 1000;

        if (!$response->successful()) {
            Log::error('OpenAI API error', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new \RuntimeException('OpenAI API error: ' . $response->body());
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
        $isReasoningModel = in_array($model, self::REASONING_MODELS);

        $requestBody = [
            'model' => $model,
            'messages' => [
                ['role' => 'user', 'content' => $prompt],
            ],
            'response_format' => ['type' => 'json_object'],
        ];

        // Reasoning models don't support temperature and use max_completion_tokens
        if ($isReasoningModel) {
            $requestBody['max_completion_tokens'] = $options['max_tokens'] ?? 4096;
        } else {
            $requestBody['temperature'] = $options['temperature'] ?? 0.3;
            $requestBody['max_tokens'] = $options['max_tokens'] ?? 4096;
        }

        $response = Http::withHeaders([
            'Authorization' => "Bearer {$this->apiKey}",
            'Content-Type' => 'application/json',
        ])->timeout(120)->post(self::API_URL, $requestBody);

        $latencyMs = (microtime(true) - $startTime) * 1000;

        if (!$response->successful()) {
            Log::error('OpenAI API error', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new \RuntimeException('OpenAI API error: ' . $response->body());
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
        return 'openai';
    }

    public function getAvailableModels(): array
    {
        return array_keys(self::PRICING);
    }

    public function calculateCost(string $model, int $inputTokens, int $outputTokens): float
    {
        $pricing = self::PRICING[$model] ?? self::PRICING['gpt-4o'];

        $inputCost = ($inputTokens / 1_000_000) * $pricing['input'];
        $outputCost = ($outputTokens / 1_000_000) * $pricing['output'];

        return round($inputCost + $outputCost, 6);
    }
}
