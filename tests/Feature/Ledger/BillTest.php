<?php

use App\Enums\RecurrenceType;
use App\Models\Account;
use App\Models\AccountType;
use App\Models\Bill;
use App\Models\Category;
use App\Models\Ledger;
use App\Models\Payee;
use App\Models\User;
use Carbon\CarbonImmutable;

test('bill active scope returns only active bills', function () {
    $ledger = Ledger::factory()->create();

    $active = Bill::factory()->for($ledger)->create(['is_active' => true]);
    Bill::factory()->for($ledger)->create(['is_active' => false]);

    $results = Bill::query()->active()->get();

    expect($results)->toHaveCount(1);
    expect($results->first()->id)->toBe($active->id);
});

test('bill upcoming scope returns bills due within 7 days', function () {
    $ledger = Ledger::factory()->create();

    $upcoming = Bill::factory()->for($ledger)->create([
        'next_due_date' => CarbonImmutable::today()->addDays(3),
    ]);
    Bill::factory()->for($ledger)->create([
        'next_due_date' => CarbonImmutable::today()->addDays(10),
    ]);
    Bill::factory()->for($ledger)->create([
        'next_due_date' => CarbonImmutable::today()->subDay(),
    ]);

    $results = Bill::query()->upcoming()->get();

    expect($results)->toHaveCount(1);
    expect($results->first()->id)->toBe($upcoming->id);
});

test('bill due scope returns bills due today', function () {
    $ledger = Ledger::factory()->create();

    $dueToday = Bill::factory()->for($ledger)->create([
        'next_due_date' => CarbonImmutable::today(),
    ]);
    Bill::factory()->for($ledger)->create([
        'next_due_date' => CarbonImmutable::today()->addDay(),
    ]);

    $results = Bill::query()->due()->get();

    expect($results)->toHaveCount(1);
    expect($results->first()->id)->toBe($dueToday->id);
});

test('bill missed scope returns overdue non-auto bills', function () {
    $ledger = Ledger::factory()->create();

    $missed = Bill::factory()->for($ledger)->create([
        'next_due_date' => CarbonImmutable::today()->subDay(),
        'auto_create' => false,
    ]);
    // Auto-create bills are not "missed"
    Bill::factory()->for($ledger)->create([
        'next_due_date' => CarbonImmutable::today()->subDay(),
        'auto_create' => true,
    ]);
    // Future bills are not missed
    Bill::factory()->for($ledger)->create([
        'next_due_date' => CarbonImmutable::today()->addDay(),
        'auto_create' => false,
    ]);

    $results = Bill::query()->missed()->get();

    expect($results)->toHaveCount(1);
    expect($results->first()->id)->toBe($missed->id);
});

test('bill hasReachedEnd returns true when end date passed', function () {
    $ledger = Ledger::factory()->create();

    $bill = Bill::factory()->for($ledger)->create([
        'end_type' => Bill::END_TYPE_ON_DATE,
        'end_date' => CarbonImmutable::today()->subDay(),
    ]);

    expect($bill->hasReachedEnd())->toBeTrue();
});

test('bill hasReachedEnd returns true when occurrences exhausted', function () {
    $ledger = Ledger::factory()->create();

    $bill = Bill::factory()->for($ledger)->create([
        'end_type' => Bill::END_TYPE_AFTER_OCCURRENCES,
        'end_after_occurrences' => 5,
        'occurrences_count' => 5,
    ]);

    expect($bill->hasReachedEnd())->toBeTrue();
});

test('bill nextDueDateAfter advances by correct interval for monthly', function () {
    $ledger = Ledger::factory()->create();

    $bill = Bill::factory()->for($ledger)->create([
        'recurrence_type' => RecurrenceType::Monthly,
        'recurrence_interval' => 1,
        'recurrence_day' => null,
    ]);

    $from = CarbonImmutable::parse('2024-03-15');
    $next = $bill->nextDueDateAfter($from);

    expect($next->toDateString())->toBe('2024-04-15');
});

test('bill nextDueDateAfter advances by correct interval for weekly', function () {
    $ledger = Ledger::factory()->create();

    $bill = Bill::factory()->for($ledger)->create([
        'recurrence_type' => RecurrenceType::Weekly,
        'recurrence_interval' => 2,
        'recurrence_day' => null,
    ]);

    $from = CarbonImmutable::parse('2024-03-15');
    $next = $bill->nextDueDateAfter($from);

    expect($next->toDateString())->toBe('2024-03-29');
});

test('bill nextDueDateAfter snaps to recurrence_day for monthly', function () {
    $ledger = Ledger::factory()->create();

    $bill = Bill::factory()->for($ledger)->create([
        'recurrence_type' => RecurrenceType::Monthly,
        'recurrence_interval' => 1,
        'recurrence_day' => 15,
    ]);

    $from = CarbonImmutable::parse('2024-03-01');
    $next = $bill->nextDueDateAfter($from);

    expect($next->toDateString())->toBe('2024-04-15');
});

