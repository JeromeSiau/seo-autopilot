<?php

namespace App\Services\LLM\Contracts;

use App\Services\LLM\DTOs\LLMResponse;

interface LLMProviderInterface
{
    /**
     * Send a completion request to the LLM.
     */
    public function complete(
        string $prompt,
        array $options = []
    ): LLMResponse;

    /**
     * Send a completion request expecting JSON response.
     */
    public function completeJson(
        string $prompt,
        array $schema = [],
        array $options = []
    ): LLMResponse;

    /**
     * Get the provider name.
     */
    public function getName(): string;

    /**
     * Get available models for this provider.
     */
    public function getAvailableModels(): array;

    /**
     * Calculate cost for given token counts.
     */
    public function calculateCost(string $model, int $inputTokens, int $outputTokens): float;
}
