<?php

namespace App\Support;

use App\Models\User;

final class FinancialTablesContext
{
    public static function promptSection(User $user): string
    {
        $incomeLines = $user->incomeSources()
            ->where('is_active', true)
            ->orderByDesc('amount_cents')
            ->limit(10)
            ->get(['name', 'currency', 'amount_cents', 'cadence'])
            ->map(fn ($row) => sprintf('- %s: %s %0.2f (%s)', $row->name, $row->currency, $row->amount_cents / 100, $row->cadence))
            ->all();

        $commitmentLines = $user->commitments()
            ->where('is_active', true)
            ->orderByDesc('amount_cents')
            ->limit(15)
            ->get(['name', 'category', 'currency', 'amount_cents', 'cadence', 'due_day'])
            ->map(function ($row) {
                $when = $row->due_day ? "due day {$row->due_day}" : 'no due day';
                $cat = $row->category ? "{$row->category}" : 'Uncategorized';

                return sprintf('- %s (%s): %s %0.2f (%s, %s)', $row->name, $cat, $row->currency, $row->amount_cents / 100, $row->cadence, $when);
            })
            ->all();

        $debtLines = $user->debts()
            ->where('is_active', true)
            ->orderByDesc('balance_cents')
            ->limit(15)
            ->get(['type', 'name', 'currency', 'balance_cents', 'minimum_payment_cents', 'apr_bps', 'due_day', 'credit_limit_cents'])
            ->map(function ($row) {
                $apr = is_null($row->apr_bps) ? 'APR unknown' : sprintf('APR %0.2f%%', $row->apr_bps / 100);
                $min = is_null($row->minimum_payment_cents) ? 'min unknown' : sprintf('min %s %0.2f', $row->currency, $row->minimum_payment_cents / 100);
                $due = $row->due_day ? "due day {$row->due_day}" : 'no due day';
                $limit = is_null($row->credit_limit_cents) ? null : sprintf('limit %s %0.2f', $row->currency, $row->credit_limit_cents / 100);
                $extras = array_values(array_filter([$apr, $min, $due, $limit]));

                return sprintf('- %s (%s): %s %0.2f — %s', $row->name, $row->type, $row->currency, $row->balance_cents / 100, implode(', ', $extras));
            })
            ->all();

        if ($incomeLines === [] && $commitmentLines === [] && $debtLines === []) {
            return <<<'TEXT'

## Structured financial records (app database)
No income sources, commitments, or debts have been saved yet.

TEXT;
        }

        $out = "\n\n## Structured financial records (app database)\n";

        if ($incomeLines !== []) {
            $out .= "\n### Income sources\n".implode("\n", $incomeLines)."\n";
        }

        if ($commitmentLines !== []) {
            $out .= "\n### Monthly commitments\n".implode("\n", $commitmentLines)."\n";
        }

        if ($debtLines !== []) {
            $out .= "\n### Debts / credit\n".implode("\n", $debtLines)."\n";
        }

        return $out;
    }
}
