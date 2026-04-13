<?php

namespace App\Services;

use App\Models\Commitment;
use App\Models\Debt;
use App\Models\ExpenseEntry;
use App\Models\IncomeSource;
use App\Models\MonthlyBudgetAllocation;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

class ChatToolRunner
{
    /**
     * Tool names that persist changes to the database (these require user confirmation before running).
     *
     * @return list<string>
     */
    public static function databaseMutationToolNames(): array
    {
        return [
            'upsert_income_source',
            'upsert_commitment',
            'upsert_debt',
            'delete_income_source',
            'delete_commitment',
            'delete_debt',
            'upsert_monthly_budget_allocation',
            'delete_monthly_budget_allocation',
            'log_expense',
            'delete_expense',
        ];
    }

    /**
     * @param  array<int, mixed>  $toolCalls
     * @return array<int, array<string, mixed>>
     */
    public static function filterDatabaseMutationToolCalls(array $toolCalls): array
    {
        $allowed = array_flip(self::databaseMutationToolNames());
        $out = [];

        foreach ($toolCalls as $call) {
            if (! is_array($call)) {
                continue;
            }

            $name = data_get($call, 'function.name');
            if (is_string($name) && isset($allowed[$name])) {
                $out[] = $call;
            }
        }

        return $out;
    }

