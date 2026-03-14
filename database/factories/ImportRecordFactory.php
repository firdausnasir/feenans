<?php

namespace Database\Factories;

use App\Models\ImportRecord;
use App\Models\Ledger;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ImportRecord>
 */
class ImportRecordFactory extends Factory
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
            'filename' => fake()->word().'.csv',
            'row_count' => fake()->numberBetween(10, 500),
            'imported_count' => fake()->numberBetween(5, 400),
            'skipped_count' => fake()->numberBetween(0, 50),
            'mapping_used' => null,
            'imported_at' => fake()->dateTimeBetween('-30 days'),
        ];
    }
}
