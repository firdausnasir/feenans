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
                $this->seedDefaultCategories($ledger);
            }

            return $ledger;
        });
    }

    /**
     * Seed the default Malaysian-relevant category tree for a ledger.
     */
    private function seedDefaultCategories(Ledger $ledger): void
    {
        $expenseType = TransactionType::Expense->value;
        $incomeType = TransactionType::Income->value;

        $expenseCategories = [
            ['name' => 'Food & Drinks', 'color' => '#ef4444', 'children' => [
                'Groceries', 'Dining Out', 'Coffee & Tea', 'Snacks',
            ]],
            ['name' => 'Transport', 'color' => '#f97316', 'children' => [
                'Grab / Taxi', 'Fuel', 'Toll', 'Parking', 'Public Transport',
            ]],
            ['name' => 'Shopping', 'color' => '#eab308', 'children' => [
                'Clothing', 'Electronics', 'Online Shopping', 'Home Goods',
            ]],
            ['name' => 'Utilities', 'color' => '#22c55e', 'children' => [
                'Electricity', 'Water', 'Internet', 'Mobile Plan',
            ]],
            ['name' => 'Entertainment', 'color' => '#3b82f6', 'children' => [
                'Streaming', 'Movies', 'Games', 'Events',
            ]],
            ['name' => 'Health', 'color' => '#ec4899', 'children' => [
                'Pharmacy', 'Doctor / Clinic', 'Gym', 'Supplements',
            ]],
            ['name' => 'Personal Care', 'color' => '#a855f7', 'children' => [
                'Haircut', 'Skincare', 'Toiletries',
            ]],
            ['name' => 'Education', 'color' => '#6366f1', 'children' => [
                'Books', 'Courses', 'Tuition',
            ]],
            ['name' => 'Home', 'color' => '#14b8a6', 'children' => [
                'Rent', 'Maintenance', 'Furniture',
            ]],
            ['name' => 'Bills & Fees', 'color' => '#64748b', 'children' => [
                'Insurance', 'Subscriptions', 'Bank Fees', 'Government / Tax',
            ]],
            ['name' => 'Gifts & Donations', 'color' => '#f43f5e', 'children' => [
                'Gifts', 'Charity', 'Zakat',
            ]],
        ];

        $incomeCategories = [
            ['name' => 'Salary', 'color' => '#22c55e', 'children' => []],
            ['name' => 'Bonus', 'color' => '#16a34a', 'children' => []],
            ['name' => 'Freelance', 'color' => '#84cc16', 'children' => []],
            ['name' => 'Dividends', 'color' => '#06b6d4', 'children' => []],
            ['name' => 'Other Income', 'color' => '#8b5cf6', 'children' => []],
        ];

        $position = 1;

        foreach ($expenseCategories as $catData) {
            $parent = $ledger->categories()->create([
                'name' => $catData['name'],
                'transaction_type' => $expenseType,
                'color' => $catData['color'],
                'position' => $position++,
            ]);

            foreach ($catData['children'] as $childName) {
                $ledger->categories()->create([
                    'name' => $childName,
                    'transaction_type' => $expenseType,
                    'parent_id' => $parent->id,
                    'position' => $position++,
                ]);
            }
        }

        foreach ($incomeCategories as $catData) {
            $parent = $ledger->categories()->create([
                'name' => $catData['name'],
                'transaction_type' => $incomeType,
                'color' => $catData['color'],
                'position' => $position++,
            ]);

            foreach ($catData['children'] as $childName) {
                $ledger->categories()->create([
                    'name' => $childName,
                    'transaction_type' => $incomeType,
                    'parent_id' => $parent->id,
                    'position' => $position++,
                ]);
            }
        }
    }
}