test('bill nextDueDateAfter advances by correct interval for daily', function () {
    $ledger = Ledger::factory()->create();

    $bill = Bill::factory()->for($ledger)->create([
        'recurrence_type' => RecurrenceType::Daily,
        'recurrence_interval' => 3,
        'recurrence_day' => null,
    ]);

    $from = CarbonImmutable::parse('2024-03-15');
    $next = $bill->nextDueDateAfter($from);

    expect($next->toDateString())->toBe('2024-03-18');
});

test('bill nextDueDateAfter advances by correct interval for yearly', function () {
    $ledger = Ledger::factory()->create();

    $bill = Bill::factory()->for($ledger)->create([
        'recurrence_type' => RecurrenceType::Yearly,
        'recurrence_interval' => 2,
        'recurrence_day' => null,
    ]);

    $from = CarbonImmutable::parse('2024-03-15');
    $next = $bill->nextDueDateAfter($from);

    expect($next->toDateString())->toBe('2026-03-15');
});

test('bill hasReachedEnd returns false when end_type is null', function () {
    $ledger = Ledger::factory()->create();

    $bill = Bill::factory()->for($ledger)->create([
        'end_type' => null,
    ]);

    expect($bill->hasReachedEnd())->toBeFalse();
});

test('bill upcoming scope excludes a bill due exactly today', function () {
    $ledger = Ledger::factory()->create();

    $dueToday = Bill::factory()->for($ledger)->create([
        'next_due_date' => CarbonImmutable::today(),
    ]);

    $results = Bill::query()->upcoming()->get();

    expect($results->contains($dueToday))->toBeFalse();
});

test('bill nextDueDateAfter handles recurrence_day greater than target month length', function () {
    $ledger = Ledger::factory()->create();

    $bill = Bill::factory()->for($ledger)->create([
        'recurrence_type' => RecurrenceType::Monthly,
        'recurrence_interval' => 1,
        'recurrence_day' => 31,
    ]);

    // Jan 15 + 1 month = Feb, which has 28/29 days — must not overflow into March
    $from = CarbonImmutable::parse('2024-01-15');
    $next = $bill->nextDueDateAfter($from);

    expect($next->month)->toBe(2);
    expect($next->toDateString())->toBe('2024-02-29'); // 2024 is a leap year
});

test('bill index renders successfully', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create();

    Bill::factory()->for($ledger)->for($account)->create(['name' => 'Rent']);
    Bill::factory()->for($ledger)->for($account)->create(['name' => 'Internet']);

    $response = $this
        ->actingAs($user)
        ->get(route('ledgers.bills.index', $ledger));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('ledgers/bills/index')
    );
});

test('bill store creates a bill via HTTP', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create();

    $response = $this
        ->actingAs($user)
        ->from(route('ledgers.bills.index', $ledger))
        ->post(route('ledgers.bills.store', $ledger), [
            'name' => 'Electricity',
            'transaction_type' => 'expense',
            'amount' => 120.00,
            'account_id' => $account->id,
            'category_id' => null,
            'payee_id' => null,
            'recurrence_type' => 'monthly',
            'recurrence_interval' => 1,
            'recurrence_day' => null,
            'next_due_date' => '2026-04-01',
            'auto_create' => false,
            'end_type' => null,
            'end_date' => null,
            'end_after_occurrences' => null,
        ]);

    $response->assertRedirect(route('ledgers.bills.index', $ledger))
        ->assertSessionHas('success', 'Recurring transaction created.');

    expect($ledger->bills()->where('name', 'Electricity')->exists())->toBeTrue();
});

test('bill store creates a recurring transfer via HTTP', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    $fromAccount = Account::factory()->for($ledger)->for($accountType)->create();
    $toAccount = Account::factory()->for($ledger)->for($accountType)->create();

    $response = $this
        ->actingAs($user)
        ->from(route('ledgers.bills.index', $ledger))
        ->post(route('ledgers.bills.store', $ledger), [
            'name' => 'Transfer to savings',
            'transaction_type' => 'transfer',
            'amount' => 120.00,
            'account_id' => $fromAccount->id,
            'to_account_id' => $toAccount->id,
            'category_id' => null,
            'payee_id' => null,
            'recurrence_type' => 'monthly',
            'recurrence_interval' => 1,
            'recurrence_day' => null,
            'next_due_date' => '2026-04-01',
            'auto_create' => false,
            'end_type' => null,
            'end_date' => null,
            'end_after_occurrences' => null,
        ]);

    $response->assertRedirect(route('ledgers.bills.index', $ledger))
        ->assertSessionHas('success', 'Recurring transaction created.');

    $bill = $ledger->bills()->where('name', 'Transfer to savings')->first();

    expect($bill)->not->toBeNull()
        ->and($bill->transaction_type->value)->toBe('transfer')
        ->and($bill->account_id)->toBe($fromAccount->id)
        ->and($bill->to_account_id)->toBe($toAccount->id);
});

