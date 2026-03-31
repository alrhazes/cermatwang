<?php

namespace Tests\Feature;

use App\Models\Commitment;
use App\Models\User;
use Database\Seeders\CurrentFinancialDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CurrentFinancialDataSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_seeds_current_commitments_for_test_user(): void
    {
        $this->seed(CurrentFinancialDataSeeder::class);

        $user = User::query()->where('email', 'test@example.com')->firstOrFail();

        $this->assertSame(6, Commitment::query()->where('user_id', $user->id)->count());

        $this->assertSame(77621, (int) $user->commitments()->where('name', 'EasyPinjamanRHB')->value('amount_cents'));
        $this->assertSame(74500, (int) $user->commitments()->where('name', 'Loan Kereta')->value('amount_cents'));
        $this->assertSame(58620, (int) $user->commitments()->where('name', 'Kad Kredit visa')->value('amount_cents'));
        $this->assertSame(29580, (int) $user->commitments()->where('name', 'Kad Kredit amex')->value('amount_cents'));
        $this->assertSame(201300, (int) $user->commitments()->where('name', 'Housing Loan')->value('amount_cents'));
        $this->assertSame(60000, (int) $user->commitments()->where('name', 'CIMB')->value('amount_cents'));

        $this->assertFalse($user->needsFinancialOnboarding());
    }
}
