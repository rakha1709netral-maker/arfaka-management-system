<?php

namespace App\Models;

use App\TripAssignmentStatus;
use App\TripStatus;
use Database\Factories\TripFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable(['name', 'customer_id', 'destination', 'start_date', 'end_date', 'participant_count', 'notes', 'status'])]
class Trip extends Model
{
    /** @use HasFactory<TripFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function (Trip $trip): void {
            $trip->code = 'TRP-'.Str::ulid();
        });
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['start_date' => 'date', 'end_date' => 'date', 'participant_count' => 'integer', 'status' => TripStatus::class,
            'contract_amount' => 'integer', 'finances_locked_at' => 'datetime'];
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** @return HasMany<TripAssignment, $this> */
    public function assignments(): HasMany
    {
        return $this->hasMany(TripAssignment::class);
    }

    /** @return HasMany<FinanceEntry, $this> */
    public function financeEntries(): HasMany
    {
        return $this->hasMany(FinanceEntry::class);
    }

    /** @return HasMany<BudgetItem, $this> */
    public function budgetItems(): HasMany
    {
        return $this->hasMany(BudgetItem::class);
    }

    /** @return HasMany<FinanceAudit, $this> */
    public function financeAudits(): HasMany
    {
        return $this->hasMany(FinanceAudit::class);
    }

    /** @return BelongsToMany<User, $this> */
    public function tourLeaders(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'trip_assignments')
            ->withPivot(['status', 'response_note', 'responded_at'])
            ->withTimestamps();
    }

    /** @return BelongsToMany<User, $this> */
    public function acceptedTourLeaders(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'trip_assignments')
            ->wherePivot('status', TripAssignmentStatus::Accepted->value)
            ->withPivot(['responded_at'])
            ->withTimestamps();
    }
}
