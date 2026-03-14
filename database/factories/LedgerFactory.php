<?php

namespace Database\Factories;

use App\Models\Ledger;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Ledger>
 */
class LedgerFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->unique()->words(2, true),
            'currency_code' => 'MYR',
            'uses_seeded_categories' => true,
        ];
    }
}
