<?php

use App\Actions\Bills\Queries\GetBillMissedCyclesQuery;
use App\Actions\Bills\Queries\ListUpcomingBillsQuery;
use App\Actions\Bills\UseCases\PayBillAction;
use App\Actions\Bills\UseCases\ProcessAutoBillsAction;
use App\Actions\Bills\UseCases\StoreBillAction;
use App\Actions\Bills\UseCases\UpdateBillAction;
use App\Data\Bills\Input\PayBillData;
use App\Data\Bills\Input\StoreBillData;
use App\Data\Bills\Input\UpdateBillData;
use App\Enums\RecurrenceType;
use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\AccountType;
use App\Models\Bill;
use App\Models\Ledger;
use App\Models\Transaction;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

function makeLedgerWithAccount(): array
{
    $ledger = Ledger::factory()->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create();

    return [$ledger, $account];
}

test('store bill action can store a bill', function () {
    [$ledger, $account] = makeLedgerWithAccount();

    $bill = app(StoreBillAction::class)(new StoreBillData(
        name: 'Rent',
        transaction_type: TransactionType::Expense->value,
        amount: 1200.00,
        account_id: $account->id,
        to_account_id: null,
        category_id: null,
        payee_id: null,
        new_payee_name: null,
        recurrence_type: RecurrenceType::Monthly->value,
        recurrence_interval: 1,
        recurrence_day: null,
        next_due_date: '2026-04-01',
        auto_create: false,
        end_type: null,
        end_date: null,
        end_after_occurrences: null,
        ledger: $ledger,
    ));

    expect($bill)->toBeInstanceOf(Bill::class)
        ->and($bill->name)->toBe('Rent')
        ->and($bill->ledger_id)->toBe($ledger->id)
        ->and($bill->account_id)->toBe($account->id)
        ->and((string) $bill->amount)->toBe('1200.00');
});

test('store bill action can store a transfer bill', function () {
    [$ledger, $fromAccount] = makeLedgerWithAccount();
    $toAccount = Account::factory()->for($ledger)->create();

    $bill = app(StoreBillAction::class)(new StoreBillData(
        name: 'Savings transfer',
        transaction_type: TransactionType::Transfer->value,
        amount: 250.00,
        account_id: $fromAccount->id,
        to_account_id: $toAccount->id,
        category_id: null,
        payee_id: null,
        new_payee_name: null,
        recurrence_type: RecurrenceType::Monthly->value,
        recurrence_interval: 1,
        recurrence_day: null,
        next_due_date: '2026-04-01',
        auto_create: false,
        end_type: null,
        end_date: null,
        end_after_occurrences: null,
        ledger: $ledger,
    ));

    expect($bill)->toBeInstanceOf(Bill::class)
        ->and($bill->transaction_type->value)->toBe('transfer')
        ->and($bill->account_id)->toBe($fromAccount->id)
        ->and($bill->to_account_id)->toBe($toAccount->id)
        ->and((string) $bill->amount)->toBe('250.00');
});

test('update bill action can update a bill', function () {
    [$ledger, $account] = makeLedgerWithAccount();

    $bill = Bill::factory()->for($ledger)->for($account)->create([
        'name' => 'Old Name',
        'amount' => 100.00,
    ]);

    app()->instance('request', Request::create('/_tests/bills/'.$bill->id, 'PATCH', [
        'name' => 'New Name',
        'amount' => 200.00,
    ]));

    $updated = app(UpdateBillAction::class)(new UpdateBillData(
        ledger: $ledger,
        bill: $bill,
        name: 'New Name',
        amount: 200.00,
    ));

    expect($updated->name)->toBe('New Name')
        ->and((string) $updated->amount)->toBe('200.00');
});

