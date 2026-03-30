<?php

use App\Models\Account;
use App\Models\AccountType;
use App\Models\Bill;
use App\Models\Ledger;
use App\Models\User;
use App\Notifications\BillDueReminder;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

test('check-reminders sends single summary notification for bills due within 3 days', function () {
    CarbonImmutable::setTestNow(CarbonImmutable::create(2026, 3, 15));

    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create();

    Bill::factory()->for($ledger)->for($account)->create([
        'name' => 'Netflix',
        'next_due_date' => CarbonImmutable::create(2026, 3, 17),
        'amount' => 15.99,
        'is_active' => true,
    ]);

    $this->artisan('bills:check-reminders')->assertSuccessful();

    expect($user->notifications()->count())->toBe(1);
    expect($user->notifications()->first()->data['type'])->toBe('bill_summary_reminder');
    expect($user->notifications()->first()->data['upcoming_count'])->toBe(1);
});

test('check-reminders sends single summary notification for overdue bills', function () {
    CarbonImmutable::setTestNow(CarbonImmutable::create(2026, 3, 15));

    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create();

    Bill::factory()->for($ledger)->for($account)->create([
        'name' => 'Electric',
        'next_due_date' => CarbonImmutable::create(2026, 3, 12),
        'amount' => 120.50,
        'is_active' => true,
    ]);

    $this->artisan('bills:check-reminders')->assertSuccessful();

    expect($user->notifications()->count())->toBe(1);
    expect($user->notifications()->first()->data['type'])->toBe('bill_summary_reminder');
    expect($user->notifications()->first()->data['overdue_count'])->toBe(1);
});

test('check-reminders sends single summary notification for due-today bills', function () {
    CarbonImmutable::setTestNow(CarbonImmutable::create(2026, 3, 15));

    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create();

    Bill::factory()->for($ledger)->for($account)->create([
        'name' => 'Internet',
        'next_due_date' => CarbonImmutable::create(2026, 3, 15),
        'amount' => 79.99,
        'is_active' => true,
    ]);

    $this->artisan('bills:check-reminders')->assertSuccessful();

    expect($user->notifications()->count())->toBe(1);
    expect($user->notifications()->first()->data['type'])->toBe('bill_summary_reminder');
    expect($user->notifications()->first()->data['due_today_count'])->toBe(1);
});

test('check-reminders sends single notification per user with multiple bills', function () {
    CarbonImmutable::setTestNow(CarbonImmutable::create(2026, 3, 15));

    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create();

    // Overdue bill
    Bill::factory()->for($ledger)->for($account)->create([
        'name' => 'Electric',
        'next_due_date' => CarbonImmutable::create(2026, 3, 12),
        'amount' => 120.50,
        'is_active' => true,
    ]);

    // Due today bill
    Bill::factory()->for($ledger)->for($account)->create([
        'name' => 'Internet',
        'next_due_date' => CarbonImmutable::create(2026, 3, 15),
        'amount' => 79.99,
        'is_active' => true,
    ]);

    // Upcoming bill
    Bill::factory()->for($ledger)->for($account)->create([
        'name' => 'Netflix',
        'next_due_date' => CarbonImmutable::create(2026, 3, 17),
        'amount' => 15.99,
        'is_active' => true,
    ]);

    $this->artisan('bills:check-reminders')->assertSuccessful();

    expect($user->notifications()->count())->toBe(1);
    expect($user->notifications()->first()->data['type'])->toBe('bill_summary_reminder');
    expect($user->notifications()->first()->data['overdue_count'])->toBe(1);
    expect($user->notifications()->first()->data['due_today_count'])->toBe(1);
    expect($user->notifications()->first()->data['upcoming_count'])->toBe(1);
    expect($user->notifications()->first()->data['total_bills'])->toBe(3);
});

test('check-reminders does not create duplicate summary notifications on same day', function () {
    CarbonImmutable::setTestNow(CarbonImmutable::create(2026, 3, 15));

    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create();

    Bill::factory()->for($ledger)->for($account)->create([
        'next_due_date' => CarbonImmutable::create(2026, 3, 17),
        'is_active' => true,
    ]);

    $this->artisan('bills:check-reminders')->assertSuccessful();
    $this->artisan('bills:check-reminders')->assertSuccessful();

    expect($user->notifications()->count())->toBe(1);
});

test('check-reminders skips inactive bills', function () {
    CarbonImmutable::setTestNow(CarbonImmutable::create(2026, 3, 15));

    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create();

    Bill::factory()->for($ledger)->for($account)->create([
        'next_due_date' => CarbonImmutable::create(2026, 3, 17),
        'is_active' => false,
    ]);

    $this->artisan('bills:check-reminders')->assertSuccessful();

    expect($user->notifications()->count())->toBe(0);
});

test('check-reminders sends separate notifications to different users', function () {
    CarbonImmutable::setTestNow(CarbonImmutable::create(2026, 3, 15));

    $user1 = User::factory()->create();
    $user2 = User::factory()->create();

    $ledger1 = Ledger::factory()->for($user1)->create();
    $ledger2 = Ledger::factory()->for($user2)->create();

    $accountType1 = AccountType::factory()->for($ledger1)->create();
    $accountType2 = AccountType::factory()->for($ledger2)->create();

    $account1 = Account::factory()->for($ledger1)->for($accountType1)->create();
    $account2 = Account::factory()->for($ledger2)->for($accountType2)->create();

    Bill::factory()->for($ledger1)->for($account1)->create([
        'next_due_date' => CarbonImmutable::create(2026, 3, 17),
        'is_active' => true,
    ]);

    Bill::factory()->for($ledger2)->for($account2)->create([
        'next_due_date' => CarbonImmutable::create(2026, 3, 18),
        'is_active' => true,
    ]);

    $this->artisan('bills:check-reminders')->assertSuccessful();

    expect($user1->notifications()->count())->toBe(1);
    expect($user2->notifications()->count())->toBe(1);
});

test('check-reminders batches summary notification lookups for multiple users', function () {
    CarbonImmutable::setTestNow(CarbonImmutable::create(2026, 3, 15));

    $users = User::factory()->count(3)->create();

    foreach ($users as $index => $user) {
        $ledger = Ledger::factory()->for($user)->create();
        $accountType = AccountType::factory()->for($ledger)->create();
        $account = Account::factory()->for($ledger)->for($accountType)->create();

        Bill::factory()->for($ledger)->for($account)->create([
            'name' => 'Bill '.$index,
            'next_due_date' => CarbonImmutable::create(2026, 3, 17),
            'amount' => 20 + $index,
            'is_active' => true,
        ]);

        $user->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => BillDueReminder::class,
            'data' => [
                'type' => 'bill_summary_reminder',
                'upcoming_count' => 1,
                'due_today_count' => 0,
                'overdue_count' => 0,
                'total_bills' => 1,
            ],
            'created_at' => CarbonImmutable::create(2026, 3, 15, 8),
            'updated_at' => CarbonImmutable::create(2026, 3, 15, 8),
        ]);
    }

    DB::flushQueryLog();
    DB::enableQueryLog();

    $this->artisan('bills:check-reminders')->assertSuccessful();

    $notificationQueries = collect(DB::getQueryLog())
        ->filter(function (array $query): bool {
            $sql = strtolower($query['query']);

            return str_starts_with($sql, 'select')
                && str_contains($sql, 'from "notifications"');
        });

    DB::disableQueryLog();

    expect($notificationQueries)->toHaveCount(1)
        ->and($users->sum(fn (User $user) => $user->notifications()->count()))->toBe(3);
});
