<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\Trip;
use App\TripStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Trip> */
class TripFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['name' => fake()->words(3, true), 'customer_id' => Customer::factory(), 'destination' => fake()->city(), 'start_date' => '2026-10-01', 'end_date' => '2026-10-03', 'participant_count' => 20, 'status' => TripStatus::Draft, 'notes' => null];
    }
}
