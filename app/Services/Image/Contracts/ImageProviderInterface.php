<?php

namespace App\Services\Image\Contracts;

use App\Services\Image\DTOs\GeneratedImage;

interface ImageProviderInterface
{
    /**
     * Generate an image from a prompt.
     */
    public function generate(
        string $prompt,
        array $options = []
    ): GeneratedImage;

    /**
     * Get the provider name.
     */
    public function getName(): string;

    /**
     * Get available models.
     */
    public function getAvailableModels(): array;

    /**
     * Calculate cost for an image generation.
     */
    public function calculateCost(string $model, array $options = []): float;
}
