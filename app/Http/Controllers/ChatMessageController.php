<?php

namespace App\Http\Controllers;

use App\Exceptions\OpenAiCompletionFailedException;
use App\Http\Requests\SendChatMessageRequest;
use App\Services\ChatToolRunner;
use App\Services\OpenAiChatService;
use App\Support\AdvisorPersona;
use App\Support\FinancialOnboarding;
use App\Support\FinancialProfileContext;
use App\Support\FinancialTablesContext;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class ChatMessageController extends Controller
{
    /**
     * Return the next assistant message for the given conversation history.
     */
    public function __invoke(SendChatMessageRequest $request, OpenAiChatService $openAiChat, ChatToolRunner $toolRunner): JsonResponse
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
        $basePersona = is_string($customPrompt) && $customPrompt !== ''
            ? $customPrompt
            : AdvisorPersona::defaultBasePersona();

        $needsOnboarding = $request->user()->needsFinancialOnboarding();

        $systemContent = FinancialOnboarding::userStateInstructions($needsOnboarding)
            .FinancialProfileContext::promptSection($request->user()->financialProfilePayload())
            .FinancialTablesContext::promptSection($request->user())
            .$basePersona;

        if ($needsOnboarding) {
            $systemContent .= FinancialOnboarding::systemPromptAddon();
        }

        $messages = array_merge([
            ['role' => 'system', 'content' => $systemContent],
        ], $history);

        try {
            $tools = $this->toolDefinitions();
            $result = $openAiChat->respond($messages, $tools);

            $content = $result['content'];

            if ($result['tool_calls'] !== []) {
                $pendingId = (string) str()->uuid();
                Cache::put(
                    "chat:pending:{$pendingId}",
                    [
                        'user_id' => $request->user()->id,
                        'base_messages' => $messages,
                        'assistant_content' => $content,
                        'tool_calls' => $result['tool_calls'],
                    ],
                    now()->addMinutes(15)
                );

                return response()->json([
                    'content' => $content !== '' ? $content : 'I can save a few details to your profile. Please confirm before I apply them.',
                    'pending' => [
                        'id' => $pendingId,
                        'tool_calls' => $result['tool_calls'],
                    ],
                ]);
            }
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

        return response()->json(['content' => $content]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function toolDefinitions(): array
    {
        return [
            [
                'type' => 'function',
                'function' => [
                    'name' => 'upsert_income_source',
                    'description' => 'Create or update a monthly income source for the user.',
                    'parameters' => [
                        'type' => 'object',
                        'additionalProperties' => false,
                        'properties' => [
                            'name' => ['type' => 'string'],
                            'currency' => ['type' => 'string', 'description' => '3-letter currency, e.g. MYR'],
                            'amount_cents' => ['type' => 'integer', 'description' => 'Amount in cents'],
                            'cadence' => ['type' => 'string', 'description' => 'monthly|weekly|yearly|irregular'],
                            'is_active' => ['type' => 'boolean'],
                        ],
                        'required' => ['name', 'amount_cents'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'upsert_commitment',
                    'description' => 'Create or update a recurring monthly commitment (fixed bill).',
                    'parameters' => [
                        'type' => 'object',
                        'additionalProperties' => false,
                        'properties' => [
                            'name' => ['type' => 'string'],
                            'category' => ['type' => 'string'],
                            'currency' => ['type' => 'string'],
                            'amount_cents' => ['type' => 'integer'],
                            'due_day' => ['type' => 'integer', 'description' => '1-28 if known'],
                            'cadence' => ['type' => 'string'],
                            'is_active' => ['type' => 'boolean'],
                        ],
                        'required' => ['name', 'amount_cents'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'upsert_debt',
                    'description' => 'Create or update a debt/credit entry (credit card, loan, BNPL).',
                    'parameters' => [
                        'type' => 'object',
                        'additionalProperties' => false,
                        'properties' => [
                            'type' => ['type' => 'string', 'description' => 'credit_card|personal_loan|car_loan|housing_loan|bnpl|other'],
                            'name' => ['type' => 'string'],
                            'currency' => ['type' => 'string'],
                            'balance_cents' => ['type' => 'integer'],
                            'minimum_payment_cents' => ['type' => 'integer'],
                            'apr_bps' => ['type' => 'integer', 'description' => 'APR in basis points, e.g. 1899 for 18.99%'],
                            'due_day' => ['type' => 'integer'],
                            'credit_limit_cents' => ['type' => 'integer'],
                            'is_active' => ['type' => 'boolean'],
                        ],
                        'required' => ['name'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'delete_income_source',
                    'description' => 'Delete an income source by id or name.',
                    'parameters' => [
                        'type' => 'object',
                        'additionalProperties' => false,
                        'properties' => [
                            'id' => ['type' => 'integer'],
                            'name' => ['type' => 'string'],
                        ],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'delete_commitment',
                    'description' => 'Delete a commitment by id or name.',
                    'parameters' => [
                        'type' => 'object',
                        'additionalProperties' => false,
                        'properties' => [
                            'id' => ['type' => 'integer'],
                            'name' => ['type' => 'string'],
                        ],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'delete_debt',
                    'description' => 'Delete a debt by id, or by name (+ optional type).',
                    'parameters' => [
                        'type' => 'object',
                        'additionalProperties' => false,
                        'properties' => [
                            'id' => ['type' => 'integer'],
                            'name' => ['type' => 'string'],
                            'type' => ['type' => 'string'],
                        ],
                    ],
                ],
            ],
        ];
    }
}
