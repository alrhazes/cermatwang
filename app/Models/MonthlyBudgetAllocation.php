<?php

namespace App\Models;

use Database\Factories\MonthlyBudgetAllocationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MonthlyBudgetAllocation extends Model
{
    /** @use HasFactory<MonthlyBudgetAllocationFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'user_id',
        'year_month',
        'category',
        'amount_cents',
        'currency',
        'notes',
    ];

    /** @return BelongsTo<User, MonthlyBudgetAllocation> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
