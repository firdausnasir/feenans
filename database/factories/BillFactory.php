<?php

namespace Database\Factories;

use App\Enums\RecurrenceType;
use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\Bill;
use App\Models\Ledger;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Bill>
 */
class BillFactory extends Factory
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
            'category_id' => null,
            'payee_id' => null,
            'name' => fake()->words(2, true),
            'transaction_type' => TransactionType::Expense,
            'amount' => fake()->randomFloat(2, 5, 500),
            'recurrence_type' => RecurrenceType::Monthly,
            'recurrence_interval' => 1,
            'recurrence_day' => null,
            'next_due_date' => fake()->dateTimeBetween('now', '+30 days'),
            'auto_create' => false,
            'end_type' => null,
            'end_date' => null,
            'end_after_occurrences' => null,
            'occurrences_count' => 0,
            'is_active' => true,
            'notify_email' => true,
        ];
    }
}