test('pay bill action creates transaction', function () {
    [$ledger, $account] = makeLedgerWithAccount();

    $bill = Bill::factory()->for($ledger)->for($account)->create([
        'amount' => 150.00,
        'name' => 'Electricity',
        'next_due_date' => CarbonImmutable::today(),
    ]);

    $transaction = app(PayBillAction::class)(new PayBillData(
        ledger: $ledger,
        bill: $bill,
    ));

    expect($transaction)->toBeInstanceOf(Transaction::class)
        ->and((string) $transaction->amount)->toBe('-150.00')
        ->and($transaction->account_id)->toBe($account->id)
        ->and($transaction->description)->toBe('Electricity')
        ->and($transaction->transaction_date->toDateString())->toBe(CarbonImmutable::today()->toDateString());
});

test('pay bill action creates paired transactions for transfer bills', function () {
    [$ledger, $fromAccount] = makeLedgerWithAccount();
    $toAccount = Account::factory()->for($ledger)->create();

    $bill = Bill::factory()->for($ledger)->for($fromAccount)->create([
        'transaction_type' => 'transfer',
        'to_account_id' => $toAccount->id,
        'amount' => 150.00,
        'name' => 'Savings transfer',
        'next_due_date' => CarbonImmutable::today(),
    ]);

    $transaction = app(PayBillAction::class)(new PayBillData(
        ledger: $ledger,
        bill: $bill,
    ));

    $transactions = Transaction::query()
        ->where('bill_id', $bill->id)
        ->orderBy('amount')
        ->get();

    expect($transaction)->toBeInstanceOf(Transaction::class)
        ->and($transactions)->toHaveCount(2)
        ->and((string) $transactions[0]->amount)->toBe('-150.00')
        ->and((string) $transactions[1]->amount)->toBe('150.00')
        ->and($transactions[0]->account_id)->toBe($fromAccount->id)
        ->and($transactions[1]->account_id)->toBe($toAccount->id)
        ->and($transactions[0]->transfer_pair_id)->not->toBeNull()
        ->and($transactions[0]->transfer_pair_id)->toBe($transactions[1]->transfer_pair_id);
});

test('pay bill action advances the next due date', function () {
    [$ledger, $account] = makeLedgerWithAccount();

    $bill = Bill::factory()->for($ledger)->for($account)->create([
        'next_due_date' => CarbonImmutable::today(),
        'recurrence_type' => RecurrenceType::Monthly,
        'recurrence_interval' => 1,
    ]);

    $originalDue = $bill->next_due_date->toDateString();

    app(PayBillAction::class)(new PayBillData(
        ledger: $ledger,
        bill: $bill,
    ));

    $bill->refresh();

    expect($bill->next_due_date->toDateString())->not->toBe($originalDue);
});

test('pay bill action deactivates a bill when its occurrence limit is reached', function () {
    [$ledger, $account] = makeLedgerWithAccount();

    $bill = Bill::factory()->for($ledger)->for($account)->create([
        'next_due_date' => CarbonImmutable::today(),
        'end_type' => Bill::END_TYPE_AFTER_OCCURRENCES,
        'end_after_occurrences' => 1,
        'occurrences_count' => 0,
    ]);

    app(PayBillAction::class)(new PayBillData(
        ledger: $ledger,
        bill: $bill,
    ));

    $bill->refresh();

    expect($bill->is_active)->toBeFalse();
});

test('get bill missed cycles query returns 0 when the bill is not overdue', function () {
    [$ledger, $account] = makeLedgerWithAccount();

    $bill = Bill::factory()->for($ledger)->for($account)->create([
        'next_due_date' => CarbonImmutable::today(),
        'recurrence_type' => RecurrenceType::Monthly,
        'recurrence_interval' => 1,
    ]);

    $count = app(GetBillMissedCyclesQuery::class)($bill);

    expect($count)->toBe(0);
});

test('get bill missed cycles query returns the correct count when overdue', function () {
    [$ledger, $account] = makeLedgerWithAccount();

    $bill = Bill::factory()->for($ledger)->for($account)->create([
        'next_due_date' => CarbonImmutable::today()->subMonths(2),
        'recurrence_type' => RecurrenceType::Monthly,
        'recurrence_interval' => 1,
    ]);

    $count = app(GetBillMissedCyclesQuery::class)($bill);

    expect($count)->toBe(2);
});