test('bill store rejects cross ledger related ids', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    Account::factory()->for($ledger)->for($accountType)->create();

    $foreignLedger = Ledger::factory()->create();
    $foreignAccountType = AccountType::factory()->for($foreignLedger)->create();
    $foreignAccount = Account::factory()->for($foreignLedger)->for($foreignAccountType)->create();
    $foreignCategory = Category::factory()->for($foreignLedger)->create();
    $foreignPayee = Payee::factory()->for($foreignLedger)->create();

    $response = $this->actingAs($user)
        ->from(route('ledgers.bills.index', $ledger))
        ->post(route('ledgers.bills.store', $ledger), [
            'name' => 'Electricity',
            'transaction_type' => 'expense',
            'amount' => 120.00,
            'account_id' => $foreignAccount->id,
            'category_id' => $foreignCategory->id,
            'payee_id' => $foreignPayee->id,
            'recurrence_type' => 'monthly',
            'recurrence_interval' => 1,
            'recurrence_day' => null,
            'next_due_date' => '2026-04-01',
            'auto_create' => false,
            'end_type' => null,
            'end_date' => null,
            'end_after_occurrences' => null,
        ]);

    $response->assertRedirect(route('ledgers.bills.index', $ledger))
        ->assertSessionHasErrors(['account_id', 'category_id', 'payee_id']);
});

test('bill pay creates a transaction and advances next due date', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create();

    $bill = Bill::factory()->for($ledger)->for($account)->create([
        'name' => 'Water Bill',
        'amount' => 50.00,
        'recurrence_type' => RecurrenceType::Monthly,
        'recurrence_interval' => 1,
        'next_due_date' => CarbonImmutable::today(),
        'is_active' => true,
    ]);

    $response = $this
        ->actingAs($user)
        ->from(route('ledgers.bills.index', $ledger))
        ->post(route('ledgers.bills.pay', [$ledger, $bill]));

    $response->assertRedirect();

    expect($ledger->transactions()->count())->toBe(1);
    expect($bill->fresh()->next_due_date->toDateString())
        ->toBe(CarbonImmutable::today()->addMonth()->toDateString());
});

test('bill pay uses edited amount override when creating transaction', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create();

    $bill = Bill::factory()->for($ledger)->for($account)->create([
        'name' => 'Water Bill',
        'amount' => 50.00,
        'recurrence_type' => RecurrenceType::Monthly,
        'recurrence_interval' => 1,
        'next_due_date' => CarbonImmutable::today(),
        'is_active' => true,
    ]);

    $response = $this
        ->actingAs($user)
        ->from(route('ledgers.bills.index', $ledger))
        ->post(route('ledgers.bills.pay', [$ledger, $bill]), [
            'amount' => 72.35,
        ]);

    $response->assertRedirect();

    $transaction = $ledger->transactions()->latest('id')->first();

    expect($transaction)->not->toBeNull()
        ->and((string) $transaction->amount)->toBe('-72.35');
});

test('bill pay creates paired transactions for a transfer bill', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    $fromAccount = Account::factory()->for($ledger)->for($accountType)->create();
    $toAccount = Account::factory()->for($ledger)->for($accountType)->create();

    $bill = Bill::factory()->for($ledger)->for($fromAccount)->create([
        'name' => 'Transfer to savings',
        'transaction_type' => 'transfer',
        'to_account_id' => $toAccount->id,
        'amount' => 50.00,
        'recurrence_type' => RecurrenceType::Monthly,
        'recurrence_interval' => 1,
        'next_due_date' => CarbonImmutable::today(),
        'is_active' => true,
    ]);

    $response = $this
        ->actingAs($user)
        ->from(route('ledgers.bills.index', $ledger))
        ->post(route('ledgers.bills.pay', [$ledger, $bill]));

    $response->assertRedirect();

    $transactions = $ledger->transactions()
        ->where('bill_id', $bill->id)
        ->orderBy('amount')
        ->get();

    expect($transactions)->toHaveCount(2)
        ->and((string) $transactions[0]->amount)->toBe('-50.00')
        ->and((string) $transactions[1]->amount)->toBe('50.00')
        ->and($transactions[0]->account_id)->toBe($fromAccount->id)
        ->and($transactions[1]->account_id)->toBe($toAccount->id)
        ->and($transactions[0]->transfer_pair_id)->toBe($transactions[1]->transfer_pair_id);
});

test('bill toggle toggles the is_active flag', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create();

    $bill = Bill::factory()->for($ledger)->for($account)->create(['is_active' => true]);

    $response = $this
        ->actingAs($user)
        ->from(route('ledgers.bills.index', $ledger))
        ->patch(route('ledgers.bills.toggle', [$ledger, $bill]));

    $response->assertRedirect(route('ledgers.bills.index', $ledger))
        ->assertSessionHas('success', 'Recurring transaction deactivated.');

    expect($bill->fresh()->is_active)->toBeFalse();

    $this
        ->actingAs($user)
        ->from(route('ledgers.bills.index', $ledger))
        ->patch(route('ledgers.bills.toggle', [$ledger, $bill]))
        ->assertRedirect(route('ledgers.bills.index', $ledger))
        ->assertSessionHas('success', 'Recurring transaction activated.');

    expect($bill->fresh()->is_active)->toBeTrue();
});
