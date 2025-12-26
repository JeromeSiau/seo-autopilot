<?php

namespace Database\Factories;

use App\Models\Plan;
use Illuminate\Database\Eloquent\Factories\Factory;

class PlanFactory extends Factory
{
    protected $model = Plan::class;

    public function definition(): array
    {
        return [
            'slug' => $this->faker->unique()->slug(2),
            'name' => $this->faker->words(2, true),
            'price' => $this->faker->randomElement([39, 99, 249]),
            'articles_per_month' => $this->faker->randomElement([8, 30, 100]),
            'sites_limit' => $this->faker->randomElement([1, 3, -1]),
            'stripe_price_id_live' => null,
            'stripe_price_id_test' => null,
            'features' => ['Feature 1', 'Feature 2'],
            'is_active' => true,
            'sort_order' => 0,
        ];
    }

    public function starter(): static
    {
        return $this->state([
            'slug' => 'starter',
            'name' => 'Starter',
            'price' => 39,
            'articles_per_month' => 8,
            'sites_limit' => 1,
        ]);
    }

    public function pro(): static
    {
        return $this->state([
            'slug' => 'pro',
            'name' => 'Pro',
            'price' => 99,
            'articles_per_month' => 30,
            'sites_limit' => 3,
        ]);
    }

    public function agency(): static
    {
        return $this->state([
            'slug' => 'agency',
            'name' => 'Agency',
            'price' => 249,
            'articles_per_month' => 100,
            'sites_limit' => -1,
        ]);
    }
}