test('process auto bills action creates transactions for due bills', function () {
    [$ledger, $account] = makeLedgerWithAccount();

    $bill = Bill::factory()->for($ledger)->for($account)->create([
        'auto_create' => true,
        'next_due_date' => CarbonImmutable::today(),
        'recurrence_type' => RecurrenceType::Monthly,
        'recurrence_interval' => 1,
        'amount' => 75.00,
    ]);

    $processAutoBills = app(ProcessAutoBillsAction::class);
    $processAutoBills();

    expect(Transaction::query()->where('description', $bill->name)->count())->toBe(1);
});

test('process auto bills action skips processing while another run holds the lock', function () {
    [$ledger, $account] = makeLedgerWithAccount();

    $bill = Bill::factory()->for($ledger)->for($account)->create([
        'auto_create' => true,
        'next_due_date' => CarbonImmutable::today(),
        'recurrence_type' => RecurrenceType::Monthly,
        'recurrence_interval' => 1,
        'amount' => 75.00,
    ]);

    $lock = Cache::lock('bills:process-auto', 300);

    expect($lock->get())->toBeTrue();

    try {
        $processAutoBills = app(ProcessAutoBillsAction::class);
        $processAutoBills();
    } finally {
        $lock->release();
    }

    expect(Transaction::query()->where('bill_id', $bill->id)->count())->toBe(0)
        ->and($bill->fresh()->next_due_date->isSameDay(CarbonImmutable::today()))->toBeTrue();
});

test('process auto bills action creates paired transactions for due transfer bills', function () {
    [$ledger, $fromAccount] = makeLedgerWithAccount();
    $toAccount = Account::factory()->for($ledger)->create();

    $bill = Bill::factory()->for($ledger)->for($fromAccount)->create([
        'transaction_type' => 'transfer',
        'to_account_id' => $toAccount->id,
        'auto_create' => true,
        'next_due_date' => CarbonImmutable::today(),
        'recurrence_type' => RecurrenceType::Monthly,
        'recurrence_interval' => 1,
        'amount' => 75.00,
    ]);

    $processAutoBills = app(ProcessAutoBillsAction::class);
    $processAutoBills();

    $transactions = Transaction::query()
        ->where('bill_id', $bill->id)
        ->orderBy('amount')
        ->get();

    expect($transactions)->toHaveCount(2)
        ->and((string) $transactions[0]->amount)->toBe('-75.00')
        ->and((string) $transactions[1]->amount)->toBe('75.00');
});

test('process auto bills action handles multiple missed cycles', function () {
    [$ledger, $account] = makeLedgerWithAccount();

    $bill = Bill::factory()->for($ledger)->for($account)->create([
        'auto_create' => true,
        'next_due_date' => CarbonImmutable::today()->subMonths(3),
        'recurrence_type' => RecurrenceType::Monthly,
        'recurrence_interval' => 1,
        'amount' => 50.00,
    ]);

    $processAutoBills = app(ProcessAutoBillsAction::class);
    $processAutoBills();

    expect(Transaction::query()->where('description', $bill->name)->count())->toBe(3);
});

test('process auto bills action skips non-auto bills', function () {
    [$ledger, $account] = makeLedgerWithAccount();

    $bill = Bill::factory()->for($ledger)->for($account)->create([
        'auto_create' => false,
        'next_due_date' => CarbonImmutable::today(),
        'recurrence_type' => RecurrenceType::Monthly,
    ]);

    $processAutoBills = app(ProcessAutoBillsAction::class);
    $processAutoBills();

    expect(Transaction::query()->where('description', $bill->name)->count())->toBe(0);
});

