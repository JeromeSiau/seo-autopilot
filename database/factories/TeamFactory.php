<?php

namespace Database\Factories;

use App\Models\Plan;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class TeamFactory extends Factory
{
    protected $model = Team::class;

    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'owner_id' => User::factory(),
            'plan_id' => null,
            'is_trial' => true,
            'trial_ends_at' => now()->addDays(7),
            'plan' => 'starter',
            'articles_limit' => 10,
        ];
    }

    public function withPlan(Plan $plan): static
    {
        return $this->state(fn (array $attributes) => [
            'plan_id' => $plan->id,
        ]);
    }

    public function trialExpired(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_trial' => true,
            'trial_ends_at' => now()->subDay(),
        ]);
    }
}
