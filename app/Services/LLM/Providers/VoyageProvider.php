<?php

namespace App\Services\LLM\Providers;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class VoyageProvider
{
    private const API_URL = 'https://api.voyageai.com/v1/embeddings';
    private const MODEL = 'voyage-3.5-lite';
    private const DIMENSION = 1024;
    private const MAX_BATCH_SIZE = 128;

    public function __construct(
        private readonly string $apiKey,
    ) {}

    /**
     * Generate embedding for a single text.
     *
     * @return array<float>
     */
    public function embed(string $text, string $inputType = 'document'): array
    {
        $response = Http::withHeaders([
            'Authorization' => "Bearer {$this->apiKey}",
            'Content-Type' => 'application/json',
        ])->timeout(30)->post(self::API_URL, [
            'model' => self::MODEL,
            'input' => [$text],
            'input_type' => $inputType,
        ]);

        if (!$response->successful()) {
            Log::error('Voyage API error', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new \RuntimeException('Voyage API error: ' . $response->body());
        }

        return $response->json('data.0.embedding');
    }

    /**
     * Generate embeddings for multiple texts (batch).
     *
     * @param array<string> $texts
     * @return array<array<float>>
     */
    public function embedBatch(array $texts, string $inputType = 'document'): array
    {
        $results = [];

        foreach (array_chunk($texts, self::MAX_BATCH_SIZE) as $chunk) {
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$this->apiKey}",
                'Content-Type' => 'application/json',
            ])->timeout(60)->post(self::API_URL, [
                'model' => self::MODEL,
                'input' => $chunk,
                'input_type' => $inputType,
            ]);

            if (!$response->successful()) {
                Log::error('Voyage API error', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                throw new \RuntimeException('Voyage API error: ' . $response->body());
            }

            foreach ($response->json('data') as $item) {
                $results[] = $item['embedding'];
            }
        }

        return $results;
    }

    public function getDimension(): int
    {
        return self::DIMENSION;
    }
}