test('process auto bills action deactivates expired bills', function () {
    [$ledger, $account] = makeLedgerWithAccount();

    $bill = Bill::factory()->for($ledger)->for($account)->create([
        'auto_create' => true,
        'next_due_date' => CarbonImmutable::today(),
        'end_type' => Bill::END_TYPE_AFTER_OCCURRENCES,
        'end_after_occurrences' => 1,
        'occurrences_count' => 0,
        'recurrence_type' => RecurrenceType::Monthly,
        'recurrence_interval' => 1,
    ]);

    $processAutoBills = app(ProcessAutoBillsAction::class);
    $processAutoBills();

    $bill->refresh();
    expect($bill->is_active)->toBeFalse();
});

test('list upcoming bills query returns the correct groups', function () {
    [$ledger, $account] = makeLedgerWithAccount();

    $due = Bill::factory()->for($ledger)->for($account)->create([
        'next_due_date' => CarbonImmutable::today(),
        'is_active' => true,
    ]);

    $upcoming = Bill::factory()->for($ledger)->for($account)->create([
        'next_due_date' => CarbonImmutable::today()->addDays(3),
        'is_active' => true,
    ]);

    $missed = Bill::factory()->for($ledger)->for($account)->create([
        'next_due_date' => CarbonImmutable::today()->subDays(5),
        'is_active' => true,
        'auto_create' => false,
    ]);

    // auto_create past-due should not appear in missed
    Bill::factory()->for($ledger)->for($account)->create([
        'next_due_date' => CarbonImmutable::today()->subDays(2),
        'is_active' => true,
        'auto_create' => true,
    ]);

    $result = app(ListUpcomingBillsQuery::class)($ledger, 30);

    expect(collect($result['due'])->pluck('id'))->toContain($due->id)
        ->and(collect($result['upcoming'])->pluck('id'))->toContain($upcoming->id)
        ->and(collect($result['missed'])->pluck('id'))->toContain($missed->id)
        ->and($result['missed'])->toHaveCount(1)
        ->and($result['due'])->toHaveCount(1)
        ->and($result['upcoming'])->toHaveCount(1);
});

test('pay bill action respects overrides', function () {
    [$ledger, $account] = makeLedgerWithAccount();

    $bill = Bill::factory()->for($ledger)->for($account)->create([
        'amount' => 150.00,
        'name' => 'Electricity',
        'next_due_date' => CarbonImmutable::today(),
    ]);

    $transaction = app(PayBillAction::class)(new PayBillData(
        amount: 100,
        date: '2026-01-01',
        ledger: $ledger,
        bill: $bill,
    ));

    expect((string) $transaction->amount)->toBe('-100.00')
        ->and($transaction->transaction_date->toDateString())->toBe('2026-01-01');
});

test('pay bill action deactivates a bill when its end date is reached', function () {
    [$ledger, $account] = makeLedgerWithAccount();

    $bill = Bill::factory()->for($ledger)->for($account)->create([
        'next_due_date' => CarbonImmutable::today(),
        'end_type' => Bill::END_TYPE_ON_DATE,
        'end_date' => CarbonImmutable::today(),
        'recurrence_type' => RecurrenceType::Monthly,
        'recurrence_interval' => 1,
    ]);

    app(PayBillAction::class)(new PayBillData(
        ledger: $ledger,
        bill: $bill,
    ));

    $bill->refresh();

    expect($bill->is_active)->toBeFalse();
});

test('process auto bills action stops when an end condition is reached during multi-cycle catchup', function () {
    [$ledger, $account] = makeLedgerWithAccount();

    $bill = Bill::factory()->for($ledger)->for($account)->create([
        'auto_create' => true,
        'next_due_date' => CarbonImmutable::today()->subMonths(3),
        'recurrence_type' => RecurrenceType::Monthly,
        'recurrence_interval' => 1,
        'amount' => 50.00,
        'end_type' => Bill::END_TYPE_AFTER_OCCURRENCES,
        'end_after_occurrences' => 2,
        'occurrences_count' => 1,
    ]);

    $processAutoBills = app(ProcessAutoBillsAction::class);
    $processAutoBills();

    expect(Transaction::query()->where('description', $bill->name)->count())->toBe(1);
    $bill->refresh();
    expect($bill->is_active)->toBeFalse();
});
