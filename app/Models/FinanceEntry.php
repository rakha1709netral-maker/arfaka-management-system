<?php

namespace App\Models;

use App\ExpenseCategory;
use App\FinanceType;
use Database\Factories\FinanceEntryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['type', 'category', 'occurred_on', 'amount', 'description'])]
class FinanceEntry extends Model
{
    /** @use HasFactory<FinanceEntryFactory> */
    use HasFactory;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'amount' => 'integer', 'occurred_on' => 'date', 'type' => FinanceType::class,
            'category' => ExpenseCategory::class, 'reviewed_at' => 'datetime', 'captured_at' => 'datetime',
            'latitude' => 'decimal:7', 'longitude' => 'decimal:7', 'location_accuracy' => 'decimal:2',
            'receipt_original_size' => 'integer', 'receipt_stored_size' => 'integer',
        ];
    }

    /** @return BelongsTo<Trip, $this> */
    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return BelongsTo<User, $this> */
    public function leader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'leader_id');
    }

    /** @return BelongsTo<User, $this> */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
