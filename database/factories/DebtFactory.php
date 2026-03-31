<?php

namespace Database\Factories;

use App\Models\Debt;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Debt>
 */
class DebtFactory extends Factory
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
            'type' => fake()->randomElement(['credit_card', 'personal_loan', 'car_loan', 'bnpl']),
            'name' => fake()->randomElement(['Maybank CC', 'CIMB CC', 'Personal Loan', 'Car Loan']),
            'currency' => 'MYR',
            'balance_cents' => fake()->numberBetween(200_00, 45000_00),
            'minimum_payment_cents' => fake()->optional()->numberBetween(25_00, 1500_00),
            'apr_bps' => fake()->optional()->numberBetween(300, 2499),
            'due_day' => fake()->optional()->numberBetween(1, 28),
            'credit_limit_cents' => fake()->optional()->numberBetween(1000_00, 60000_00),
            'is_active' => true,
        ];
    }
}
