<?php

namespace Tests\Unit\Services;

use App\Services\LLM\Providers\VoyageProvider;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class VoyageProviderTest extends TestCase
{
    public function test_embed_returns_vector(): void
    {
        Http::fake([
            'api.voyageai.com/*' => Http::response([
                'data' => [
                    ['embedding' => array_fill(0, 1024, 0.1)]
                ]
            ], 200),
        ]);

        $provider = new VoyageProvider('fake-key');
        $embedding = $provider->embed('Test text');

        $this->assertCount(1024, $embedding);
        Http::assertSent(fn($request) =>
            $request->url() === 'https://api.voyageai.com/v1/embeddings' &&
            $request['model'] === 'voyage-3.5-lite'
        );
    }

    public function test_embed_batch_chunks_large_requests(): void
    {
        Http::fake([
            'api.voyageai.com/*' => Http::sequence()
                ->push([
                    'data' => array_map(
                        fn() => ['embedding' => array_fill(0, 1024, 0.1)],
                        range(1, 128)
                    )
                ], 200)
                ->push([
                    'data' => array_map(
                        fn() => ['embedding' => array_fill(0, 1024, 0.1)],
                        range(1, 72)
                    )
                ], 200),
        ]);

        $provider = new VoyageProvider('fake-key');
        $texts = array_fill(0, 200, 'Test text');
        $embeddings = $provider->embedBatch($texts);

        $this->assertCount(200, $embeddings);
        Http::assertSentCount(2); // 200 texts = 2 batches of 128
    }

    public function test_get_dimension_returns_1024(): void
    {
        $provider = new VoyageProvider('fake-key');
        $this->assertEquals(1024, $provider->getDimension());
    }
}
