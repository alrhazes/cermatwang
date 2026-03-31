<?php

namespace Tests\Feature;

use App\Models\Commitment;
use App\Models\Debt;
use App\Models\IncomeSource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FinancialOnboardingTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_complete_financial_onboarding(): void
    {
        $user = User::factory()->awaitingFinancialOnboarding()->create();

        $response = $this->actingAs($user)->post(route('chat.onboarding.complete', absolute: false));

        $response->assertRedirect(route('chat', absolute: false));
        $this->assertNotNull($user->fresh()->onboarding_completed_at);
    }

    public function test_chat_messages_include_onboarding_instructions_for_new_users(): void
    {
        Http::fake([
            'api.openai.com/v1/chat/completions' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'role' => 'assistant',
                            'content' => 'Got it.',
                        ],
                    ],
                ],
            ], 200),
        ]);

        config([
            'services.openai.api_key' => 'test-key',
            'services.openai.base_url' => 'https://api.openai.com/v1',
            'services.openai.model' => 'gpt-4o-mini',
        ]);

        $user = User::factory()->awaitingFinancialOnboarding()->create();

        $this->actingAs($user)->postJson('/chat/messages', [
            'messages' => [
                ['role' => 'user', 'content' => 'I earn about RM5k'],
            ],
        ])->assertOk();

        Http::assertSent(function ($request) {
            $system = data_get($request->data(), 'messages.0.content');

            return is_string($system)
                && str_contains($system, 'first_time_financial_setup: YES')
                && str_contains($system, 'You lead first')
                && str_contains($system, 'Saved financial profile')
                && str_contains($system, 'Financial onboarding detail');
        });
    }

    public function test_chat_messages_omit_onboarding_instructions_after_completed(): void
    {
        Http::fake([
            'api.openai.com/v1/chat/completions' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'role' => 'assistant',
                            'content' => 'Sure.',
                        ],
                    ],
                ],
            ], 200),
        ]);

        config([
            'services.openai.api_key' => 'test-key',
            'services.openai.base_url' => 'https://api.openai.com/v1',
            'services.openai.model' => 'gpt-4o-mini',
        ]);

        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/chat/messages', [
            'messages' => [
                ['role' => 'user', 'content' => 'Hello'],
            ],
        ])->assertOk();

        Http::assertSent(function ($request) {
            $system = data_get($request->data(), 'messages.0.content');

            return is_string($system)
                && str_contains($system, 'first_time_financial_setup: NO')
                && str_contains($system, 'Saved financial profile')
                && str_contains($system, 'dedicated personal financial advisor')
                && ! str_contains($system, 'Financial onboarding detail');
        });
    }

    public function test_structured_financial_profile_is_injected_when_present(): void
    {
        Http::fake([
            'api.openai.com/v1/chat/completions' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'role' => 'assistant',
                            'content' => 'Noted.',
                        ],
                    ],
                ],
            ], 200),
        ]);

        config([
            'services.openai.api_key' => 'test-key',
            'services.openai.base_url' => 'https://api.openai.com/v1',
            'services.openai.model' => 'gpt-4o-mini',
        ]);

        $user = User::factory()->create([
            'financial_profile' => [
                'monthly_take_home' => 'RM 4,800',
                'housing' => 'RM 1,200 rent',
            ],
        ]);

        $this->actingAs($user)->postJson('/chat/messages', [
            'messages' => [
                ['role' => 'user', 'content' => 'Hi'],
            ],
        ])->assertOk();

        Http::assertSent(function ($request) {
            $system = data_get($request->data(), 'messages.0.content');

            return is_string($system)
                && str_contains($system, '**monthly take home:** RM 4,800')
                && str_contains($system, '**housing:** RM 1,200 rent');
        });
    }

    public function test_income_commitment_and_debt_tables_are_injected_into_system_prompt(): void
    {
        Http::fake([
            'api.openai.com/v1/chat/completions' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'role' => 'assistant',
                            'content' => 'Ok.',
                        ],
                    ],
                ],
            ], 200),
        ]);

        config([
            'services.openai.api_key' => 'test-key',
            'services.openai.base_url' => 'https://api.openai.com/v1',
            'services.openai.model' => 'gpt-4o-mini',
        ]);

        $user = User::factory()->create();

        IncomeSource::factory()->for($user)->create([
            'name' => 'Salary',
            'amount_cents' => 5000_00,
        ]);

        Commitment::factory()->for($user)->create([
            'name' => 'Rent',
            'category' => 'Housing',
            'amount_cents' => 1200_00,
            'due_day' => 1,
        ]);

        Debt::factory()->for($user)->create([
            'type' => 'credit_card',
            'name' => 'Maybank CC',
            'balance_cents' => 3200_00,
            'minimum_payment_cents' => 150_00,
        ]);

        $this->actingAs($user)->postJson('/chat/messages', [
            'messages' => [
                ['role' => 'user', 'content' => 'Hi'],
            ],
        ])->assertOk();

        Http::assertSent(function ($request) {
            $system = data_get($request->data(), 'messages.0.content');

            return is_string($system)
                && str_contains($system, 'Structured financial records')
                && str_contains($system, 'Income sources')
                && str_contains($system, 'Salary: MYR 5000.00')
                && str_contains($system, 'Monthly commitments')
                && str_contains($system, 'Rent (Housing): MYR 1200.00')
                && str_contains($system, 'Debts / credit')
                && str_contains($system, 'Maybank CC (credit_card): MYR 3200.00');
        });
    }
}
