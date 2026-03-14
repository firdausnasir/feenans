<?php

namespace Database\Factories;

use App\Models\AccountType;
use App\Models\Ledger;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AccountType>
 */
class AccountTypeFactory extends Factory
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
            'name' => fake()->unique()->words(2, true),
            'color' => fake()->safeHexColor(),
            'position' => fake()->numberBetween(0, 20),
            'is_credit' => false,
        ];
    }

    public function credit(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_credit' => true,
            'name' => 'Credit Card',
        ]);
    }
}
