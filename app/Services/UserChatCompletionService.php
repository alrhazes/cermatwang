<?php

namespace App\Services;

use App\Exceptions\AssistantNotConfiguredException;
use App\Models\User;
use Illuminate\Support\Facades\Config;

class UserChatCompletionService
{
    public function __construct(
        private readonly OpenAiChatService $openAiChat,
        private readonly GroqChatService $groqChat,
    ) {}

    /**
     * @param  array<int, array<string, mixed>>  $messages
     * @param  array<int, array<string, mixed>>  $tools
     * @return array{content: string, tool_calls: array<int, array<string, mixed>>}
     */
    public function respond(User $user, array $messages, array $tools = []): array
    {
        $provider = $user->ai_chat_provider;

        if ($provider === 'groq') {
            $key = config('services.groq.api_key');
            if (! is_string($key) || $key === '') {
                throw new AssistantNotConfiguredException('The assistant is not configured. Add GROQ_API_KEY to your environment.');
            }

            return $this->withGroqModelOverride($user->ai_chat_model, fn () => $this->groqChat->respond($messages, $tools));
        }

        $key = config('services.openai.api_key');
        if (! is_string($key) || $key === '') {
            throw new AssistantNotConfiguredException('The assistant is not configured. Add OPENAI_API_KEY to your environment.');
        }

        return $this->withOpenAiModelOverride($user->ai_chat_model, fn () => $this->openAiChat->respond($messages, $tools));
    }

    /**
     * @param  callable(): array{content: string, tool_calls: array<int, array<string, mixed>>}  $callback
     */
    private function withOpenAiModelOverride(?string $model, callable $callback): array
    {
        if ($model === null || trim($model) === '') {
            return $callback();
        }

        $previous = config('services.openai.model');
        Config::set('services.openai.model', trim($model));

        try {
            return $callback();
        } finally {
            Config::set('services.openai.model', $previous);
        }
    }

    /**
     * @param  callable(): array{content: string, tool_calls: array<int, array<string, mixed>>}  $callback
     */
    private function withGroqModelOverride(?string $model, callable $callback): array
    {
        if ($model === null || trim($model) === '') {
            return $callback();
        }

        $previous = config('services.groq.model');
        Config::set('services.groq.model', trim($model));

        try {
            return $callback();
        } finally {
            Config::set('services.groq.model', $previous);
        }
    }
}
