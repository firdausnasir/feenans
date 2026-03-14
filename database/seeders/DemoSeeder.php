<?php

namespace Database\Seeders;

use App\Enums\RecurrenceType;
use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\AccountType;
use App\Models\Bill;
use App\Models\Category;
use App\Models\Ledger;
use App\Models\Payee;
use App\Models\Transaction;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::where('email', 'test@example.com')->firstOrFail();
        $user->update([
            'name' => 'Demo User',
            'password' => bcrypt('password'),
            'onboarding_step' => null,
        ]);

        // Ledger
        $ledger = Ledger::create([
            'user_id' => $user->id,
            'name' => 'My Finances',
            'currency_code' => 'MYR',
            'cycle_start_day' => 1,
            'uses_seeded_categories' => false,
        ]);

        // Account Types
        $bankType = AccountType::create([
            'ledger_id' => $ledger->id,
            'name' => 'Bank Account',
            'is_credit' => false,
            'color' => '#3b82f6',
            'position' => 1,
        ]);

        $creditType = AccountType::create([
            'ledger_id' => $ledger->id,
            'name' => 'Credit Card',
            'is_credit' => true,
            'color' => '#8b5cf6',
            'position' => 2,
        ]);

        $ewalletType = AccountType::create([
            'ledger_id' => $ledger->id,
            'name' => 'E-Wallet',
            'is_credit' => false,
            'color' => '#10b981',
            'position' => 3,
        ]);

        $cashType = AccountType::create([
            'ledger_id' => $ledger->id,
            'name' => 'Cash',
            'is_credit' => false,
            'color' => '#f59e0b',
            'position' => 4,
        ]);

        // Accounts
        $maybank = Account::create([
            'ledger_id' => $ledger->id,
            'account_type_id' => $bankType->id,
            'name' => 'Maybank Savings',
            'initial_balance' => 5200.00,
            'position' => 1,
        ]);

        $cimb = Account::create([
            'ledger_id' => $ledger->id,
            'account_type_id' => $creditType->id,
            'name' => 'CIMB Credit Card',
            'initial_balance' => 0.00,
            'statement_day' => 15,
            'position' => 2,
        ]);

        $tng = Account::create([
            'ledger_id' => $ledger->id,
            'account_type_id' => $ewalletType->id,
            'name' => "Touch 'n Go eWallet",
            'initial_balance' => 200.00,
            'position' => 3,
        ]);

        $cash = Account::create([
            'ledger_id' => $ledger->id,
            'account_type_id' => $cashType->id,
            'name' => 'Cash Wallet',
            'initial_balance' => 150.00,
            'position' => 4,
            'is_hidden' => true,
        ]);

        // Payees
        $payees = [];
        $payeeNames = [
            'Grab', 'Shopee', 'Lazada', 'Watsons', 'Guardian',
            'Tesco', 'AEON', 'Uniqlo', 'Spotify', 'Netflix',
            'Astro', 'Telekom', 'TNB', 'Petronas', "McDonald's",
            'Starbucks', 'KFC', 'Jaya Grocer', 'Parkson', 'Lotus\'s',
        ];

        foreach ($payeeNames as $i => $name) {
            $payees[$name] = Payee::create([
                'ledger_id' => $ledger->id,
                'name' => $name,
            ]);
        }

        // Categories — Expense Parents & Subcategories
        $expenseColors = [
            'Food & Drinks' => '#f97316',
            'Transport' => '#3b82f6',
            'Shopping' => '#ec4899',
            'Utilities' => '#6b7280',
            'Entertainment' => '#8b5cf6',
            'Health' => '#ef4444',
            'Personal Care' => '#14b8a6',
            'Education' => '#0ea5e9',
            'Home' => '#a16207',
        ];

        $expenseSubcategories = [
            'Food & Drinks' => ['Groceries', 'Dining Out', 'Coffee & Tea', 'Snacks'],
            'Transport' => ['Grab / Ride-hailing', 'Fuel', 'Toll', 'Parking', 'Public Transport'],
            'Shopping' => ['Clothing', 'Electronics', 'Online Shopping', 'Home Goods'],
            'Utilities' => ['Electricity', 'Water', 'Internet', 'Mobile Plan'],
            'Entertainment' => ['Streaming', 'Movies', 'Games', 'Events'],
            'Health' => ['Pharmacy', 'Doctor / Clinic', 'Gym', 'Supplements'],
            'Personal Care' => ['Haircut', 'Skincare', 'Toiletries'],
            'Education' => ['Books', 'Courses', 'Tuition'],
            'Home' => ['Rent', 'Maintenance', 'Furniture'],
        ];

        $expenseCats = [];
        $expenseSubCats = [];
        $pos = 1;
        foreach ($expenseColors as $parentName => $color) {
            $parent = Category::create([
                'ledger_id' => $ledger->id,
                'parent_id' => null,
                'name' => $parentName,
                'transaction_type' => 'expense',
                'color' => $color,
                'icon' => null,
                'position' => $pos++,
            ]);
            $expenseCats[$parentName] = $parent;

            $subPos = 1;
            foreach ($expenseSubcategories[$parentName] as $subName) {
                $sub = Category::create([
                    'ledger_id' => $ledger->id,
                    'parent_id' => $parent->id,
                    'name' => $subName,
                    'transaction_type' => 'expense',
                    'color' => $color,
                    'icon' => null,
                    'position' => $subPos++,
                ]);
                $expenseSubCats[$parentName][] = $sub;
            }
        }

        // Categories — Income Parents & Subcategories
        $incomeColors = [
            'Salary' => '#22c55e',
            'Freelance' => '#84cc16',
            'Investment Returns' => '#06b6d4',
            'Other Income' => '#a855f7',
        ];

        $incomeSubcategories = [
            'Salary' => ['Monthly Salary', 'Bonus', 'Allowance'],
            'Freelance' => ['Project Payment', 'Consulting'],
            'Investment Returns' => ['Dividends', 'Interest', 'Capital Gains'],
            'Other Income' => ['Gift', 'Cashback', 'Refund'],
        ];

        $incomeCats = [];
        $incomeSubCats = [];
        foreach ($incomeColors as $parentName => $color) {
            $parent = Category::create([
                'ledger_id' => $ledger->id,
                'parent_id' => null,
                'name' => $parentName,
                'transaction_type' => 'income',
                'color' => $color,
                'icon' => null,
                'position' => $pos++,
            ]);
            $incomeCats[$parentName] = $parent;

            $subPos = 1;
            foreach ($incomeSubcategories[$parentName] as $subName) {
                $sub = Category::create([
                    'ledger_id' => $ledger->id,
                    'parent_id' => $parent->id,
                    'name' => $subName,
                    'transaction_type' => 'income',
                    'color' => $color,
                    'icon' => null,
                    'position' => $subPos++,
                ]);
                $incomeSubCats[$parentName][] = $sub;
            }
        }

        // Bills
        $bills = [
            [
                'name' => 'Netflix',
                'amount' => 17.90,
                'account_id' => $cimb->id,
                'category_id' => $expenseSubCats['Entertainment'][0]->id, // Streaming
                'payee_id' => $payees['Netflix']->id,
                'recurrence_day' => 5,
                'auto_create' => true,
            ],
            [
                'name' => 'Spotify',
                'amount' => 8.90,
                'account_id' => $cimb->id,
                'category_id' => $expenseSubCats['Entertainment'][0]->id, // Streaming
                'payee_id' => $payees['Spotify']->id,
                'recurrence_day' => 8,
                'auto_create' => true,
            ],
            [
                'name' => 'Astro',
                'amount' => 99.90,
                'account_id' => $maybank->id,
                'category_id' => $expenseSubCats['Entertainment'][0]->id, // Streaming
                'payee_id' => $payees['Astro']->id,
                'recurrence_day' => 1,
                'auto_create' => false,
            ],
            [
                'name' => 'TNB Electricity',
                'amount' => 82.50,
                'account_id' => $maybank->id,
                'category_id' => $expenseSubCats['Utilities'][0]->id, // Electricity
                'payee_id' => $payees['TNB']->id,
                'recurrence_day' => 20,
                'auto_create' => false,
            ],
            [
                'name' => 'Telekom Unifi',
                'amount' => 119.00,
                'account_id' => $maybank->id,
                'category_id' => $expenseSubCats['Utilities'][2]->id, // Internet
                'payee_id' => $payees['Telekom']->id,
                'recurrence_day' => 15,
                'auto_create' => false,
            ],
            [
                'name' => 'Gym Membership',
                'amount' => 99.00,
                'account_id' => $cimb->id,
                'category_id' => $expenseSubCats['Health'][2]->id, // Gym
                'payee_id' => null,
                'recurrence_day' => 1,
                'auto_create' => true,
            ],
            [
                'name' => 'Mobile Plan',
                'amount' => 45.00,
                'account_id' => $maybank->id,
                'category_id' => $expenseSubCats['Utilities'][3]->id, // Mobile Plan
                'payee_id' => null,
                'recurrence_day' => 10,
                'auto_create' => false,
            ],
        ];

        $createdBills = [];
        foreach ($bills as $bill) {
            $today = CarbonImmutable::today();
            $day = $bill['recurrence_day'];
            $nextDue = $today->setDay(min($day, $today->daysInMonth));
            if ($nextDue->lte($today)) {
                $nextDue = $nextDue->addMonth()->setDay(min($day, $nextDue->addMonth()->daysInMonth));
            }

            $createdBills[$bill['name']] = Bill::create([
                'ledger_id' => $ledger->id,
                'account_id' => $bill['account_id'],
                'category_id' => $bill['category_id'],
                'payee_id' => $bill['payee_id'] ?? null,
                'name' => $bill['name'],
                'amount' => $bill['amount'],
                'recurrence_type' => RecurrenceType::Monthly,
                'recurrence_interval' => 1,
                'recurrence_day' => $day,
                'next_due_date' => $nextDue->toDateString(),
                'auto_create' => $bill['auto_create'],
                'end_type' => Bill::END_TYPE_NEVER,
                'end_date' => null,
                'end_after_occurrences' => null,
                'occurrences_count' => 3,
                'is_active' => true,
            ]);
        }

        // Transactions — 3 months of data
        $this->seedTransactions(
            $ledger, $maybank, $cimb, $tng, $cash,
            $payees, $expenseCats, $expenseSubCats, $incomeCats, $incomeSubCats,
            $createdBills
        );
    }

    private function seedTransactions(
        Ledger $ledger,
        Account $maybank,
        Account $cimb,
        Account $tng,
        Account $cash,
        array $payees,
        array $expenseCats,
        array $expenseSubCats,
        array $incomeCats,
        array $incomeSubCats,
        array $bills
    ): void {
        $today = CarbonImmutable::today();

        // --- Salary income: 1st of each of the last 3 months ---
        for ($m = 2; $m >= 0; $m--) {
            $salaryDate = $today->subMonths($m)->startOfMonth()->toDateString();
            $salaryAmount = match ($m) {
                2 => 4800.00,
                1 => 4800.00,
                0 => 4800.00,
            };

            Transaction::create([
                'ledger_id' => $ledger->id,
                'account_id' => $maybank->id,
                'category_id' => $incomeSubCats['Salary'][0]->id, // Monthly Salary
                'payee_id' => null,
                'transaction_type' => TransactionType::Income,
                'amount' => $salaryAmount,
                'description' => 'Monthly salary',
                'notes' => null,
                'transaction_date' => $salaryDate,
                'transfer_pair_id' => null,
            ]);

            // Bonus in first month
            if ($m === 2) {
                Transaction::create([
                    'ledger_id' => $ledger->id,
                    'account_id' => $maybank->id,
                    'category_id' => $incomeSubCats['Salary'][1]->id, // Bonus
                    'payee_id' => null,
                    'transaction_type' => TransactionType::Income,
                    'amount' => 1200.00,
                    'description' => 'Performance bonus',
                    'notes' => null,
                    'transaction_date' => $today->subMonths(2)->startOfMonth()->addDays(3)->toDateString(),
                    'transfer_pair_id' => null,
                ]);
            }
        }

        // --- Monthly TnG top-up transfer (Maybank → TnG) ---
        for ($m = 2; $m >= 0; $m--) {
            $transferDate = $today->subMonths($m)->startOfMonth()->addDays(2)->toDateString();
            $pairId = (string) Str::uuid();
            $topUpAmount = 100.00;

            Transaction::create([
                'ledger_id' => $ledger->id,
                'account_id' => $maybank->id,
                'category_id' => null,
                'payee_id' => null,
                'transaction_type' => TransactionType::Transfer,
                'amount' => -$topUpAmount,
                'description' => 'TnG top-up',
                'notes' => null,
                'transaction_date' => $transferDate,
                'transfer_pair_id' => $pairId,
            ]);

            Transaction::create([
                'ledger_id' => $ledger->id,
                'account_id' => $tng->id,
                'category_id' => null,
                'payee_id' => null,
                'transaction_type' => TransactionType::Transfer,
                'amount' => $topUpAmount,
                'description' => 'TnG top-up',
                'notes' => null,
                'transaction_date' => $transferDate,
                'transfer_pair_id' => $pairId,
            ]);
        }

        // --- Weekly groceries ---
        $this->seedWeeklyExpenses($ledger, $maybank, $expenseSubCats['Food & Drinks'][0], $payees['Tesco'], 'Weekly groceries', 65.00, 130.00, $today);
        $this->seedWeeklyExpenses($ledger, $maybank, $expenseSubCats['Food & Drinks'][0], $payees['Jaya Grocer'], 'Groceries', 50.00, 110.00, $today);

        // --- Recurring subscriptions on credit card (monthly) ---
        $subscriptions = [
            ['Netflix', 17.90, $payees['Netflix'], $expenseSubCats['Entertainment'][0]],
            ['Spotify', 8.90, $payees['Spotify'], $expenseSubCats['Entertainment'][0]],
            ['Gym Membership', 99.00, null, $expenseSubCats['Health'][2]],
        ];

        foreach ($subscriptions as [$desc, $amount, $payee, $cat]) {
            $billId = $bills[$desc]->id ?? null;
            for ($m = 2; $m >= 0; $m--) {
                $subDate = $today->subMonths($m)->startOfMonth()->addDays(4)->toDateString();
                Transaction::create([
                    'ledger_id' => $ledger->id,
                    'account_id' => $cimb->id,
                    'category_id' => $cat->id,
                    'payee_id' => $payee?->id,
                    'transaction_type' => TransactionType::Expense,
                    'amount' => -$amount,
                    'description' => $desc,
                    'notes' => null,
                    'transaction_date' => $subDate,
                    'transfer_pair_id' => null,
                    'bill_id' => $billId,
                ]);
            }
        }

        // --- Utilities on Maybank (monthly) ---
        $utilities = [
            ['TNB Electricity', 82.50, $payees['TNB'], $expenseSubCats['Utilities'][0], 20],
            ['Telekom Unifi', 119.00, $payees['Telekom'], $expenseSubCats['Utilities'][2], 15],
            ['Mobile Plan', 45.00, null, $expenseSubCats['Utilities'][3], 10],
            ['Astro', 99.90, $payees['Astro'], $expenseSubCats['Entertainment'][0], 1],
        ];

        foreach ($utilities as [$desc, $amount, $payee, $cat, $day]) {
            $billId = $bills[$desc]->id ?? null;
            for ($m = 2; $m >= 0; $m--) {
                $ref = $today->subMonths($m);
                $utilDate = $ref->setDay(min($day, $ref->daysInMonth))->toDateString();
                Transaction::create([
                    'ledger_id' => $ledger->id,
                    'account_id' => $maybank->id,
                    'category_id' => $cat->id,
                    'payee_id' => $payee?->id,
                    'transaction_type' => TransactionType::Expense,
                    'amount' => -$amount,
                    'description' => $desc,
                    'notes' => null,
                    'transaction_date' => $utilDate,
                    'transfer_pair_id' => null,
                    'bill_id' => $billId,
                ]);
            }
        }

        // --- Dining out / food — several per week on credit card ---
        $diningPayees = [$payees["McDonald's"], $payees['KFC'], $payees['Starbucks']];
        $diningAmounts = [
            ["McDonald's", 22.50],
            ["McDonald's", 18.90],
            ['KFC', 28.00],
            ['Starbucks', 15.00],
            ['Starbucks', 17.50],
        ];

        for ($daysBack = 1; $daysBack <= 90; $daysBack += 3) {
            $txDate = $today->subDays($daysBack)->toDateString();
            $entry = $diningAmounts[array_rand($diningAmounts)];
            Transaction::create([
                'ledger_id' => $ledger->id,
                'account_id' => $cimb->id,
                'category_id' => $expenseSubCats['Food & Drinks'][1]->id, // Dining Out
                'payee_id' => $payees[$entry[0]]->id,
                'transaction_type' => TransactionType::Expense,
                'amount' => -$entry[1],
                'description' => 'Dining out',
                'notes' => null,
                'transaction_date' => $txDate,
                'transfer_pair_id' => null,
            ]);
        }

        // --- Coffee / breakfast ---
        for ($daysBack = 1; $daysBack <= 90; $daysBack += 2) {
            if ($daysBack % 4 === 0) {
                continue;
            }
            $txDate = $today->subDays($daysBack)->toDateString();
            Transaction::create([
                'ledger_id' => $ledger->id,
                'account_id' => $tng->id,
                'category_id' => $expenseSubCats['Food & Drinks'][2]->id, // Coffee & Tea
                'payee_id' => $payees['Starbucks']->id,
                'transaction_type' => TransactionType::Expense,
                'amount' => -round(mt_rand(1000, 1800) / 100, 2),
                'description' => 'Coffee',
                'notes' => null,
                'transaction_date' => $txDate,
                'transfer_pair_id' => null,
            ]);
        }

        // --- Grab rides ---
        for ($daysBack = 2; $daysBack <= 90; $daysBack += 4) {
            $txDate = $today->subDays($daysBack)->toDateString();
            Transaction::create([
                'ledger_id' => $ledger->id,
                'account_id' => $tng->id,
                'category_id' => $expenseSubCats['Transport'][0]->id, // Grab / Ride-hailing
                'payee_id' => $payees['Grab']->id,
                'transaction_type' => TransactionType::Expense,
                'amount' => -round(mt_rand(1500, 5500) / 100, 2),
                'description' => 'Grab ride',
                'notes' => null,
                'transaction_date' => $txDate,
                'transfer_pair_id' => null,
            ]);
        }

        // --- Petrol (Petronas) ---
        for ($daysBack = 7; $daysBack <= 90; $daysBack += 10) {
            $txDate = $today->subDays($daysBack)->toDateString();
            Transaction::create([
                'ledger_id' => $ledger->id,
                'account_id' => $maybank->id,
                'category_id' => $expenseSubCats['Transport'][1]->id, // Fuel
                'payee_id' => $payees['Petronas']->id,
                'transaction_type' => TransactionType::Expense,
                'amount' => -round(mt_rand(6000, 12000) / 100, 2),
                'description' => 'Petrol',
                'notes' => null,
                'transaction_date' => $txDate,
                'transfer_pair_id' => null,
            ]);
        }

        // --- Online shopping (Shopee / Lazada) on credit card ---
        $shoppingItems = [
            ['Shopee', 'Online purchase', 45.00, 200.00],
            ['Lazada', 'Online purchase', 30.00, 180.00],
            ['Uniqlo', 'Clothing', 89.90, 249.00],
        ];

        for ($daysBack = 5; $daysBack <= 90; $daysBack += 12) {
            $item = $shoppingItems[array_rand($shoppingItems)];
            $txDate = $today->subDays($daysBack)->toDateString();
            Transaction::create([
                'ledger_id' => $ledger->id,
                'account_id' => $cimb->id,
                'category_id' => $expenseSubCats['Shopping'][2]->id, // Online Shopping
                'payee_id' => $payees[$item[0]]->id,
                'transaction_type' => TransactionType::Expense,
                'amount' => -round(mt_rand((int) ($item[2] * 100), (int) ($item[3] * 100)) / 100, 2),
                'description' => $item[1],
                'notes' => null,
                'transaction_date' => $txDate,
                'transfer_pair_id' => null,
            ]);
        }

        // --- Pharmacy / health ---
        for ($daysBack = 10; $daysBack <= 90; $daysBack += 22) {
            $txDate = $today->subDays($daysBack)->toDateString();
            Transaction::create([
                'ledger_id' => $ledger->id,
                'account_id' => $cimb->id,
                'category_id' => $expenseSubCats['Health'][0]->id, // Pharmacy
                'payee_id' => $payees['Watsons']->id,
                'transaction_type' => TransactionType::Expense,
                'amount' => -round(mt_rand(2000, 8000) / 100, 2),
                'description' => 'Pharmacy',
                'notes' => null,
                'transaction_date' => $txDate,
                'transfer_pair_id' => null,
            ]);
        }

        // --- Cash spending (food, small purchases) ---
        for ($daysBack = 3; $daysBack <= 90; $daysBack += 7) {
            $txDate = $today->subDays($daysBack)->toDateString();
            Transaction::create([
                'ledger_id' => $ledger->id,
                'account_id' => $cash->id,
                'category_id' => $expenseSubCats['Food & Drinks'][3]->id, // Snacks
                'payee_id' => null,
                'transaction_type' => TransactionType::Expense,
                'amount' => -round(mt_rand(500, 2500) / 100, 2),
                'description' => 'Cash purchase',
                'notes' => null,
                'transaction_date' => $txDate,
                'transfer_pair_id' => null,
            ]);
        }

        // --- Cashback / freelance income ---
        $freelanceDates = [
            $today->subMonths(2)->addDays(15)->toDateString(),
            $today->subMonths(1)->addDays(10)->toDateString(),
        ];

        foreach ($freelanceDates as $flDate) {
            Transaction::create([
                'ledger_id' => $ledger->id,
                'account_id' => $maybank->id,
                'category_id' => $incomeSubCats['Freelance'][0]->id, // Project Payment
                'payee_id' => null,
                'transaction_type' => TransactionType::Income,
                'amount' => round(mt_rand(50000, 150000) / 100, 2),
                'description' => 'Freelance project payment',
                'notes' => null,
                'transaction_date' => $flDate,
                'transfer_pair_id' => null,
            ]);
        }

        // --- Shopee / AEON physical shopping ---
        for ($daysBack = 15; $daysBack <= 90; $daysBack += 20) {
            $txDate = $today->subDays($daysBack)->toDateString();
            Transaction::create([
                'ledger_id' => $ledger->id,
                'account_id' => $cimb->id,
                'category_id' => $expenseSubCats['Shopping'][3]->id, // Home Goods
                'payee_id' => $payees['AEON']->id,
                'transaction_type' => TransactionType::Expense,
                'amount' => -round(mt_rand(5000, 20000) / 100, 2),
                'description' => 'AEON shopping',
                'notes' => null,
                'transaction_date' => $txDate,
                'transfer_pair_id' => null,
            ]);
        }

        // --- Personal care ---
        for ($daysBack = 20; $daysBack <= 90; $daysBack += 30) {
            $txDate = $today->subDays($daysBack)->toDateString();
            Transaction::create([
                'ledger_id' => $ledger->id,
                'account_id' => $cash->id,
                'category_id' => $expenseSubCats['Personal Care'][0]->id, // Haircut
                'payee_id' => null,
                'transaction_type' => TransactionType::Expense,
                'amount' => -25.00,
                'description' => 'Haircut',
                'notes' => null,
                'transaction_date' => $txDate,
                'transfer_pair_id' => null,
            ]);
        }

        // --- Guardian (skincare / personal care) ---
        for ($daysBack = 25; $daysBack <= 90; $daysBack += 30) {
            $txDate = $today->subDays($daysBack)->toDateString();
            Transaction::create([
                'ledger_id' => $ledger->id,
                'account_id' => $cimb->id,
                'category_id' => $expenseSubCats['Personal Care'][1]->id, // Skincare
                'payee_id' => $payees['Guardian']->id,
                'transaction_type' => TransactionType::Expense,
                'amount' => -round(mt_rand(3000, 9000) / 100, 2),
                'description' => 'Skincare',
                'notes' => null,
                'transaction_date' => $txDate,
                'transfer_pair_id' => null,
            ]);
        }

        // --- Investment returns (monthly) ---
        for ($m = 2; $m >= 0; $m--) {
            $divDate = $today->subMonths($m)->startOfMonth()->addDays(12)->toDateString();
            Transaction::create([
                'ledger_id' => $ledger->id,
                'account_id' => $maybank->id,
                'category_id' => $incomeSubCats['Investment Returns'][0]->id, // Dividends
                'payee_id' => null,
                'transaction_type' => TransactionType::Income,
                'amount' => round(mt_rand(5000, 15000) / 100, 2),
                'description' => 'Dividend income',
                'notes' => null,
                'transaction_date' => $divDate,
                'transfer_pair_id' => null,
            ]);
        }

        // --- Toll ---
        for ($daysBack = 6; $daysBack <= 90; $daysBack += 8) {
            $txDate = $today->subDays($daysBack)->toDateString();
            Transaction::create([
                'ledger_id' => $ledger->id,
                'account_id' => $tng->id,
                'category_id' => $expenseSubCats['Transport'][2]->id, // Toll
                'payee_id' => null,
                'transaction_type' => TransactionType::Expense,
                'amount' => -round(mt_rand(200, 800) / 100, 2),
                'description' => 'Toll',
                'notes' => null,
                'transaction_date' => $txDate,
                'transfer_pair_id' => null,
            ]);
        }
    }

    private function seedWeeklyExpenses(
        Ledger $ledger,
        Account $account,
        Category $category,
        Payee $payee,
        string $description,
        float $minAmount,
        float $maxAmount,
        CarbonImmutable $today
    ): void {
        for ($daysBack = 4; $daysBack <= 90; $daysBack += 7) {
            $txDate = $today->subDays($daysBack)->toDateString();
            Transaction::create([
                'ledger_id' => $ledger->id,
                'account_id' => $account->id,
                'category_id' => $category->id,
                'payee_id' => $payee->id,
                'transaction_type' => TransactionType::Expense,
                'amount' => -round(mt_rand((int) ($minAmount * 100), (int) ($maxAmount * 100)) / 100, 2),
                'description' => $description,
                'notes' => null,
                'transaction_date' => $txDate,
                'transfer_pair_id' => null,
            ]);
        }
    }
}
