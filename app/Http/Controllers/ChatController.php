<?php

namespace App\Http\Controllers;

use App\Models\Commitment;
use App\Support\ChatBudgetOverview;
use App\Support\FinancialOnboarding;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ChatController extends Controller
{
    public function show(Request $request): Response
    {
        $user = $request->user();
        $needsOnboarding = $user->needsFinancialOnboarding();

        $welcome = $needsOnboarding
            ? FinancialOnboarding::welcomeMessage()
            : 'Welcome back. I’m working from what you’ve already told me in this chat and anything saved on your profile—so we won’t start from zero. What’s most useful right now: tightening this month’s cashflow, a debt or card you want a plan for, or a big expense coming up?';

        // Show for all users who have commitments missing category or due day (not only first-time onboarding).
        $missing = Commitment::query()
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('category')
                    ->orWhereNull('due_day');
            })
            ->orderByDesc('amount_cents')
            ->limit(10)
            ->get(['name', 'category', 'due_day']);

        if ($missing->isNotEmpty()) {
            $lines = [];
            foreach ($missing as $row) {
                $suggested = $this->suggestCommitmentCategory($row->name);
                $cat = $row->category ?? $suggested;
                $due = $row->due_day ? "due {$row->due_day}" : 'due day unknown';
                $lines[] = "- {$row->name}: {$cat} ({$due})";
            }

            $welcome .= "\n\nI can help tidy your saved bills so budgeting is easier. Here are quick suggested categories (and we can add due days too):\n"
                .implode("\n", $lines)
                ."\n\nIf you want, tell me any corrections (or just say “looks good”) and I’ll queue the saves for you to confirm.";
        }

        return Inertia::render('chat', [
            'needsFinancialOnboarding' => $needsOnboarding,
            'chatWelcome' => $welcome,
            'budgetOverview' => ChatBudgetOverview::forInertia($user),
        ]);
    }

    private function suggestCommitmentCategory(string $name): string
    {
        $haystack = mb_strtolower($name);

        if (str_contains($haystack, 'kad kredit') || str_contains($haystack, 'credit card') || str_contains($haystack, 'amex') || str_contains($haystack, 'visa')) {
            return 'Credit Cards';
        }

        if (str_contains($haystack, 'loan') || str_contains($haystack, 'pinjaman')) {
            if (str_contains($haystack, 'kereta') || str_contains($haystack, 'car')) {
                return 'Transport';
            }

            if (str_contains($haystack, 'housing') || str_contains($haystack, 'mortgage') || str_contains($haystack, 'rumah')) {
                return 'Housing';
            }

            return 'Loans';
        }

        if (str_contains($haystack, 'housing') || str_contains($haystack, 'mortgage') || str_contains($haystack, 'rent') || str_contains($haystack, 'sewa')) {
            return 'Housing';
        }

        if (str_contains($haystack, 'cimb') || str_contains($haystack, 'maybank') || str_contains($haystack, 'rhb')) {
            return 'Loans';
        }

        return 'Other';
    }
}
