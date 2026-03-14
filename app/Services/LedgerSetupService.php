<?php

namespace App\Services;

use App\Enums\TransactionType;
use App\Models\Ledger;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class LedgerSetupService
{
    /**
     * Create a ledger and its default supporting records.
     */
    public function createForUser(User $user, array $attributes): Ledger
    {
        return DB::transaction(function () use ($user, $attributes): Ledger {
            $ledger = $user->ledgers()->create([
                'name' => $attributes['name'],
                'currency_code' => strtoupper($attributes['currency_code']),
                'uses_seeded_categories' => (bool) $attributes['uses_seeded_categories'],
                'cycle_start_day' => $attributes['cycle_start_day'] ?? 1,
            ]);

            $defaultAccountTypes = [
                ['name' => 'Cash', 'position' => 1, 'is_credit' => false],
                ['name' => 'Bank', 'position' => 2, 'is_credit' => false],
                ['name' => 'Savings', 'position' => 3, 'is_credit' => false],
                ['name' => 'Credit Card', 'position' => 4, 'is_credit' => true],
                ['name' => 'E-Wallet', 'position' => 5, 'is_credit' => false],
                ['name' => 'Investment', 'position' => 6, 'is_credit' => false],
                ['name' => 'Loan', 'position' => 7, 'is_credit' => true],
            ];

            $ledger->accountTypes()->createMany($defaultAccountTypes);

            if ($ledger->uses_seeded_categories) {
                $ledger->categories()->createMany([
                    ['name' => 'Food', 'transaction_type' => TransactionType::Expense->value, 'position' => 1],
                    ['name' => 'Transport', 'transaction_type' => TransactionType::Expense->value, 'position' => 2],
                    ['name' => 'Bills', 'transaction_type' => TransactionType::Expense->value, 'position' => 3],
                    ['name' => 'Salary', 'transaction_type' => TransactionType::Income->value, 'position' => 4],
                    ['name' => 'Bonus', 'transaction_type' => TransactionType::Income->value, 'position' => 5],
                ]);
            }

            return $ledger;
        });
    }
}
