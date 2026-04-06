<?php

namespace Tests\Feature;

use App\Models\Commitment;
use App\Models\IncomeSource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ChatMessageTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_post_chat_messages(): void
    {
        $response = $this->postJson('/chat/messages', [
            'messages' => [
                ['role' => 'user', 'content' => 'Hello'],
            ],
        ]);

        $response->assertUnauthorized();
    }

    public function test_authenticated_user_receives_assistant_content_from_openai(): void
    {
        Http::fake([
            'api.openai.com/v1/chat/completions' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'role' => 'assistant',
                            'content' => 'Here is a simple budgeting tip.',
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

        $response = $this->actingAs($user)->postJson('/chat/messages', [
            'messages' => [
                ['role' => 'user', 'content' => 'Any tips?'],
            ],
        ]);

        $response->assertOk();
        $response->assertJson([
            'content' => 'Here is a simple budgeting tip.',
        ]);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.openai.com/v1/chat/completions'
                && $request->hasHeader('Authorization', 'Bearer test-key')
                && data_get($request->data(), 'model') === 'gpt-4o-mini'
                && data_get($request->data(), 'messages.0.role') === 'system'
                && data_get($request->data(), 'messages.1.role') === 'user'
                && data_get($request->data(), 'messages.1.content') === 'Any tips?';
        });
    }

    public function test_validation_rejects_invalid_payload(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/chat/messages', [
            'messages' => [
                ['role' => 'system', 'content' => 'nope'],
            ],
        ]);

        $response->assertUnprocessable();
    }

    public function test_returns_503_when_api_key_missing(): void
    {
        Http::fake();

        config([
            'services.openai.api_key' => '',
            'services.openai.base_url' => 'https://api.openai.com/v1',
        ]);

        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/chat/messages', [
            'messages' => [
                ['role' => 'user', 'content' => 'Hello'],
            ],
        ]);

        $response->assertStatus(503);
        $response->assertJsonFragment(['message' => 'The assistant is not configured. Add OPENAI_API_KEY to your environment.']);

        Http::assertNothingSent();
    }

    public function test_openai_error_response_is_surfaced_to_client(): void
    {
        Http::fake([
            'api.openai.com/v1/chat/completions' => Http::response([
                'error' => [
                    'message' => 'Incorrect API key provided: sk-***',
                    'type' => 'invalid_request_error',
                    'code' => 'invalid_api_key',
                ],
            ], 401),
        ]);

        config([
            'services.openai.api_key' => 'invalid',
            'services.openai.base_url' => 'https://api.openai.com/v1',
            'services.openai.model' => 'gpt-4o-mini',
        ]);

        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/chat/messages', [
            'messages' => [
                ['role' => 'user', 'content' => 'Hello'],
            ],
        ]);

        $response->assertStatus(502);
        $response->assertJsonFragment(['message' => 'Incorrect API key provided: sk-***']);
    }

    public function test_tool_calls_can_create_financial_rows(): void
    {
        Http::fake([
            'api.openai.com/v1/chat/completions' => Http::sequence()
                ->push([
                    'choices' => [
                        [
                            'message' => [
                                'role' => 'assistant',
                                'content' => '',
                                'tool_calls' => [
                                    [
                                        'id' => 'call_1',
                                        'type' => 'function',
                                        'function' => [
                                            'name' => 'upsert_income_source',
                                            'arguments' => json_encode([
                                                'name' => 'Salary',
                                                'currency' => 'MYR',
                                                'amount_cents' => 5000_00,
                                                'cadence' => 'monthly',
                                            ]),
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ], 200)
                ->push([
                    'choices' => [
                        [
                            'message' => [
                                'role' => 'assistant',
                                'content' => 'Noted — I’ve saved Salary at MYR 5000/month.',
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

        $response = $this->actingAs($user)->postJson('/chat/messages', [
            'messages' => [
                ['role' => 'user', 'content' => 'My salary is RM5000.'],
            ],
        ])->assertOk();

        $response->assertJsonStructure([
            'content',
            'pending' => ['id', 'tool_calls'],
        ]);

        $pendingId = $response->json('pending.id');

        $this->assertSame(0, IncomeSource::where('user_id', $user->id)->count());

        $this->actingAs($user)->postJson("/chat/pending-tool-calls/{$pendingId}/confirm")
            ->assertOk()
            ->assertJsonFragment([
                'content' => 'Noted — I’ve saved Salary at MYR 5000/month.',
            ]);

        $this->assertDatabaseHas('income_sources', [
            'user_id' => $user->id,
            'name' => 'Salary',
            'currency' => 'MYR',
            'amount_cents' => 5000_00,
        ]);

        $this->assertSame(1, IncomeSource::where('user_id', $user->id)->count());
    }

    public function test_tool_calls_can_create_commitments_with_category_and_due_day(): void
    {
        Http::fake([
            'api.openai.com/v1/chat/completions' => Http::sequence()
                ->push([
                    'choices' => [
                        [
                            'message' => [
                                'role' => 'assistant',
                                'content' => '',
                                'tool_calls' => [
                                    [
                                        'id' => 'call_1',
                                        'type' => 'function',
                                        'function' => [
                                            'name' => 'upsert_commitment',
                                            'arguments' => json_encode([
                                                'name' => 'Rent',
                                                'category' => 'Housing',
                                                'currency' => 'MYR',
                                                'amount_cents' => 1500_00,
                                                'due_day' => 1,
                                                'cadence' => 'monthly',
                                            ]),
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ], 200)
                ->push([
                    'choices' => [
                        [
                            'message' => [
                                'role' => 'assistant',
                                'content' => 'Noted — I’ll save Rent under Housing, due day 1.',
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

        $response = $this->actingAs($user)->postJson('/chat/messages', [
            'messages' => [
                ['role' => 'user', 'content' => 'My rent is RM1500.'],
            ],
        ])->assertOk();

        $pendingId = $response->json('pending.id');

        $this->assertSame(0, Commitment::where('user_id', $user->id)->count());

        $this->actingAs($user)->postJson("/chat/pending-tool-calls/{$pendingId}/confirm")
            ->assertOk();

        $row = Commitment::query()->where('user_id', $user->id)->where('name', 'Rent')->firstOrFail();
        $this->assertSame('Housing', $row->category);
        $this->assertSame(1, $row->due_day);
        $this->assertSame(1500_00, $row->amount_cents);
    }

    public function test_upsert_commitment_rejects_invalid_due_day(): void
    {
        Http::fake([
            'api.openai.com/v1/chat/completions' => Http::sequence()
                ->push([
                    'choices' => [
                        [
                            'message' => [
                                'role' => 'assistant',
                                'content' => '',
                                'tool_calls' => [
                                    [
                                        'id' => 'call_1',
                                        'type' => 'function',
                                        'function' => [
                                            'name' => 'upsert_commitment',
                                            'arguments' => json_encode([
                                                'name' => 'Internet',
                                                'amount_cents' => 129_00,
                                                'due_day' => 0,
                                            ]),
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ], 200)
                ->push([
                    'choices' => [
                        [
                            'message' => [
                                'role' => 'assistant',
                                'content' => 'Got it — I couldn’t save that due day. What day of the month is it normally due (1–31)?',
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

        $response = $this->actingAs($user)->postJson('/chat/messages', [
            'messages' => [
                ['role' => 'user', 'content' => 'Internet RM129.'],
            ],
        ])->assertOk();

        $pendingId = $response->json('pending.id');

        $this->actingAs($user)->postJson("/chat/pending-tool-calls/{$pendingId}/confirm")
            ->assertOk();

        $this->assertSame(0, Commitment::where('user_id', $user->id)->count());
    }

    public function test_uses_groq_when_user_prefers_groq(): void
    {
        Http::fake([
            'https://api.groq.com/*' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'role' => 'assistant',
                            'content' => 'Hello from Groq.',
                        ],
                    ],
                ],
            ], 200),
        ]);

        config([
            'services.groq.api_key' => 'test-groq-key',
            'services.groq.base_url' => 'https://api.groq.com/openai/v1',
            'services.groq.model' => 'llama-test',
            'services.openai.api_key' => 'openai-should-not-be-called',
        ]);

        $user = User::factory()->create([
            'ai_chat_provider' => 'groq',
        ]);

        $response = $this->actingAs($user)->postJson('/chat/messages', [
            'messages' => [
                ['role' => 'user', 'content' => 'Hi'],
            ],
        ]);

        $response->assertOk();
        $response->assertJson(['content' => 'Hello from Groq.']);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'api.groq.com');
        });
    }

    public function test_returns_503_when_groq_api_key_missing(): void
    {
        Http::fake();

        config([
            'services.groq.api_key' => '',
            'services.groq.base_url' => 'https://api.groq.com/openai/v1',
        ]);

        $user = User::factory()->create([
            'ai_chat_provider' => 'groq',
        ]);

        $response = $this->actingAs($user)->postJson('/chat/messages', [
            'messages' => [
                ['role' => 'user', 'content' => 'Hi'],
            ],
        ]);

        $response->assertStatus(503);
        $response->assertJsonFragment(['message' => 'The assistant is not configured. Add GROQ_API_KEY to your environment.']);

        Http::assertNothingSent();
    }
}
