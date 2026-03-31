<?php

namespace Database\Factories;

use App\Models\Commitment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Commitment>
 */
class CommitmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->randomElement(['Rent', 'Insurance', 'Phone plan', 'Family support']),
            'category' => fake()->randomElement(['Housing', 'Insurance', 'Utilities', 'Family']),
            'currency' => 'MYR',
            'amount_cents' => fake()->numberBetween(50_00, 2500_00),
            'due_day' => fake()->optional()->numberBetween(1, 28),
            'cadence' => 'monthly',
            'is_active' => true,
        ];
    }
}
