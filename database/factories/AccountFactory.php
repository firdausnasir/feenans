<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\AccountType;
use App\Models\Ledger;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Account>
 */
class AccountFactory extends Factory
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
            'account_type_id' => AccountType::factory(),
            'name' => fake()->unique()->company(),
            'initial_balance' => fake()->randomFloat(2, -500, 5000),
            'statement_day' => null,
            'include_in_totals' => true,
        ];
    }

    public function creditCard(): static
    {
        return $this->state(fn (array $attributes) => [
            'statement_day' => 15,
        ]);
    }

    public function hidden(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_hidden' => true,
        ]);
    }
}
