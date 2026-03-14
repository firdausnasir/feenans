<?php

namespace Database\Factories;

use App\Models\Ledger;
use App\Models\Tag;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Tag>
 */
class TagFactory extends Factory
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
            'color' => null,
        ];
    }
}
