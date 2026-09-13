<?php

namespace App\Models;

use App\ExpenseCategory;
use Database\Factories\BudgetItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['category', 'description', 'quantity', 'unit_amount'])]
class BudgetItem extends Model
{
    /** @use HasFactory<BudgetItemFactory> */
    use HasFactory, SoftDeletes;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['quantity' => 'integer', 'unit_amount' => 'integer', 'category' => ExpenseCategory::class];
    }

    public function total(): int
    {
        return $this->quantity * $this->unit_amount;
    }

    /** @return BelongsTo<Trip, $this> */
    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }
}
