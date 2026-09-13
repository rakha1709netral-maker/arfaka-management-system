<?php

namespace Database\Factories;

use App\Models\Trip;
use App\Models\TripAssignment;
use App\Models\User;
use App\TripAssignmentStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<TripAssignment> */
class TripAssignmentFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'trip_id' => Trip::factory(), 'user_id' => User::factory()->tourLeader(),
            'status' => TripAssignmentStatus::Accepted, 'responded_at' => now(),
        ];
    }

    public function pending(): static
    {
        return $this->state(fn (): array => [
            'status' => TripAssignmentStatus::Pending, 'response_note' => null, 'responded_at' => null,
        ]);
    }
}
