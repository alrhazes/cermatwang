<?php

namespace App\Support;

use App\Models\Commitment;
use App\Models\ExpenseEntry;
use App\Models\IncomeSource;
use App\Models\MonthlyBudgetAllocation;
use App\Models\User;
use Illuminate\Support\Collection;

final class ChatBudgetOverview
{
    /**
     * @return array{
     *   year_month: string,
     *   label: string,
     *   rows: list<array{
     *     category: string,
     *     budget_cents: int,
     *     fixed_commitments_cents: int|null,
     *     previous_budget_cents: int|null,
     *     spent_cents: int,
     *     remaining_cents: int,
     *     percent_used: float|null,
     *     currency: string,
     *     notes: string|null
     *   }>,
     *   spend_outside_budget_slots: list<array{category: string, spent_cents: int, currency: string}>,
     *   totals: array{
     *     budget_cents: int,
     *     fixed_commitments_cents: int,
     *     monthly_income_cents: int,
     *     health_percent: float|null,
     *     spent_cents: int,
     *     remaining_vs_budget_cents: int,
     *     spent_percent_of_planned: float|null,
     *     today_spent_cents: int
     *   },
     *   today_label: string,
     *   canned_prompts: list<array{label: string, text: string}>
     * }
     */
    public static function forInertia(User $user): array
    {
        $tz = config('app.timezone', 'UTC');
        $now = now($tz);
        $yearMonth = $now->format('Y-m');
        $prevYm = $now->copy()->subMonth()->format('Y-m');

        $fixedByCategory = $user->commitments()
            ->where('is_active', true)
            ->get(['category', 'amount_cents'])
            ->groupBy(fn (Commitment $c) => mb_strtolower(trim($c->category ?? 'Uncategorized')))
            ->map(fn (Collection $rows) => (int) $rows->sum('amount_cents'));

        $prevByCategory = MonthlyBudgetAllocation::query()
            ->where('user_id', $user->id)
            ->where('year_month', $prevYm)
            ->get()
            ->keyBy(fn (MonthlyBudgetAllocation $m) => mb_strtolower(trim($m->category)));

        $allocations = MonthlyBudgetAllocation::query()
            ->where('user_id', $user->id)
            ->where('year_month', $yearMonth)
            ->orderBy('category')
            ->get();

        $monthlyIncomeCents = (int) IncomeSource::query()
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->where('cadence', 'monthly')
            ->sum('amount_cents');

        if ($monthlyIncomeCents === 0) {
            $monthlyIncomeCents = (int) IncomeSource::query()
                ->where('user_id', $user->id)
                ->where('is_active', true)
                ->sum('amount_cents');
        }

        $expenses = ExpenseEntry::query()
            ->where('user_id', $user->id)
            ->where('year_month', $yearMonth)
            ->get();

        $todayStart = $now->copy()->startOfDay();
        $todayEnd = $now->copy()->endOfDay();
        $todaySpentCents = (int) ExpenseEntry::query()
            ->where('user_id', $user->id)
            ->whereBetween('spent_at', [$todayStart, $todayEnd])
            ->sum('amount_cents');

        $expenseGroups = $expenses->groupBy(fn (ExpenseEntry $e) => mb_strtolower(trim($e->category)));
        $spentByKey = $expenseGroups->map(fn (Collection $g) => (int) $g->sum('amount_cents'));
        $totalSpentCents = (int) $expenses->sum('amount_cents');

        $allocationKeys = [];
        foreach ($allocations as $a) {
            $allocationKeys[mb_strtolower(trim($a->category))] = true;
        }

        $spendOutside = [];
        foreach ($spentByKey as $key => $cents) {
            if (! isset($allocationKeys[$key])) {
                $first = $expenseGroups->get($key)?->first();
                if ($first instanceof ExpenseEntry) {
                    $spendOutside[] = [
                        'category' => $first->category,
                        'spent_cents' => $cents,
                        'currency' => $first->currency,
                    ];
                }
            }
        }

        $rows = [];
        $totalBudget = 0;

        $totalFixedAll = (int) $user->commitments()->where('is_active', true)->sum('amount_cents');

        foreach ($allocations as $row) {
            $key = mb_strtolower(trim($row->category));
            $fixed = (int) ($fixedByCategory->get($key) ?? 0);
            $prev = $prevByCategory->get($key);
            $prevCents = $prev instanceof MonthlyBudgetAllocation ? $prev->amount_cents : null;

            $spent = (int) ($spentByKey->get($key) ?? 0);
            $remaining = $row->amount_cents - $spent;
            $pctUsed = $row->amount_cents > 0
                ? round(100 * $spent / $row->amount_cents, 1)
                : null;

            $rows[] = [
                'category' => $row->category,
                'budget_cents' => $row->amount_cents,
                'fixed_commitments_cents' => $fixed > 0 ? $fixed : null,
                'previous_budget_cents' => $prevCents,
                'spent_cents' => $spent,
                'remaining_cents' => $remaining,
                'percent_used' => $pctUsed,
                'currency' => $row->currency,
                'notes' => $row->notes,
            ];
            $totalBudget += $row->amount_cents;
        }

        $health = null;
        if ($monthlyIncomeCents > 0 && $totalBudget > 0) {
            $health = round(100 * $totalBudget / $monthlyIncomeCents, 1);
        }

        $remainingVsBudget = $totalBudget - $totalSpentCents;
        $spentPctPlanned = $totalBudget > 0
            ? round(100 * $totalSpentCents / $totalBudget, 1)
            : null;

        return [
            'year_month' => $yearMonth,
            'label' => $now->translatedFormat('F Y'),
            'today_label' => $now->translatedFormat('F j, Y'),
            'rows' => $rows,
            'spend_outside_budget_slots' => $spendOutside,
            'totals' => [
                'budget_cents' => $totalBudget,
                'fixed_commitments_cents' => $totalFixedAll,
                'monthly_income_cents' => $monthlyIncomeCents,
                'health_percent' => $health,
                'spent_cents' => $totalSpentCents,
                'remaining_vs_budget_cents' => $remainingVsBudget,
                'spent_percent_of_planned' => $spentPctPlanned,
                'today_spent_cents' => $todaySpentCents,
            ],
            'canned_prompts' => [
                ['label' => 'Readjust budget', 'text' => 'Readjust my budget for this month based on my income and commitments.'],
                ['label' => 'Tight month', 'text' => 'This is a tight month — tighten discretionary categories and suggest a revised budget.'],
                ['label' => 'Extra income', 'text' => 'I have extra income this month — suggest a revised budget split.'],
                ['label' => 'Festive / seasonal', 'text' => 'It is a festive or seasonal stretch — adjust my budget for this month accordingly.'],
            ],
        ];
    }

