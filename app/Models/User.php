<?php

namespace App\Models;

use App\TripAssignmentStatus;
use App\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'role', 'is_active'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /** @return HasMany<TripAssignment, $this> */
    public function tripAssignments(): HasMany
    {
        return $this->hasMany(TripAssignment::class);
    }

    /** @return BelongsToMany<Trip, $this> */
    public function assignedTrips(): BelongsToMany
    {
        return $this->belongsToMany(Trip::class, 'trip_assignments')
            ->wherePivot('status', TripAssignmentStatus::Accepted->value)
            ->withPivot(['status', 'response_note', 'responded_at'])
            ->withTimestamps();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'is_active' => 'boolean',
        ];
    }
}
