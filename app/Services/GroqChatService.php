<?php

namespace App\Services;

use App\Exceptions\OpenAiCompletionFailedException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class GroqChatService
{
    /**
     * Send chat messages to Groq (OpenAI-compatible API) and return message content and tool calls.
     *
     * @param  array<int, array<string, mixed>>  $messages
     * @param  array<int, array<string, mixed>>  $tools
     * @return array{content: string, tool_calls: array<int, array<string, mixed>>}
     *
     * @throws OpenAiCompletionFailedException
     * @throws ConnectionException
     */
    public function respond(array $messages, array $tools = []): array
    {
        $apiKey = config('services.groq.api_key');
        if (! is_string($apiKey) || $apiKey === '') {
            throw new RuntimeException('Groq API key is not configured.');
        }

        $baseUrl = rtrim((string) config('services.groq.base_url', 'https://api.groq.com/openai/v1'), '/');
        $model = (string) config('services.groq.model', 'llama-3.3-70b-versatile');
        $url = $baseUrl.'/chat/completions';

        $payload = [
            'model' => $model,
            'messages' => $messages,
            'max_tokens' => (int) config('services.groq.max_tokens', 1024),
            'temperature' => (float) config('services.groq.temperature', 0.7),
        ];

        if ($tools !== []) {
            $payload['tools'] = $tools;
            $payload['tool_choice'] = 'auto';
        }

        $response = Http::withToken($apiKey)
            ->acceptJson()
            ->asJson()
            ->timeout(90)
            ->post($url, $payload);

        if ($response->failed()) {
            $errorMessage = $response->json('error.message');
            $errorCode = $response->json('error.code');

            Log::warning('Groq chat completions request failed', [
                'status' => $response->status(),
                'error_type' => $response->json('error.type'),
                'error_code' => is_string($errorCode) ? $errorCode : null,
            ]);

            $userMessage = is_string($errorMessage) && $errorMessage !== ''
                ? $errorMessage
                : 'The assistant could not complete that request. Try again in a moment.';

            throw new OpenAiCompletionFailedException($userMessage, $response->status());
        }

        $content = $response->json('choices.0.message.content');
        $toolCalls = $response->json('choices.0.message.tool_calls');

        return [
            'content' => is_string($content) ? trim($content) : '',
            'tool_calls' => is_array($toolCalls) ? $toolCalls : [],
        ];
    }
}
