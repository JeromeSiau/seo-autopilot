<?php

namespace App\Services\Image\DTOs;

readonly class GeneratedImage
{
    public function __construct(
        public string $url,
        public string $prompt,
        public string $model,
        public int $width,
        public int $height,
        public float $cost,
        public float $latencyMs,
        public ?string $revisedPrompt = null,
        public ?string $localPath = null,
    ) {}

    public function toArray(): array
    {
        return [
            'url' => $this->url,
            'prompt' => $this->prompt,
            'model' => $this->model,
            'width' => $this->width,
            'height' => $this->height,
            'cost' => $this->cost,
            'latency_ms' => $this->latencyMs,
            'revised_prompt' => $this->revisedPrompt,
            'local_path' => $this->localPath,
        ];
    }
}
