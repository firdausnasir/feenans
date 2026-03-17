<?php

namespace Database\Factories;

use App\Models\Budget;
use App\Models\Category;
use App\Models\Ledger;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Budget>
 */
class BudgetFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ledger_id' => Ledger::factory(),
            'category_id' => Category::factory(),
            'amount' => $this->faker->randomFloat(2, 50, 2000),
            'period' => 'monthly',
            'start_date' => now()->startOfMonth()->toDateString(),
            'is_active' => true,
            'rollover' => false,
        ];
    }
}
