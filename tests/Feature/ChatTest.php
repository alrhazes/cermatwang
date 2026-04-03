<?php

namespace Tests\Feature;

use App\Models\Commitment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChatTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page_when_visiting_chat(): void
    {
        $this->get('/chat')->assertRedirect('/login');
    }

    public function test_authenticated_users_can_visit_chat(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get('/chat')->assertOk();
    }

    public function test_chat_welcome_suggests_categories_for_commitments_missing_metadata(): void
    {
        $user = User::factory()->awaitingFinancialOnboarding()->create();
        $this->actingAs($user);

        Commitment::factory()->create([
            'user_id' => $user->id,
            'name' => 'Kad Kredit amex',
            'category' => null,
            'due_day' => null,
            'amount_cents' => 295_80,
        ]);

        $response = $this->get('/chat')->assertOk();

        $response->assertSee('suggested categories');
        $response->assertSee('Kad Kredit amex');
        $response->assertSee('Credit Cards');
    }

    public function test_chat_welcome_suggests_categories_after_onboarding_completed(): void
    {
        $user = User::factory()->create([
            'onboarding_completed_at' => now(),
        ]);
        $this->actingAs($user);

        Commitment::factory()->create([
            'user_id' => $user->id,
            'name' => 'Loan Kereta',
            'category' => null,
            'due_day' => null,
            'amount_cents' => 745_00,
        ]);

        $response = $this->get('/chat')->assertOk();

        $response->assertSee('suggested categories');
        $response->assertSee('Loan Kereta');
        $response->assertSee('Transport');
    }

    public function test_legacy_dashboard_path_redirects_to_chat(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get('/dashboard')->assertRedirect('/chat');
    }
}
