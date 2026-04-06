<?php

namespace App\Services;

use App\Exceptions\OpenAiCompletionFailedException;
use App\Support\ChatAssistantContentSanitizer;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class OpenAiChatService
{
    /**
     * Send chat messages to OpenAI and return the assistant text.
     *
     * @param  array<int, array{role: string, content: string}>  $messages
     *
     * @throws OpenAiCompletionFailedException
     */
    public function complete(array $messages): string
    {
        $result = $this->respond($messages);

        return $result['content'];
    }

    /**
     * Send chat messages to OpenAI and return message content and tool calls.
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
        $apiKey = config('services.openai.api_key');
        if (! is_string($apiKey) || $apiKey === '') {
            throw new RuntimeException('OpenAI API key is not configured.');
        }

        $baseUrl = rtrim((string) config('services.openai.base_url', 'https://api.openai.com/v1'), '/');
        $model = (string) config('services.openai.model', 'gpt-4o');
        $url = $baseUrl.'/chat/completions';

        $payload = [
            'model' => $model,
            'messages' => $messages,
            'max_tokens' => (int) config('services.openai.max_tokens', 1024),
            'temperature' => (float) config('services.openai.temperature', 0.7),
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
            $openAiMessage = $response->json('error.message');
            $openAiCode = $response->json('error.code');

            Log::warning('OpenAI chat completions request failed', [
                'status' => $response->status(),
                'error_type' => $response->json('error.type'),
                'error_code' => is_string($openAiCode) ? $openAiCode : null,
            ]);

            $userMessage = is_string($openAiMessage) && $openAiMessage !== ''
                ? $openAiMessage
                : 'The assistant could not complete that request. Try again in a moment.';

            throw new OpenAiCompletionFailedException($userMessage, $response->status());
        }

        $content = $response->json('choices.0.message.content');
        $toolCalls = $response->json('choices.0.message.tool_calls');

        return [
            'content' => ChatAssistantContentSanitizer::stripInlineToolMarkup(is_string($content) ? trim($content) : ''),
            'tool_calls' => is_array($toolCalls) ? $toolCalls : [],
        ];
    }
}
