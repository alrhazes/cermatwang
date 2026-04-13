<?php

namespace Database\Factories;

use App\Models\ExpenseEntry;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExpenseEntry>
 */
class ExpenseEntryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $spentAt = fake()->dateTimeThisMonth();

        return [
            'user_id' => User::factory(),
            'spent_at' => $spentAt,
            'year_month' => $spentAt->format('Y-m'),
            'category' => fake()->randomElement(['Food', 'Transport', 'Utilities', 'Other']),
            'amount_cents' => fake()->numberBetween(500, 50_000),
            'currency' => 'MYR',
            'place_label' => fake()->optional(0.4)->company(),
            'latitude' => null,
            'longitude' => null,
            'location_accuracy_m' => null,
            'notes' => null,
        ];
    }
}
