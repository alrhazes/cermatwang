<?php

namespace App\Models;

use Database\Factories\DebtFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Debt extends Model
{
    /** @use HasFactory<DebtFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'user_id',
        'type',
        'name',
        'currency',
        'balance_cents',
        'minimum_payment_cents',
        'apr_bps',
        'due_day',
        'credit_limit_cents',
        'is_active',
    ];

    /** @return BelongsTo<User, Debt> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
