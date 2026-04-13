<?php

namespace App\Models;

use Database\Factories\ExpenseEntryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExpenseEntry extends Model
{
    /** @use HasFactory<ExpenseEntryFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'spent_at',
        'year_month',
        'category',
        'amount_cents',
        'currency',
        'place_label',
        'latitude',
        'longitude',
        'location_accuracy_m',
        'notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'spent_at' => 'datetime',
            'latitude' => 'float',
            'longitude' => 'float',
        ];
    }

    /** @return BelongsTo<User, ExpenseEntry> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
