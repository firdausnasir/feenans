<?php

namespace Database\Factories;

use App\Models\Ledger;
use App\Models\Payee;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payee>
 */
class PayeeFactory extends Factory
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
            'name' => fake()->unique()->company(),
        ];
    }
}
