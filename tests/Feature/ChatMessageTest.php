<?php

namespace Tests\Feature;

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
}
