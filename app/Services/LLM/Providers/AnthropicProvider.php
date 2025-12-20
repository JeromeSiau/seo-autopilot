<?php

namespace App\Services\LLM\Providers;

use App\Services\LLM\Contracts\LLMProviderInterface;
use App\Services\LLM\DTOs\LLMResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AnthropicProvider implements LLMProviderInterface
{
    private const API_URL = 'https://api.anthropic.com/v1/messages';
    private const API_VERSION = '2023-06-01';

    // Pricing per 1M tokens (December 2025)
    private const PRICING = [
        'claude-sonnet-4-5-20241022' => ['input' => 3.00, 'output' => 15.00],
        'claude-3-5-sonnet-20241022' => ['input' => 3.00, 'output' => 15.00],
        'claude-3-5-haiku-20241022' => ['input' => 1.00, 'output' => 5.00],
        'claude-3-opus-20240229' => ['input' => 15.00, 'output' => 75.00],
    ];

    // Model aliases for easier use
    private const MODEL_ALIASES = [
        'claude-sonnet' => 'claude-sonnet-4-5-20241022',
        'claude-haiku' => 'claude-3-5-haiku-20241022',
        'claude-opus' => 'claude-3-opus-20240229',
    ];

    public function __construct(
        private readonly string $apiKey,
        private readonly string $defaultModel = 'claude-sonnet-4-5-20241022',
    ) {}

    public function complete(string $prompt, array $options = []): LLMResponse
    {
        $model = $this->resolveModel($options['model'] ?? $this->defaultModel);
        $startTime = microtime(true);

        $response = Http::withHeaders([
            'x-api-key' => $this->apiKey,
            'anthropic-version' => self::API_VERSION,
            'Content-Type' => 'application/json',
        ])->timeout(120)->post(self::API_URL, [
            'model' => $model,
            'max_tokens' => $options['max_tokens'] ?? 4096,
            'messages' => [
                ['role' => 'user', 'content' => $prompt],
            ],
            'temperature' => $options['temperature'] ?? 0.7,
        ]);

        $latencyMs = (microtime(true) - $startTime) * 1000;

        if (!$response->successful()) {
            Log::error('Anthropic API error', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new \RuntimeException('Anthropic API error: ' . $response->body());
        }

        $data = $response->json();
        $usage = $data['usage'] ?? [];
        $inputTokens = $usage['input_tokens'] ?? 0;
        $outputTokens = $usage['output_tokens'] ?? 0;

        $content = '';
        foreach ($data['content'] ?? [] as $block) {
            if ($block['type'] === 'text') {
                $content .= $block['text'];
            }
        }

        return new LLMResponse(
            content: $content,
            model: $model,
            inputTokens: $inputTokens,
            outputTokens: $outputTokens,
            cost: $this->calculateCost($model, $inputTokens, $outputTokens),
            latencyMs: $latencyMs,
            finishReason: $data['stop_reason'] ?? null,
        );
    }

    public function completeJson(string $prompt, array $schema = [], array $options = []): LLMResponse
    {
        // Claude doesn't have native JSON mode, so we add instructions
        $jsonPrompt = $prompt . "\n\nRespond with valid JSON only. No markdown formatting, no code blocks, just the raw JSON object.";

        return $this->complete($jsonPrompt, $options);
    }

    public function getName(): string
    {
        return 'anthropic';
    }

    public function getAvailableModels(): array
    {
        return array_merge(
            array_keys(self::PRICING),
            array_keys(self::MODEL_ALIASES)
        );
    }

    public function calculateCost(string $model, int $inputTokens, int $outputTokens): float
    {
        $resolvedModel = $this->resolveModel($model);
        $pricing = self::PRICING[$resolvedModel] ?? self::PRICING['claude-sonnet-4-5-20241022'];

        $inputCost = ($inputTokens / 1_000_000) * $pricing['input'];
        $outputCost = ($outputTokens / 1_000_000) * $pricing['output'];

        return round($inputCost + $outputCost, 6);
    }

    private function resolveModel(string $model): string
    {
        return self::MODEL_ALIASES[$model] ?? $model;
    }
}
