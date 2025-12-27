<?php

namespace Database\Factories;

use App\Models\Keyword;
use App\Models\Site;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Keyword>
 */
class KeywordFactory extends Factory
{
    protected $model = Keyword::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'site_id' => Site::factory(),
            'keyword' => fake()->words(3, true),
            'volume' => fake()->numberBetween(100, 10000),
            'difficulty' => fake()->numberBetween(1, 100),
            'cpc' => fake()->randomFloat(2, 0.1, 10),
            'status' => fake()->randomElement(['pending', 'queued', 'scheduled', 'generating', 'completed']),
            'source' => fake()->randomElement(['gsc', 'manual', 'discovery']),
            'priority' => fake()->numberBetween(1, 10),
        ];
    }

    /**
     * Indicate that the keyword is queued.
     */
    public function queued(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'queued',
            'queued_at' => now(),
        ]);
    }

    /**
     * Indicate that the keyword is processed.
     */
    public function processed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'processed',
            'processed_at' => now(),
        ]);
    }
}
