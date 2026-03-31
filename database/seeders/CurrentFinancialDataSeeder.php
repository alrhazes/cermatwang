<?php

namespace Database\Seeders;

use App\Models\Commitment;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class CurrentFinancialDataSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::query()->firstOrCreate(
            ['email' => 'test@example.com'],
            [
                'name' => 'Test User',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'onboarding_completed_at' => now(),
            ],
        );

        $commitments = [
            ['name' => 'EasyPinjamanRHB', 'amount' => 776.21],
            ['name' => 'Loan Kereta', 'amount' => 745],
            ['name' => 'Kad Kredit visa', 'amount' => 586.20],
            ['name' => 'Kad Kredit amex', 'amount' => 295.80],
            ['name' => 'Housing Loan', 'amount' => 2013],
            ['name' => 'CIMB', 'amount' => 600],
        ];

        foreach ($commitments as $commitment) {
            Commitment::query()->updateOrCreate(
                [
                    'user_id' => $user->id,
                    'name' => $commitment['name'],
                ],
                [
                    'category' => null,
                    'currency' => 'MYR',
                    'amount_cents' => $this->toCents($commitment['amount']),
                    'due_day' => null,
                    'cadence' => 'monthly',
                    'is_active' => true,
                ],
            );
        }
    }

    private function toCents(float|int|string $amount): int
    {
        return (int) round(((float) $amount) * 100);
    }
}