    /**
     * Compact text for the LLM system prompt (same shape as {@see self::forInertia()}).
     *
     * @param  array<string, mixed>  $overview
     */
    public static function toPromptText(array $overview): string
    {
        $label = is_string($overview['label'] ?? null) ? $overview['label'] : '';
        $ym = is_string($overview['year_month'] ?? null) ? $overview['year_month'] : '';
        $todayLabel = is_string($overview['today_label'] ?? null) ? $overview['today_label'] : '';
        $totals = is_array($overview['totals'] ?? null) ? $overview['totals'] : [];
        $rows = is_array($overview['rows'] ?? null) ? $overview['rows'] : [];
        $outside = is_array($overview['spend_outside_budget_slots'] ?? null) ? $overview['spend_outside_budget_slots'] : [];

        $budgetCents = is_int($totals['budget_cents'] ?? null) ? $totals['budget_cents'] : 0;
        $fixedCents = is_int($totals['fixed_commitments_cents'] ?? null) ? $totals['fixed_commitments_cents'] : 0;
        $incomeCents = is_int($totals['monthly_income_cents'] ?? null) ? $totals['monthly_income_cents'] : 0;
        $spentCents = is_int($totals['spent_cents'] ?? null) ? $totals['spent_cents'] : 0;
        $remainingVs = is_int($totals['remaining_vs_budget_cents'] ?? null) ? $totals['remaining_vs_budget_cents'] : 0;
        $spentPctPlanned = $totals['spent_percent_of_planned'] ?? null;
        $spentPctStr = is_float($spentPctPlanned) || is_int($spentPctPlanned)
            ? sprintf('%s%% of planned slots', $spentPctPlanned)
            : 'n/a';

        $health = $totals['health_percent'] ?? null;
        $healthStr = is_float($health) || is_int($health)
            ? sprintf('%s%% of monthly income', $health)
            : 'n/a (set income or add budget slots)';

        $currency = 'MYR';
        if ($rows !== [] && is_array($rows[0]) && isset($rows[0]['currency']) && is_string($rows[0]['currency']) && $rows[0]['currency'] !== '') {
            $currency = $rows[0]['currency'];
        }

        $lines = [];
        $lines[] = sprintf(
            'Summary for %s (%s): planned total %s %0.2f; fixed commitments (all active) %s %0.2f; reference monthly income %s %0.2f; planned vs income %s.',
            $label,
            $ym,
            $currency,
            $budgetCents / 100,
            $currency,
            $fixedCents / 100,
            $currency,
            $incomeCents / 100,
            $healthStr,
        );
        $lines[] = sprintf(
            'Logged spend this month: %s %0.2f; vs planned slots %s %0.2f remaining; %s.',
            $currency,
            $spentCents / 100,
            $currency,
            $remainingVs / 100,
            $spentPctStr,
        );

        $todaySpentCents = is_int($totals['today_spent_cents'] ?? null) ? $totals['today_spent_cents'] : 0;
        $lines[] = sprintf(
            'Logged spend today (%s, saved expenses only): %s %0.2f.',
            $todayLabel !== '' ? $todayLabel : 'today',
            $currency,
            $todaySpentCents / 100,
        );

        if ($rows === []) {
            $lines[] = 'No per-category budget slots saved for this month yet.';
        } else {
            $lines[] = 'Per category (planned = discretionary cap; fixed = matching commitment category total; prev = last month’s planned slot; spent = logged expenses this month):';

            foreach ($rows as $row) {
                if (! is_array($row)) {
                    continue;
                }

                $cat = is_string($row['category'] ?? null) ? $row['category'] : 'Category';
                $cur = is_string($row['currency'] ?? null) && $row['currency'] !== '' ? $row['currency'] : $currency;
                $plan = is_int($row['budget_cents'] ?? null) ? $row['budget_cents'] : 0;
                $fixed = $row['fixed_commitments_cents'] ?? null;
                $prev = $row['previous_budget_cents'] ?? null;
                $spent = is_int($row['spent_cents'] ?? null) ? $row['spent_cents'] : 0;
                $remain = is_int($row['remaining_cents'] ?? null) ? $row['remaining_cents'] : 0;
                $notes = $row['notes'] ?? null;

                $bits = [sprintf('planned %s %0.2f', $cur, $plan / 100)];
                if (is_int($fixed) && $fixed > 0) {
                    $bits[] = sprintf('fixed %s %0.2f', $cur, $fixed / 100);
                }
                if (is_int($prev)) {
                    $bits[] = sprintf('prev month planned %s %0.2f', $cur, $prev / 100);
                }
                $bits[] = sprintf('spent %s %0.2f', $cur, $spent / 100);
                $bits[] = sprintf('remaining %s %0.2f', $cur, $remain / 100);
                if (is_string($notes) && trim($notes) !== '') {
                    $bits[] = 'notes: '.trim($notes);
                }

                $lines[] = '- '.$cat.': '.implode('; ', $bits);
            }
        }

        if ($outside !== []) {
            $lines[] = 'Spend logged under categories with no budget slot for this month:';
            foreach ($outside as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $c = is_string($row['category'] ?? null) ? $row['category'] : '?';
                $cur = is_string($row['currency'] ?? null) ? $row['currency'] : $currency;
                $s = is_int($row['spent_cents'] ?? null) ? $row['spent_cents'] : 0;
                $lines[] = sprintf('- %s: %s %0.2f', $c, $cur, $s / 100);
            }
        }

        return "\n### Monthly budget\n".implode("\n", $lines)."\n";
    }
}
