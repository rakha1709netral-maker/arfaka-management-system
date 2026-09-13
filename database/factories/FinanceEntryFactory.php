<?php

namespace Database\Factories;

use App\ExpenseCategory;
use App\FinanceType;
use App\Models\FinanceEntry;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<FinanceEntry> */
class FinanceEntryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'trip_id' => Trip::factory(), 'created_by' => User::factory()->superAdmin(),
            'type' => FinanceType::Expense, 'category' => ExpenseCategory::Meals,
            'status' => 'approved', 'reviewed_at' => now(), 'occurred_on' => '2026-09-01', 'amount' => 100000,
            'description' => fake()->sentence(), 'submission_token' => fake()->uuid(),
        ];
    }
}
