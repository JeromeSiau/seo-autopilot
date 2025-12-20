<?php

namespace App\Services\LLM\DTOs;

class LLMResponse
{
    public function __construct(
        public readonly string $content,
        public readonly string $model,
        public readonly int $inputTokens,
        public readonly int $outputTokens,
        public readonly float $cost,
        public readonly float $latencyMs,
        public readonly ?string $finishReason = null,
    ) {}

    public function toArray(): array
    {
        return [
            'content' => $this->content,
            'model' => $this->model,
            'input_tokens' => $this->inputTokens,
            'output_tokens' => $this->outputTokens,
            'cost' => $this->cost,
            'latency_ms' => $this->latencyMs,
            'finish_reason' => $this->finishReason,
        ];
    }

    public function getJson(): ?array
    {
        $content = $this->content;

        // Try to extract JSON from markdown code blocks
        if (preg_match('/```(?:json)?\s*([\s\S]*?)```/', $content, $matches)) {
            $content = trim($matches[1]);
        }

        $decoded = json_decode($content, true);

        return json_last_error() === JSON_ERROR_NONE ? $decoded : null;
    }
}
