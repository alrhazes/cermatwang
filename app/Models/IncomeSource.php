<?php

namespace App\Models;

use Database\Factories\IncomeSourceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IncomeSource extends Model
{
    /** @use HasFactory<IncomeSourceFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'user_id',
        'name',
        'currency',
        'amount_cents',
        'cadence',
        'is_active',
    ];

    /** @return BelongsTo<User, IncomeSource> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
