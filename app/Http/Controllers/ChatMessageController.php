<?php

namespace App\Http\Controllers;

use App\Exceptions\OpenAiCompletionFailedException;
use App\Http\Requests\SendChatMessageRequest;
use App\Services\OpenAiChatService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class ChatMessageController extends Controller
{
    /**
     * Return the next assistant message for the given conversation history.
     */
    public function __invoke(SendChatMessageRequest $request, OpenAiChatService $openAiChat): JsonResponse
    {
        $validated = $request->validated();

        $history = [];
        foreach ($validated['messages'] as $row) {
            $history[] = [
                'role' => $row['role'],
                'content' => $row['content'],
            ];
        }

        $customPrompt = config('services.openai.system_prompt');
        $systemContent = is_string($customPrompt) && $customPrompt !== ''
            ? $customPrompt
            : 'You are a supportive personal finance assistant for users in Malaysia. Help with budgeting, debt payoff ideas, and spending tradeoffs. Use RM when discussing money unless the user uses another currency. You do not have access to their bank accounts or real balances unless the user pastes them in chat—say so if you are guessing. Be concise unless they ask for detail.';

        $messages = array_merge([
            ['role' => 'system', 'content' => $systemContent],
        ], $history);

        try {
            $content = $openAiChat->complete($messages);
        } catch (RuntimeException) {
            return response()->json([
                'message' => 'The assistant is not configured. Add OPENAI_API_KEY to your environment.',
            ], 503);
        } catch (ConnectionException) {
            return response()->json([
                'message' => 'Could not reach OpenAI. Check your connection and try again.',
            ], 503);
        } catch (OpenAiCompletionFailedException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 502);
        } catch (Throwable $e) {
            Log::error('OpenAI chat unexpected error', [
                'exception' => $e::class,
            ]);

            return response()->json([
                'message' => 'Something went wrong. Please try again.',
            ], 500);
        }

        if ($content === '') {
            return response()->json([
                'message' => 'The assistant returned an empty response. Try rephrasing your message.',
            ], 502);
        }

        return response()->json([
            'content' => $content,
        ]);
    }
}
