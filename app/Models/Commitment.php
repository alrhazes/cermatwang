<?php

namespace App\Models;

use Database\Factories\CommitmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Commitment extends Model
{
    /** @use HasFactory<CommitmentFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'user_id',
        'name',
        'category',
        'currency',
        'amount_cents',
        'due_day',
        'cadence',
        'is_active',
    ];

    /** @return BelongsTo<User, Commitment> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
