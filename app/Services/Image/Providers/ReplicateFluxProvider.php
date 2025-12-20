<?php

namespace App\Services\Image\Providers;

use App\Services\Image\Contracts\ImageProviderInterface;
use App\Services\Image\DTOs\GeneratedImage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ReplicateFluxProvider implements ImageProviderInterface
{
    private const API_URL = 'https://api.replicate.com/v1';

    // Model versions on Replicate
    private const MODELS = [
        'flux-1.1-pro' => 'black-forest-labs/flux-1.1-pro',
        'flux-pro' => 'black-forest-labs/flux-pro',
        'flux-dev' => 'black-forest-labs/flux-dev',
        'flux-schnell' => 'black-forest-labs/flux-schnell',
    ];

    // Pricing per image (Replicate December 2025)
    private const PRICING = [
        'flux-1.1-pro' => 0.04,
        'flux-pro' => 0.055,
        'flux-dev' => 0.025,
        'flux-schnell' => 0.003,
    ];

    public function __construct(
        private readonly string $apiKey,
        private readonly string $defaultModel = 'flux-1.1-pro',
    ) {}

    public function generate(string $prompt, array $options = []): GeneratedImage
    {
        $model = $options['model'] ?? $this->defaultModel;
        $modelId = self::MODELS[$model] ?? self::MODELS['flux-1.1-pro'];

        $aspectRatio = $options['aspect_ratio'] ?? '16:9';
        $width = $options['width'] ?? 1344;
        $height = $options['height'] ?? 768;

        $startTime = microtime(true);

        // Step 1: Create prediction
        $response = Http::withHeaders([
            'Authorization' => "Bearer {$this->apiKey}",
            'Content-Type' => 'application/json',
            'Prefer' => 'wait', // Wait for result instead of polling
        ])->timeout(120)->post(self::API_URL . '/models/' . $modelId . '/predictions', [
            'input' => [
                'prompt' => $prompt,
                'aspect_ratio' => $aspectRatio,
                'width' => $width,
                'height' => $height,
                'output_format' => $options['format'] ?? 'webp',
                'output_quality' => $options['quality'] ?? 90,
            ],
        ]);

        if (!$response->successful()) {
            Log::error('Replicate API error', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new \RuntimeException('Replicate API error: ' . $response->body());
        }

        $data = $response->json();

        // If status is not succeeded, we need to poll
        if (($data['status'] ?? '') !== 'succeeded') {
            $data = $this->pollForResult($data['id']);
        }

        $latencyMs = (microtime(true) - $startTime) * 1000;

        $output = $data['output'] ?? null;
        $imageUrl = is_array($output) ? ($output[0] ?? '') : ($output ?? '');

        if (empty($imageUrl)) {
            throw new \RuntimeException('Replicate did not return an image URL');
        }

        return new GeneratedImage(
            url: $imageUrl,
            prompt: $prompt,
            model: $model,
            width: $width,
            height: $height,
            cost: $this->calculateCost($model, $options),
            latencyMs: $latencyMs,
        );
    }

    private function pollForResult(string $predictionId, int $maxAttempts = 60): array
    {
        $attempts = 0;

        while ($attempts < $maxAttempts) {
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$this->apiKey}",
            ])->get(self::API_URL . '/predictions/' . $predictionId);

            if (!$response->successful()) {
                throw new \RuntimeException('Replicate polling error: ' . $response->body());
            }

            $data = $response->json();
            $status = $data['status'] ?? '';

            if ($status === 'succeeded') {
                return $data;
            }

            if ($status === 'failed' || $status === 'canceled') {
                throw new \RuntimeException('Replicate generation failed: ' . ($data['error'] ?? 'Unknown error'));
            }

            // Still processing, wait and retry
            usleep(500000); // 500ms
            $attempts++;
        }

        throw new \RuntimeException('Replicate generation timed out');
    }

    public function getName(): string
    {
        return 'replicate';
    }

    public function getAvailableModels(): array
    {
        return array_keys(self::MODELS);
    }

    public function calculateCost(string $model, array $options = []): float
    {
        return self::PRICING[$model] ?? self::PRICING['flux-1.1-pro'];
    }
}
