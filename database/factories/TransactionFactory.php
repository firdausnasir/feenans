<?php

namespace Database\Factories;

use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\Category;
use App\Models\Ledger;
use App\Models\Payee;
use App\Models\Transaction;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Transaction>
 */
class TransactionFactory extends Factory
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
            'account_id' => Account::factory(),
            'category_id' => Category::factory(),
            'payee_id' => Payee::factory(),
            'transaction_type' => TransactionType::Expense,
            'amount' => fake()->randomFloat(2, -500, 5000),
            'description' => fake()->sentence(3),
            'notes' => fake()->sentence(),
            'transaction_date' => fake()->date(),
            'transfer_pair_id' => null,
        ];
    }

    public function expense(): static
    {
        return $this->state(fn (array $attributes) => [
            'transaction_type' => TransactionType::Expense,
            'amount' => -1 * fake()->randomFloat(2, 1, 1000),
        ]);
    }

    public function income(): static
    {
        return $this->state(fn (array $attributes) => [
            'transaction_type' => TransactionType::Income,
            'amount' => fake()->randomFloat(2, 1, 1000),
        ]);
    }

    public function transferOut(): static
    {
        return $this->state(fn (array $attributes) => [
            'transaction_type' => TransactionType::Transfer,
            'amount' => -1 * fake()->randomFloat(2, 1, 1000),
            'category_id' => null,
            'transfer_pair_id' => (string) Str::uuid(),
        ]);
    }

    public function transferIn(): static
    {
        return $this->state(fn (array $attributes) => [
            'transaction_type' => TransactionType::Transfer,
            'amount' => fake()->randomFloat(2, 1, 1000),
            'category_id' => null,
            'transfer_pair_id' => (string) Str::uuid(),
        ]);
    }
}
