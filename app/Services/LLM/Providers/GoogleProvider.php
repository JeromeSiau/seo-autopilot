<?php

namespace App\Services\LLM\Providers;

use App\Services\LLM\Contracts\LLMProviderInterface;
use App\Services\LLM\DTOs\LLMResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GoogleProvider implements LLMProviderInterface
{
    private const API_URL = 'https://generativelanguage.googleapis.com/v1beta/models';

    // Pricing per 1M tokens (December 2025)
    private const PRICING = [
        'gemini-2.5-flash-lite' => ['input' => 0.10, 'output' => 0.40],
        'gemini-2.5-flash' => ['input' => 0.15, 'output' => 0.60],
        'gemini-2.5-pro' => ['input' => 1.25, 'output' => 10.00],
        'gemini-2.0-flash' => ['input' => 0.10, 'output' => 0.40],
    ];

    public function __construct(
        private readonly string $apiKey,
        private readonly string $defaultModel = 'gemini-2.5-flash-lite',
    ) {}

    public function complete(string $prompt, array $options = []): LLMResponse
    {
        $model = $options['model'] ?? $this->defaultModel;
        $startTime = microtime(true);

        $url = self::API_URL . "/{$model}:generateContent?key={$this->apiKey}";

        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
        ])->timeout(120)->post($url, [
            'contents' => [
                [
                    'parts' => [
                        ['text' => $prompt],
                    ],
                ],
            ],
            'generationConfig' => [
                'temperature' => $options['temperature'] ?? 0.7,
                'maxOutputTokens' => $options['max_tokens'] ?? 4096,
            ],
        ]);

        $latencyMs = (microtime(true) - $startTime) * 1000;

        if (!$response->successful()) {
            Log::error('Google AI API error', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new \RuntimeException('Google AI API error: ' . $response->body());
        }

        $data = $response->json();
        $usage = $data['usageMetadata'] ?? [];
        $inputTokens = $usage['promptTokenCount'] ?? 0;
        $outputTokens = $usage['candidatesTokenCount'] ?? 0;

        $content = '';
        $candidates = $data['candidates'] ?? [];
        if (!empty($candidates[0]['content']['parts'])) {
            foreach ($candidates[0]['content']['parts'] as $part) {
                $content .= $part['text'] ?? '';
            }
        }

        return new LLMResponse(
            content: $content,
            model: $model,
            inputTokens: $inputTokens,
            outputTokens: $outputTokens,
            cost: $this->calculateCost($model, $inputTokens, $outputTokens),
            latencyMs: $latencyMs,
            finishReason: $candidates[0]['finishReason'] ?? null,
        );
    }

    public function completeJson(string $prompt, array $schema = [], array $options = []): LLMResponse
    {
        $model = $options['model'] ?? $this->defaultModel;
        $startTime = microtime(true);

        $url = self::API_URL . "/{$model}:generateContent?key={$this->apiKey}";

        $requestBody = [
            'contents' => [
                [
                    'parts' => [
                        ['text' => $prompt],
                    ],
                ],
            ],
            'generationConfig' => [
                'temperature' => $options['temperature'] ?? 0.3,
                'maxOutputTokens' => $options['max_tokens'] ?? 4096,
                'responseMimeType' => 'application/json',
            ],
        ];

        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
        ])->timeout(120)->post($url, $requestBody);

        $latencyMs = (microtime(true) - $startTime) * 1000;

        if (!$response->successful()) {
            Log::error('Google AI API error', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new \RuntimeException('Google AI API error: ' . $response->body());
        }

        $data = $response->json();
        $usage = $data['usageMetadata'] ?? [];
        $inputTokens = $usage['promptTokenCount'] ?? 0;
        $outputTokens = $usage['candidatesTokenCount'] ?? 0;

        $content = '';
        $candidates = $data['candidates'] ?? [];
        if (!empty($candidates[0]['content']['parts'])) {
            foreach ($candidates[0]['content']['parts'] as $part) {
                $content .= $part['text'] ?? '';
            }
        }

        return new LLMResponse(
            content: $content,
            model: $model,
            inputTokens: $inputTokens,
            outputTokens: $outputTokens,
            cost: $this->calculateCost($model, $inputTokens, $outputTokens),
            latencyMs: $latencyMs,
            finishReason: $candidates[0]['finishReason'] ?? null,
        );
    }

    public function getName(): string
    {
        return 'google';
    }

    public function getAvailableModels(): array
    {
        return array_keys(self::PRICING);
    }

    public function calculateCost(string $model, int $inputTokens, int $outputTokens): float
    {
        $pricing = self::PRICING[$model] ?? self::PRICING['gemini-2.5-flash-lite'];

        $inputCost = ($inputTokens / 1_000_000) * $pricing['input'];
        $outputCost = ($outputTokens / 1_000_000) * $pricing['output'];

        return round($inputCost + $outputCost, 6);
    }
}
