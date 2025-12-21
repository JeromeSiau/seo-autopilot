<?php

namespace App\Services\Image;

use App\Models\Article;
use App\Services\Image\Contracts\ImageProviderInterface;
use App\Services\Image\DTOs\GeneratedImage;
use App\Services\Image\Providers\ReplicateFluxProvider;
use App\Services\LLM\LLMManager;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ImageGenerator
{
    private ImageProviderInterface $provider;
    private float $totalCost = 0;

    public function __construct(
        private readonly LLMManager $llm,
    ) {
        $this->initializeProvider();
    }

    private function initializeProvider(): void
    {
        $apiKey = config('services.replicate.api_key');

        if (!$apiKey) {
            throw new \RuntimeException('Replicate API key not configured');
        }

        $this->provider = new ReplicateFluxProvider($apiKey);
    }

    /**
     * Generate a featured image for an article.
     */
    public function generateFeaturedImage(Article $article, array $options = []): GeneratedImage
    {
        Log::info("Generating featured image for article: {$article->title}");

        // Generate an optimized prompt using LLM
        $prompt = $this->generateImagePrompt($article, 'featured');

        $image = $this->provider->generate($prompt, array_merge([
            'aspect_ratio' => '16:9',
            'model' => 'flux-1.1-pro',
        ], $options));

        $this->totalCost += $image->cost;

        // Download and store the image
        $storedImage = $this->downloadAndStore($image, $article, 'featured');

        Log::info("Featured image generated", [
            'article_id' => $article->id,
            'cost' => $image->cost,
            'latency_ms' => $image->latencyMs,
        ]);

        return $storedImage;
    }

    /**
     * Generate section images for an article.
     */
    public function generateSectionImages(
        Article $article,
        int $count = 2,
        array $options = []
    ): array {
        Log::info("Generating {$count} section images for article: {$article->title}");

        $images = [];

        for ($i = 0; $i < $count; $i++) {
            $prompt = $this->generateImagePrompt($article, 'section', $i);

            $image = $this->provider->generate($prompt, array_merge([
                'aspect_ratio' => '16:9',
                'model' => 'flux-schnell', // Faster/cheaper for section images
            ], $options));

            $this->totalCost += $image->cost;
            $storedImage = $this->downloadAndStore($image, $article, "section-{$i}");
            $images[] = $storedImage;
        }

        return $images;
    }

    /**
     * Generate all images for an article (featured + sections).
     */
    public function generateAllImages(
        Article $article,
        int $sectionImageCount = 2,
        array $options = []
    ): array {
        $this->totalCost = 0;

        $images = [
            'featured' => $this->generateFeaturedImage($article, $options),
            'sections' => $this->generateSectionImages($article, $sectionImageCount, $options),
        ];

        Log::info("All images generated for article", [
            'article_id' => $article->id,
            'total_cost' => $this->totalCost,
            'image_count' => 1 + count($images['sections']),
        ]);

        return $images;
    }

    /**
     * Use LLM to generate an optimized image prompt.
     */
    private function generateImagePrompt(
        Article $article,
        string $type,
        int $sectionIndex = 0
    ): string {
        $articleContext = Str::limit(strip_tags($article->content), 1000);

        $typeInstructions = match ($type) {
            'featured' => 'Create a compelling hero image that captures the essence of the entire article.',
            'section' => "Create an image for section {$sectionIndex} that illustrates a specific concept from the article.",
            default => 'Create a relevant image for this content.',
        };

        $prompt = <<<PROMPT
You are an expert at creating image generation prompts for FLUX AI.

Article Title: {$article->title}
Article Content Preview:
{$articleContext}

{$typeInstructions}

Create a detailed image prompt that:
1. Is specific and descriptive (style, lighting, composition)
2. Avoids text/words in the image
3. Uses a professional, modern aesthetic
4. Is relevant to the article topic
5. Would work well as a blog/article image

Respond with ONLY the image prompt, nothing else. Keep it under 200 words.
PROMPT;

        $response = $this->llm->complete('openai', $prompt, [
            'model' => 'gpt-4o-mini',
            'temperature' => 0.8,
            'max_tokens' => 300,
        ]);

        $this->totalCost += $response->cost;

        return trim($response->content);
    }

    /**
     * Download image from URL and store locally.
     */
    private function downloadAndStore(
        GeneratedImage $image,
        Article $article,
        string $suffix
    ): GeneratedImage {
        $response = Http::timeout(60)->get($image->url);

        if (!$response->successful()) {
            Log::warning("Failed to download image", ['url' => $image->url]);
            return $image;
        }

        $extension = $this->getExtensionFromUrl($image->url) ?: 'webp';
        $filename = "articles/{$article->id}/{$suffix}-" . Str::random(8) . ".{$extension}";

        Storage::disk('public')->put($filename, $response->body());

        return new GeneratedImage(
            url: $image->url,
            prompt: $image->prompt,
            model: $image->model,
            width: $image->width,
            height: $image->height,
            cost: $image->cost,
            latencyMs: $image->latencyMs,
            revisedPrompt: $image->revisedPrompt,
            localPath: $filename,
        );
    }

    private function getExtensionFromUrl(string $url): ?string
    {
        $path = parse_url($url, PHP_URL_PATH);
        if (!$path) {
            return null;
        }

        $extension = pathinfo($path, PATHINFO_EXTENSION);
        return in_array($extension, ['jpg', 'jpeg', 'png', 'webp', 'gif']) ? $extension : null;
    }

    public function getTotalCost(): float
    {
        return $this->totalCost;
    }

    public function resetCost(): void
    {
        $this->totalCost = 0;
    }
}
