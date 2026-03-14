<?php

namespace Database\Factories;

use App\Enums\TransactionType;
use App\Models\Category;
use App\Models\Ledger;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Category>
 */
class CategoryFactory extends Factory
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
            'name' => fake()->unique()->word(),
            'transaction_type' => TransactionType::Expense->value,
            'color' => fake()->safeHexColor(),
            'icon' => 'receipt',
            'position' => fake()->numberBetween(0, 20),
        ];
    }
}
