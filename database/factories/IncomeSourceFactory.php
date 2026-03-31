<?php

namespace Database\Factories;

use App\Models\IncomeSource;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<IncomeSource>
 */
class IncomeSourceFactory extends Factory
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
            'name' => fake()->randomElement(['Salary', 'Side income', 'Freelance']),
            'currency' => 'MYR',
            'amount_cents' => fake()->numberBetween(1500_00, 12000_00),
            'cadence' => 'monthly',
            'is_active' => true,
        ];
    }
}
