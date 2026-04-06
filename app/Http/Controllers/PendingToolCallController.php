<?php

namespace App\Http\Controllers;

use App\Exceptions\AssistantNotConfiguredException;
use App\Exceptions\OpenAiCompletionFailedException;
use App\Services\ChatToolRunner;
use App\Services\UserChatCompletionService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

class PendingToolCallController extends Controller
{
    public function confirm(
        Request $request,
        string $pendingId,
        ChatToolRunner $toolRunner,
        UserChatCompletionService $chatCompletion,
    ): JsonResponse {
        $cacheKey = "chat:pending:{$pendingId}";
        $pending = Cache::get($cacheKey);

        if (! is_array($pending) || ($pending['user_id'] ?? null) !== $request->user()?->id) {
            return response()->json(['message' => 'Pending change request not found or expired.'], 404);
        }

        Cache::forget($cacheKey);

        $baseMessages = $pending['base_messages'] ?? [];
        $assistantContent = $pending['assistant_content'] ?? '';
        $toolCalls = $pending['tool_calls'] ?? [];

        if (! is_array($baseMessages) || ! is_array($toolCalls)) {
            return response()->json(['message' => 'Pending change request is invalid.'], 422);
        }

        $toolMessages = [];
        foreach ($toolCalls as $call) {
            $toolCallId = data_get($call, 'id');
            if (! is_string($toolCallId) || $toolCallId === '') {
                continue;
            }

            try {
                $toolResult = $toolRunner->run($request->user(), $call);
            } catch (InvalidArgumentException $e) {
                $toolResult = ['ok' => false, 'error' => $e->getMessage()];
            }

            $toolMessages[] = [
                'role' => 'tool',
                'tool_call_id' => $toolCallId,
                'content' => json_encode($toolResult, JSON_UNESCAPED_UNICODE),
            ];
        }

        try {
            $followUp = $chatCompletion->respond($request->user(), array_merge($baseMessages, [
                [
                    'role' => 'assistant',
                    'content' => is_string($assistantContent) ? $assistantContent : '',
                    'tool_calls' => $toolCalls,
                ],
                ...$toolMessages,
            ]));
        } catch (AssistantNotConfiguredException $e) {
            return response()->json(['message' => $e->getMessage()], 503);
        } catch (RuntimeException) {
            return response()->json(['message' => 'The assistant is not configured.'], 503);
        } catch (ConnectionException) {
            return response()->json(['message' => 'Could not reach the assistant provider.'], 503);
        } catch (OpenAiCompletionFailedException $e) {
            return response()->json(['message' => $e->getMessage()], 502);
        } catch (Throwable) {
            return response()->json(['message' => 'Something went wrong.'], 500);
        }

        $content = $followUp['content'] ?? '';

        return response()->json([
            'content' => is_string($content) ? $content : '',
        ]);
    }

    public function cancel(Request $request, string $pendingId): JsonResponse
    {
        $cacheKey = "chat:pending:{$pendingId}";
        $pending = Cache::get($cacheKey);

        if (! is_array($pending) || ($pending['user_id'] ?? null) !== $request->user()?->id) {
            return response()->json(['message' => 'Pending change request not found or expired.'], 404);
        }

        Cache::forget($cacheKey);

        return response()->json(['ok' => true]);
    }
}
