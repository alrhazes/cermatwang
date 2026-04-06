<?php

namespace Tests\Feature\Settings;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssistantSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_update_assistant_provider_and_model(): void
    {
        $user = User::factory()->create([
            'ai_chat_provider' => null,
            'ai_chat_model' => null,
        ]);

        $this->actingAs($user)
            ->patch(route('assistant.update'), [
                'ai_chat_provider' => 'groq',
                'ai_chat_model' => 'llama-3.3-70b-versatile',
            ])
            ->assertRedirect(route('assistant.edit'));

        $user->refresh();
        $this->assertSame('groq', $user->ai_chat_provider);
        $this->assertSame('llama-3.3-70b-versatile', $user->ai_chat_model);
    }

    public function test_user_can_clear_optional_model(): void
    {
        $user = User::factory()->create([
            'ai_chat_provider' => 'openai',
            'ai_chat_model' => 'gpt-4o-mini',
        ]);

        $this->actingAs($user)
            ->patch(route('assistant.update'), [
                'ai_chat_provider' => 'openai',
                'ai_chat_model' => null,
            ])
            ->assertRedirect(route('assistant.edit'));

        $user->refresh();
        $this->assertNull($user->ai_chat_model);
    }
}
