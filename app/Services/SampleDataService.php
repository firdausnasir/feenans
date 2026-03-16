<?php

namespace App\Services;

use App\Enums\RecurrenceType;
use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\Bill;
use App\Models\Category;
use App\Models\Ledger;
use App\Models\Payee;
use App\Models\Transaction;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SampleDataService
{
    /**
     * Generate realistic Malaysian sample data for a ledger.
     */
    public function generate(Ledger $ledger): void
    {
        DB::transaction(function () use ($ledger) {
            $accountTypes = $ledger->accountTypes()->get();

            $bankType = $accountTypes->firstWhere('name', 'Bank')
                ?? $accountTypes->firstWhere('name', 'Savings')
                ?? $accountTypes->first();

            $cashType = $accountTypes->firstWhere('name', 'Cash')
                ?? $accountTypes->first();

            // Create sample accounts
            $savingsAccount = $ledger->accounts()->create([
                'account_type_id' => $bankType->id,
                'name' => 'Maybank Savings',
                'initial_balance' => 5200.00,
                'include_in_totals' => true,
                'is_sample' => true,
            ]);

            $cashAccount = $ledger->accounts()->create([
                'account_type_id' => $cashType->id,
                'name' => 'Cash Wallet',
                'initial_balance' => 350.00,
                'include_in_totals' => true,
                'is_sample' => true,
            ]);

            // Create sample payees
            $payeeNames = [
                'Grab', 'Petronas', 'TNB', 'Unifi', 'Touch n Go',
                'Mydin', 'Shopee', 'Maxis', 'Jaya Grocer', 'Mamak Corner',
            ];

            $payees = [];
            foreach ($payeeNames as $name) {
                $payees[$name] = $ledger->payees()->create([
                    'name' => $name,
                    'is_sample' => true,
                ]);
            }

            // Gather existing categories
            $categories = $ledger->categories()->get();
            $expenseCategories = $categories->where('transaction_type', TransactionType::Expense->value);
            $incomeCategories = $categories->where('transaction_type', TransactionType::Income->value);

            $foodCategory = $this->findCategory($expenseCategories, 'Food & Drinks', 'Groceries', 'Dining');
            $transportCategory = $this->findCategory($expenseCategories, 'Transport', 'Fuel', 'Grab');
            $utilitiesCategory = $this->findCategory($expenseCategories, 'Utilities', 'Electricity', 'Bills');
            $shoppingCategory = $this->findCategory($expenseCategories, 'Shopping', 'Online Shopping');
            $entertainmentCategory = $this->findCategory($expenseCategories, 'Entertainment', 'Streaming');
            $healthCategory = $this->findCategory($expenseCategories, 'Health', 'Pharmacy');
            $salaryCategory = $this->findCategory($incomeCategories, 'Salary', 'Income');
            $freelanceCategory = $this->findCategory($incomeCategories, 'Freelance', 'Other Income');

            // Fallbacks if no categories found
            $defaultExpenseCategory = $expenseCategories->first();
            $defaultIncomeCategory = $incomeCategories->first();

            $today = CarbonImmutable::today();

            // Define realistic transactions spanning last 2 months
            /** @var array<int, array{account: Account, payee: string, category: ?Category, type: TransactionType, amount: float, description: string, daysAgo: int}> */
            $transactionDefinitions = [
                // Income - last month salary
                ['account' => $savingsAccount, 'payee' => null, 'category' => $salaryCategory ?? $defaultIncomeCategory, 'type' => TransactionType::Income, 'amount' => 4500.00, 'description' => 'Monthly Salary', 'daysAgo' => 45],
                // Income - this month salary
                ['account' => $savingsAccount, 'payee' => null, 'category' => $salaryCategory ?? $defaultIncomeCategory, 'type' => TransactionType::Income, 'amount' => 4500.00, 'description' => 'Monthly Salary', 'daysAgo' => 14],
                // Income - freelance
                ['account' => $savingsAccount, 'payee' => null, 'category' => $freelanceCategory ?? $defaultIncomeCategory, 'type' => TransactionType::Income, 'amount' => 800.00, 'description' => 'Website project', 'daysAgo' => 30],

                // Food expenses
                ['account' => $savingsAccount, 'payee' => 'Jaya Grocer', 'category' => $foodCategory ?? $defaultExpenseCategory, 'type' => TransactionType::Expense, 'amount' => -185.60, 'description' => 'Weekly groceries', 'daysAgo' => 3],
                ['account' => $savingsAccount, 'payee' => 'Jaya Grocer', 'category' => $foodCategory ?? $defaultExpenseCategory, 'type' => TransactionType::Expense, 'amount' => -142.30, 'description' => 'Weekly groceries', 'daysAgo' => 10],
                ['account' => $savingsAccount, 'payee' => 'Mydin', 'category' => $foodCategory ?? $defaultExpenseCategory, 'type' => TransactionType::Expense, 'amount' => -97.50, 'description' => 'Household supplies', 'daysAgo' => 18],
                ['account' => $cashAccount, 'payee' => 'Mamak Corner', 'category' => $foodCategory ?? $defaultExpenseCategory, 'type' => TransactionType::Expense, 'amount' => -12.00, 'description' => 'Nasi lemak breakfast', 'daysAgo' => 1],
                ['account' => $cashAccount, 'payee' => 'Mamak Corner', 'category' => $foodCategory ?? $defaultExpenseCategory, 'type' => TransactionType::Expense, 'amount' => -15.50, 'description' => 'Roti canai & teh tarik', 'daysAgo' => 4],
                ['account' => $cashAccount, 'payee' => 'Mamak Corner', 'category' => $foodCategory ?? $defaultExpenseCategory, 'type' => TransactionType::Expense, 'amount' => -18.00, 'description' => 'Mee goreng mamak', 'daysAgo' => 8],
                ['account' => $cashAccount, 'payee' => 'Mamak Corner', 'category' => $foodCategory ?? $defaultExpenseCategory, 'type' => TransactionType::Expense, 'amount' => -14.00, 'description' => 'Lunch nasi kandar', 'daysAgo' => 15],
                ['account' => $cashAccount, 'payee' => 'Mamak Corner', 'category' => $foodCategory ?? $defaultExpenseCategory, 'type' => TransactionType::Expense, 'amount' => -22.00, 'description' => 'Family dinner', 'daysAgo' => 22],

                // Transport
                ['account' => $savingsAccount, 'payee' => 'Petronas', 'category' => $transportCategory ?? $defaultExpenseCategory, 'type' => TransactionType::Expense, 'amount' => -120.00, 'description' => 'Fuel RON95', 'daysAgo' => 5],
                ['account' => $savingsAccount, 'payee' => 'Petronas', 'category' => $transportCategory ?? $defaultExpenseCategory, 'type' => TransactionType::Expense, 'amount' => -115.00, 'description' => 'Fuel RON95', 'daysAgo' => 20],
                ['account' => $savingsAccount, 'payee' => 'Grab', 'category' => $transportCategory ?? $defaultExpenseCategory, 'type' => TransactionType::Expense, 'amount' => -28.50, 'description' => 'Grab to KLCC', 'daysAgo' => 7],
                ['account' => $savingsAccount, 'payee' => 'Grab', 'category' => $transportCategory ?? $defaultExpenseCategory, 'type' => TransactionType::Expense, 'amount' => -15.00, 'description' => 'Grab to Mid Valley', 'daysAgo' => 12],
                ['account' => $savingsAccount, 'payee' => 'Touch n Go', 'category' => $transportCategory ?? $defaultExpenseCategory, 'type' => TransactionType::Expense, 'amount' => -50.00, 'description' => 'TnG reload', 'daysAgo' => 9],

                // Utilities
                ['account' => $savingsAccount, 'payee' => 'TNB', 'category' => $utilitiesCategory ?? $defaultExpenseCategory, 'type' => TransactionType::Expense, 'amount' => -185.40, 'description' => 'Electric bill', 'daysAgo' => 25],
                ['account' => $savingsAccount, 'payee' => 'Unifi', 'category' => $utilitiesCategory ?? $defaultExpenseCategory, 'type' => TransactionType::Expense, 'amount' => -149.00, 'description' => 'Internet bill', 'daysAgo' => 28],
                ['account' => $savingsAccount, 'payee' => 'Maxis', 'category' => $utilitiesCategory ?? $defaultExpenseCategory, 'type' => TransactionType::Expense, 'amount' => -68.00, 'description' => 'Mobile phone plan', 'daysAgo' => 26],

                // Shopping
                ['account' => $savingsAccount, 'payee' => 'Shopee', 'category' => $shoppingCategory ?? $defaultExpenseCategory, 'type' => TransactionType::Expense, 'amount' => -89.90, 'description' => 'Phone case & accessories', 'daysAgo' => 6],
                ['account' => $savingsAccount, 'payee' => 'Shopee', 'category' => $shoppingCategory ?? $defaultExpenseCategory, 'type' => TransactionType::Expense, 'amount' => -159.00, 'description' => 'Running shoes', 'daysAgo' => 35],

                // Entertainment
                ['account' => $savingsAccount, 'payee' => null, 'category' => $entertainmentCategory ?? $defaultExpenseCategory, 'type' => TransactionType::Expense, 'amount' => -54.90, 'description' => 'Netflix subscription', 'daysAgo' => 2],
                ['account' => $cashAccount, 'payee' => null, 'category' => $entertainmentCategory ?? $defaultExpenseCategory, 'type' => TransactionType::Expense, 'amount' => -35.00, 'description' => 'Movie tickets', 'daysAgo' => 11],

                // Health
                ['account' => $savingsAccount, 'payee' => null, 'category' => $healthCategory ?? $defaultExpenseCategory, 'type' => TransactionType::Expense, 'amount' => -45.00, 'description' => 'Pharmacy - vitamins', 'daysAgo' => 16],
                ['account' => $savingsAccount, 'payee' => null, 'category' => $healthCategory ?? $defaultExpenseCategory, 'type' => TransactionType::Expense, 'amount' => -80.00, 'description' => 'Clinic visit', 'daysAgo' => 32],

                // More food from last month
                ['account' => $savingsAccount, 'payee' => 'Jaya Grocer', 'category' => $foodCategory ?? $defaultExpenseCategory, 'type' => TransactionType::Expense, 'amount' => -210.80, 'description' => 'Weekly groceries', 'daysAgo' => 38],
                ['account' => $savingsAccount, 'payee' => 'Mydin', 'category' => $foodCategory ?? $defaultExpenseCategory, 'type' => TransactionType::Expense, 'amount' => -78.20, 'description' => 'Snacks & drinks', 'daysAgo' => 42],
                ['account' => $cashAccount, 'payee' => 'Mamak Corner', 'category' => $foodCategory ?? $defaultExpenseCategory, 'type' => TransactionType::Expense, 'amount' => -16.50, 'description' => 'Char kuey teow', 'daysAgo' => 40],
                ['account' => $cashAccount, 'payee' => 'Mamak Corner', 'category' => $foodCategory ?? $defaultExpenseCategory, 'type' => TransactionType::Expense, 'amount' => -11.00, 'description' => 'Teh tarik & pisang goreng', 'daysAgo' => 48],

                // Transport last month
                ['account' => $savingsAccount, 'payee' => 'Petronas', 'category' => $transportCategory ?? $defaultExpenseCategory, 'type' => TransactionType::Expense, 'amount' => -110.00, 'description' => 'Fuel RON95', 'daysAgo' => 36],
                ['account' => $savingsAccount, 'payee' => 'Grab', 'category' => $transportCategory ?? $defaultExpenseCategory, 'type' => TransactionType::Expense, 'amount' => -22.00, 'description' => 'Grab to Sunway Pyramid', 'daysAgo' => 44],

                // Last month shopping
                ['account' => $savingsAccount, 'payee' => 'Shopee', 'category' => $shoppingCategory ?? $defaultExpenseCategory, 'type' => TransactionType::Expense, 'amount' => -45.90, 'description' => 'Kitchen utensils', 'daysAgo' => 50],
            ];

            foreach ($transactionDefinitions as $def) {
                $payeeId = null;
                if ($def['payee'] !== null && isset($payees[$def['payee']])) {
                    $payeeId = $payees[$def['payee']]->id;
                }

                $ledger->transactions()->create([
                    'account_id' => $def['account']->id,
                    'category_id' => $def['category']?->id,
                    'payee_id' => $payeeId,
                    'transaction_type' => $def['type'],
                    'amount' => $def['amount'],
                    'description' => $def['description'],
                    'transaction_date' => $today->subDays($def['daysAgo']),
                    'is_sample' => true,
                ]);
            }

            // Create sample recurring bills
            $billDefinitions = [
                [
                    'name' => 'Electricity (TNB)',
                    'payee' => 'TNB',
                    'category' => $utilitiesCategory ?? $defaultExpenseCategory,
                    'amount' => 185.40,
                    'recurrence_day' => 15,
                ],
                [
                    'name' => 'Internet (Unifi)',
                    'payee' => 'Unifi',
                    'category' => $utilitiesCategory ?? $defaultExpenseCategory,
                    'amount' => 149.00,
                    'recurrence_day' => 1,
                ],
                [
                    'name' => 'Phone (Maxis)',
                    'payee' => 'Maxis',
                    'category' => $utilitiesCategory ?? $defaultExpenseCategory,
                    'amount' => 68.00,
                    'recurrence_day' => 5,
                ],
            ];

            foreach ($billDefinitions as $billDef) {
                $payeeId = null;
                if (isset($payees[$billDef['payee']])) {
                    $payeeId = $payees[$billDef['payee']]->id;
                }

                // Set next_due_date to the recurrence_day in the current or next month
                $dueDate = $today->setDay(min($billDef['recurrence_day'], $today->daysInMonth));
                if ($dueDate->isPast()) {
                    $nextMonth = $today->addMonthNoOverflow();
                    $dueDate = $nextMonth->setDay(min($billDef['recurrence_day'], $nextMonth->daysInMonth));
                }

                $ledger->bills()->create([
                    'account_id' => $savingsAccount->id,
                    'category_id' => $billDef['category']?->id,
                    'payee_id' => $payeeId,
                    'name' => $billDef['name'],
                    'transaction_type' => TransactionType::Expense,
                    'amount' => $billDef['amount'],
                    'recurrence_type' => RecurrenceType::Monthly,
                    'recurrence_interval' => 1,
                    'recurrence_day' => $billDef['recurrence_day'],
                    'next_due_date' => $dueDate,
                    'auto_create' => false,
                    'end_type' => Bill::END_TYPE_NEVER,
                    'is_active' => true,
                    'is_sample' => true,
                ]);
            }
        });
    }

    /**
     * Remove all sample data for a ledger.
     */
    public function remove(Ledger $ledger): void
    {
        DB::transaction(function () use ($ledger) {
            Transaction::query()
                ->where('ledger_id', $ledger->id)
                ->where('is_sample', true)
                ->delete();

            Bill::query()
                ->where('ledger_id', $ledger->id)
                ->where('is_sample', true)
                ->delete();

            Account::query()
                ->where('ledger_id', $ledger->id)
                ->where('is_sample', true)
                ->delete();

            Payee::query()
                ->where('ledger_id', $ledger->id)
                ->where('is_sample', true)
                ->delete();
        });
    }

    /**
     * Check whether a ledger has any sample data.
     */
    public function hasSampleData(Ledger $ledger): bool
    {
        return Transaction::query()
            ->where('ledger_id', $ledger->id)
            ->where('is_sample', true)
            ->exists()
            || Account::query()
                ->where('ledger_id', $ledger->id)
                ->where('is_sample', true)
                ->exists();
    }

    /**
     * Find a category by trying multiple name patterns.
     */
    private function findCategory(Collection $categories, string ...$names): ?Category
    {
        foreach ($names as $name) {
            $found = $categories->first(function (Category $category) use ($name) {
                return stripos($category->name, $name) !== false;
            });

            if ($found) {
                return $found;
            }
        }

        return null;
    }
}
