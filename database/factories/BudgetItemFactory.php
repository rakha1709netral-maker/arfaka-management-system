<?php

namespace Database\Factories;

use App\ExpenseCategory;
use App\Models\BudgetItem;
use App\Models\Trip;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<BudgetItem> */
class BudgetItemFactory extends Factory
{
    public function definition(): array
    {
        return [
            'trip_id' => Trip::factory(), 'category' => ExpenseCategory::Meals,
            'description' => fake()->sentence(), 'quantity' => 20, 'unit_amount' => 25000,
        ];
    }
}