    /**
     * Execute an OpenAI tool call against the current user.
     *
     * @param  array<string, mixed>  $toolCall
     * @return array<string, mixed>
     */
    public function run(User $user, array $toolCall): array
    {
        $name = data_get($toolCall, 'function.name');
        $rawArguments = data_get($toolCall, 'function.arguments');

        if (! is_string($name) || $name === '') {
            throw new InvalidArgumentException('Tool call is missing function name.');
        }

        $args = [];
        if (is_string($rawArguments) && $rawArguments !== '') {
            $decoded = json_decode($rawArguments, true);
            if (! is_array($decoded)) {
                throw new InvalidArgumentException('Tool call arguments must be valid JSON.');
            }
            $args = $decoded;
        }

        return match ($name) {
            'upsert_income_source' => $this->upsertIncomeSource($user, $args),
            'upsert_commitment' => $this->upsertCommitment($user, $args),
            'upsert_debt' => $this->upsertDebt($user, $args),
            'delete_income_source' => $this->deleteIncomeSource($user, $args),
            'delete_commitment' => $this->deleteCommitment($user, $args),
            'delete_debt' => $this->deleteDebt($user, $args),
            'upsert_monthly_budget_allocation' => $this->upsertMonthlyBudgetAllocation($user, $args),
            'delete_monthly_budget_allocation' => $this->deleteMonthlyBudgetAllocation($user, $args),
            'log_expense' => $this->logExpense($user, $args),
            'delete_expense' => $this->deleteExpense($user, $args),
            default => throw new InvalidArgumentException("Unknown tool: {$name}"),
        };
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array{ok: bool, income_source_id: int}
     */
    private function upsertIncomeSource(User $user, array $args): array
    {
        $name = $this->requireString($args, 'name');
        $currency = $this->optionalCurrency($args, 'currency') ?? 'MYR';
        $amountCents = $this->requireInt($args, 'amount_cents');
        $cadence = $this->optionalString($args, 'cadence') ?? 'monthly';
        $isActive = $this->optionalBool($args, 'is_active') ?? true;

        $row = IncomeSource::firstOrNew([
            'user_id' => $user->id,
            'name' => $name,
        ]);

        $row->fill([
            'currency' => $currency,
            'amount_cents' => max(0, $amountCents),
            'cadence' => $cadence,
            'is_active' => $isActive,
        ])->save();

        return ['ok' => true, 'income_source_id' => $row->id];
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array{ok: bool, commitment_id: int}
     */
    private function upsertCommitment(User $user, array $args): array
    {
        $name = $this->requireString($args, 'name');
        $currency = $this->optionalCurrency($args, 'currency') ?? 'MYR';
        $amountCents = $this->requireInt($args, 'amount_cents');
        $cadence = $this->optionalString($args, 'cadence') ?? 'monthly';
        $isActive = $this->optionalBool($args, 'is_active') ?? true;

        $row = Commitment::firstOrNew([
            'user_id' => $user->id,
            'name' => $name,
        ]);

        /** @var array<string, mixed> $attributes */
        $attributes = [
            'currency' => $currency,
            'amount_cents' => max(0, $amountCents),
            'cadence' => $cadence,
            'is_active' => $isActive,
        ];

        if (Arr::exists($args, 'category')) {
            $attributes['category'] = $this->optionalString($args, 'category');
        }

        if (Arr::exists($args, 'due_day')) {
            $attributes['due_day'] = $this->optionalDayOfMonth($args, 'due_day');
        }

        $row->fill($attributes)->save();

        return ['ok' => true, 'commitment_id' => $row->id];
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array{ok: bool, debt_id: int}
     */
    private function upsertDebt(User $user, array $args): array
    {
        $type = $this->optionalString($args, 'type') ?? 'credit_card';
        $name = $this->requireString($args, 'name');
        $currency = $this->optionalCurrency($args, 'currency') ?? 'MYR';
        $balanceCents = $this->optionalInt($args, 'balance_cents') ?? 0;
        $minimumPaymentCents = $this->optionalInt($args, 'minimum_payment_cents');
        $aprBps = $this->optionalInt($args, 'apr_bps');
        $dueDay = $this->optionalInt($args, 'due_day');
        $creditLimitCents = $this->optionalInt($args, 'credit_limit_cents');
        $isActive = $this->optionalBool($args, 'is_active') ?? true;

        $row = Debt::firstOrNew([
            'user_id' => $user->id,
            'type' => $type,
            'name' => $name,
        ]);

        $row->fill([
            'currency' => $currency,
            'balance_cents' => max(0, $balanceCents),
            'minimum_payment_cents' => is_null($minimumPaymentCents) ? null : max(0, $minimumPaymentCents),
            'apr_bps' => $aprBps,
            'due_day' => $dueDay,
            'credit_limit_cents' => $creditLimitCents,
            'is_active' => $isActive,
        ])->save();

        return ['ok' => true, 'debt_id' => $row->id];
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array{ok: bool}
     */
    private function deleteIncomeSource(User $user, array $args): array
    {
        $id = $this->optionalInt($args, 'id');
        $name = $this->optionalString($args, 'name');

        $query = IncomeSource::query()->where('user_id', $user->id);
        if ($id) {
            $query->whereKey($id);
        } elseif ($name) {
            $query->where('name', $name);
        } else {
            throw new InvalidArgumentException('delete_income_source requires id or name.');
        }

        $query->delete();

        return ['ok' => true];
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array{ok: bool}
     */
    private function deleteCommitment(User $user, array $args): array
    {
        $id = $this->optionalInt($args, 'id');
        $name = $this->optionalString($args, 'name');

        $query = Commitment::query()->where('user_id', $user->id);
        if ($id) {
            $query->whereKey($id);
        } elseif ($name) {
            $query->where('name', $name);
        } else {
            throw new InvalidArgumentException('delete_commitment requires id or name.');
        }

        $query->delete();

        return ['ok' => true];
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array{ok: bool}
     */
    private function deleteDebt(User $user, array $args): array
    {
        $id = $this->optionalInt($args, 'id');
        $name = $this->optionalString($args, 'name');
        $type = $this->optionalString($args, 'type');

        $query = Debt::query()->where('user_id', $user->id);
        if ($id) {
            $query->whereKey($id);
        } elseif ($name) {
            $query->where('name', $name);
            if ($type) {
                $query->where('type', $type);
            }
        } else {
            throw new InvalidArgumentException('delete_debt requires id or name.');
        }

        $query->delete();

        return ['ok' => true];
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array{ok: bool, monthly_budget_allocation_id: int}
     */
    private function upsertMonthlyBudgetAllocation(User $user, array $args): array
    {
        $yearMonth = $this->requireYearMonth($args, 'year_month');
        $category = $this->requireString($args, 'category');
        $currency = $this->optionalCurrency($args, 'currency') ?? 'MYR';
        $amountCents = $this->requireInt($args, 'amount_cents');
        $notes = $this->optionalString($args, 'notes');

        $row = MonthlyBudgetAllocation::firstOrNew([
            'user_id' => $user->id,
            'year_month' => $yearMonth,
            'category' => $category,
        ]);

        $row->fill([
            'currency' => $currency,
            'amount_cents' => max(0, $amountCents),
            'notes' => $notes,
        ])->save();

        return ['ok' => true, 'monthly_budget_allocation_id' => $row->id];
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array{ok: bool}
     */
    private function deleteMonthlyBudgetAllocation(User $user, array $args): array
    {
        $yearMonth = $this->requireYearMonth($args, 'year_month');
        $category = $this->requireString($args, 'category');

        MonthlyBudgetAllocation::query()
            ->where('user_id', $user->id)
            ->where('year_month', $yearMonth)
            ->where('category', $category)
            ->delete();

        return ['ok' => true];
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array{ok: bool, expense_entry_id: int}
     */
    private function logExpense(User $user, array $args): array
    {
        $category = $this->requireString($args, 'category');
        $currency = $this->optionalCurrency($args, 'currency') ?? 'MYR';
        $amountCents = $this->requireInt($args, 'amount_cents');
        if ($amountCents <= 0) {
            throw new InvalidArgumentException('amount_cents must be positive.');
        }

        $spentAt = $this->parseOptionalSpentAt($args);
        $yearMonth = $spentAt->format('Y-m');
        $placeLabel = $this->optionalString($args, 'place_label');
        $notes = $this->optionalString($args, 'notes');
        $lat = $this->optionalLatitude($args, 'latitude');
        $lng = $this->optionalLongitude($args, 'longitude');
        $accuracy = $this->optionalNonNegativeInt($args, 'location_accuracy_m');

        if (($lat !== null) !== ($lng !== null)) {
            throw new InvalidArgumentException('Provide both latitude and longitude, or neither.');
        }

        $row = ExpenseEntry::query()->create([
            'user_id' => $user->id,
            'spent_at' => $spentAt,
            'year_month' => $yearMonth,
            'category' => $category,
            'amount_cents' => $amountCents,
            'currency' => $currency,
            'place_label' => $placeLabel,
            'latitude' => $lat,
            'longitude' => $lng,
            'location_accuracy_m' => $accuracy,
            'notes' => $notes,
        ]);

        return ['ok' => true, 'expense_entry_id' => $row->id];
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array{ok: bool}
     */
    private function deleteExpense(User $user, array $args): array
    {
        $id = $this->requireInt($args, 'id');
        if ($id <= 0) {
            throw new InvalidArgumentException('Invalid id.');
        }

        $yearMonth = $this->optionalString($args, 'year_month');
        if ($yearMonth !== null) {
            $yearMonth = $this->normalizeYearMonthString($yearMonth);
        }

        $query = ExpenseEntry::query()->where('user_id', $user->id)->whereKey($id);
        if ($yearMonth !== null) {
            $query->where('year_month', $yearMonth);
        }

        $deleted = $query->delete();
        if ($deleted === 0) {
            throw new InvalidArgumentException('Expense not found or year_month did not match.');
        }

        return ['ok' => true];
    }

    /**
     * @param  array<string, mixed>  $args
     */
    private function parseOptionalSpentAt(array $args): Carbon
    {
        $raw = $this->optionalString($args, 'spent_at');
        if ($raw === null) {
            return now(config('app.timezone'));
        }

        try {
            return Carbon::parse($raw)->timezone(config('app.timezone'));
        } catch (\Throwable) {
            throw new InvalidArgumentException('Invalid spent_at; use an ISO 8601 date or datetime.');
        }
    }

    /** @param  array<string, mixed>  $args */
    private function optionalLatitude(array $args, string $key): ?float
    {
        $v = $this->optionalFloat($args, $key);
        if ($v === null) {
            return null;
        }
        if ($v < -90.0 || $v > 90.0) {
            throw new InvalidArgumentException("Invalid {$key}.");
        }

        return $v;
    }

    /** @param  array<string, mixed>  $args */
    private function optionalLongitude(array $args, string $key): ?float
    {
        $v = $this->optionalFloat($args, $key);
        if ($v === null) {
            return null;
        }
        if ($v < -180.0 || $v > 180.0) {
            throw new InvalidArgumentException("Invalid {$key}.");
        }

        return $v;
    }

    /** @param  array<string, mixed>  $args */
    private function optionalFloat(array $args, string $key): ?float
    {
        $value = Arr::get($args, $key);
        if (is_int($value)) {
            return (float) $value;
        }
        if (is_float($value)) {
            return $value;
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $args
     */
    private function optionalNonNegativeInt(array $args, string $key): ?int
    {
        $v = $this->optionalInt($args, $key);
        if ($v === null) {
            return null;
        }
        if ($v < 0) {
            throw new InvalidArgumentException("Invalid {$key}.");
        }

        return $v;
    }

    private function normalizeYearMonthString(string $value): string
    {
        if (! preg_match('/^\d{4}-\d{2}$/', $value)) {
            throw new InvalidArgumentException('Invalid year_month; use YYYY-MM.');
        }
        $parts = explode('-', $value);
        $m = (int) $parts[1];
        if ($m < 1 || $m > 12) {
            throw new InvalidArgumentException('Invalid year_month; month must be 01-12.');
        }

        return sprintf('%04d-%02d', (int) $parts[0], $m);
    }

    /** @param array<string, mixed> $args */
    private function requireYearMonth(array $args, string $key): string
    {
        $value = $this->requireString($args, $key);
        if (! preg_match('/^\\d{4}-\\d{2}$/', $value)) {
            throw new InvalidArgumentException("Invalid {$key}; use YYYY-MM.");
        }
        $parts = explode('-', $value);
        $m = (int) $parts[1];
        if ($m < 1 || $m > 12) {
            throw new InvalidArgumentException("Invalid {$key}; month must be 01-12.");
        }

        return sprintf('%04d-%02d', (int) $parts[0], $m);
    }

    /** @param array<string, mixed> $args */
    private function requireString(array $args, string $key): string
    {
        $value = Arr::get($args, $key);
        if (! is_string($value) || trim($value) === '') {
            throw new InvalidArgumentException("Missing or invalid {$key}.");
        }

        return trim($value);
    }

    /** @param array<string, mixed> $args */
    private function optionalString(array $args, string $key): ?string
    {
        $value = Arr::get($args, $key);
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }

    /** @param array<string, mixed> $args */
    private function requireInt(array $args, string $key): int
    {
        $value = Arr::get($args, $key);
        if (! is_int($value)) {
            throw new InvalidArgumentException("Missing or invalid {$key}.");
        }

        return $value;
    }

    /** @param array<string, mixed> $args */
    private function optionalInt(array $args, string $key): ?int
    {
        $value = Arr::get($args, $key);

        return is_int($value) ? $value : null;
    }

    /** @param array<string, mixed> $args */
    private function optionalBool(array $args, string $key): ?bool
    {
        $value = Arr::get($args, $key);

        return is_bool($value) ? $value : null;
    }

    /** @param array<string, mixed> $args */
    private function optionalCurrency(array $args, string $key): ?string
    {
        $value = $this->optionalString($args, $key);
        if ($value === null) {
            return null;
        }
        $value = strtoupper($value);

        if (strlen($value) !== 3) {
            throw new InvalidArgumentException("Invalid {$key}.");
        }

        return $value;
    }

    /** @param array<string, mixed> $args */
    private function optionalDayOfMonth(array $args, string $key): ?int
    {
        $value = $this->optionalInt($args, $key);
        if ($value === null) {
            return null;
        }

        if ($value < 1 || $value > 31) {
            throw new InvalidArgumentException("Invalid {$key}.");
        }

        return $value;
    }
}
