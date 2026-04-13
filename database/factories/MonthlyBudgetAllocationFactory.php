<?php

namespace Database\Factories;

use App\Models\MonthlyBudgetAllocation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MonthlyBudgetAllocation>
 */
class MonthlyBudgetAllocationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'year_month' => now()->format('Y-m'),
            'category' => fake()->randomElement(['Food', 'Transport', 'Utilities', 'Other']),
            'amount_cents' => fake()->numberBetween(100_00, 2000_00),
            'currency' => 'MYR',
            'notes' => null,
        ];
    }
}
