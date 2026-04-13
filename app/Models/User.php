<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'onboarding_completed_at',
        'financial_profile',
        'ai_chat_provider',
        'ai_chat_model',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'onboarding_completed_at' => 'datetime',
            'financial_profile' => 'array',
            'password' => 'hashed',
        ];
    }

    /**
     * Structured money facts for prompts (empty until tools / imports populate).
     *
     * @return array<string, mixed>
     */
    public function financialProfilePayload(): array
    {
        $profile = $this->financial_profile;

        return is_array($profile) ? $profile : [];
    }

    public function needsFinancialOnboarding(): bool
    {
        return $this->onboarding_completed_at === null;
    }

    /** @return HasMany<IncomeSource, User> */
    public function incomeSources(): HasMany
    {
        return $this->hasMany(IncomeSource::class);
    }

    /** @return HasMany<Commitment, User> */
    public function commitments(): HasMany
    {
        return $this->hasMany(Commitment::class);
    }

    /** @return HasMany<Debt, User> */
    public function debts(): HasMany
    {
        return $this->hasMany(Debt::class);
    }

    /** @return HasMany<MonthlyBudgetAllocation, User> */
    public function monthlyBudgetAllocations(): HasMany
    {
        return $this->hasMany(MonthlyBudgetAllocation::class);
    }

    /** @return HasMany<ExpenseEntry, User> */
    public function expenseEntries(): HasMany
    {
        return $this->hasMany(ExpenseEntry::class);
    }
}
