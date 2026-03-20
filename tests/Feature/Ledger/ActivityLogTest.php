<?php

use App\Models\Account;
use App\Models\AccountType;
use App\Models\ActivityLog;
use App\Models\Budget;
use App\Models\Category;
use App\Models\Ledger;
use App\Models\Transaction;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->ledger = Ledger::factory()->for($this->user)->create();
    $this->accountType = AccountType::factory()->for($this->ledger)->create();
    $this->account = Account::factory()
        ->for($this->ledger)
        ->for($this->accountType)
        ->create();
    $this->category = Category::factory()->for($this->ledger)->create();
});

test('creating a transaction logs activity with new values', function () {
    $this->actingAs($this->user);

    Transaction::factory()
        ->for($this->ledger)
        ->for($this->account)
        ->for($this->category)
        ->create([
            'description' => 'Groceries',
            'amount' => '-50.00',
            'transaction_date' => '2026-03-13',
        ]);

    $log = ActivityLog::query()
        ->where('ledger_id', $this->ledger->id)
        ->where('action', 'created')
        ->where('subject_type', Transaction::class)
        ->first();

    expect($log)->not->toBeNull()
        ->and($log->new_values)->toBeArray()
        ->and($log->new_values['description'])->toBe('Groceries')
        ->and($log->old_values)->toBeEmpty();
});

test('updating a transaction logs activity with old and new values', function () {
    $this->actingAs($this->user);

    $transaction = Transaction::factory()
        ->for($this->ledger)
        ->for($this->account)
        ->for($this->category)
        ->create([
            'description' => 'Groceries',
            'amount' => '-50.00',
        ]);

    $transaction->update(['description' => 'Supermarket']);

    $log = ActivityLog::query()
        ->where('ledger_id', $this->ledger->id)
        ->where('action', 'updated')
        ->where('subject_type', Transaction::class)
        ->first();

    expect($log)->not->toBeNull()
        ->and($log->old_values)->toHaveKey('description', 'Groceries')
        ->and($log->new_values)->toHaveKey('description', 'Supermarket');
});

test('updating a transaction only logs changed fields', function () {
    $this->actingAs($this->user);

    $transaction = Transaction::factory()
        ->for($this->ledger)
        ->for($this->account)
        ->for($this->category)
        ->create([
            'description' => 'Groceries',
            'amount' => '-50.00',
            'notes' => 'Weekly shop',
        ]);

    $transaction->update(['description' => 'Supermarket']);

    $log = ActivityLog::query()
        ->where('ledger_id', $this->ledger->id)
        ->where('action', 'updated')
        ->where('subject_type', Transaction::class)
        ->first();

    expect($log->old_values)->toHaveKey('description')
        ->and($log->old_values)->not->toHaveKey('notes')
        ->and($log->new_values)->toHaveKey('description')
        ->and($log->new_values)->not->toHaveKey('notes');
});

test('deleting a transaction logs activity', function () {
    $this->actingAs($this->user);

    $transaction = Transaction::factory()
        ->for($this->ledger)
        ->for($this->account)
        ->for($this->category)
        ->create(['description' => 'Coffee']);

    $transactionId = $transaction->id;
    $transaction->delete();

    $log = ActivityLog::query()
        ->where('ledger_id', $this->ledger->id)
        ->where('action', 'deleted')
        ->where('subject_type', Transaction::class)
        ->where('subject_id', $transactionId)
        ->first();

    expect($log)->not->toBeNull()
        ->and($log->old_values)->toBeArray()
        ->and($log->new_values)->toBeEmpty();
});

test('creating an account logs activity', function () {
    $this->actingAs($this->user);

    $account = Account::factory()
        ->for($this->ledger)
        ->for($this->accountType)
        ->create(['name' => 'Savings Account']);

    $log = ActivityLog::query()
        ->where('ledger_id', $this->ledger->id)
        ->where('action', 'created')
        ->where('subject_type', Account::class)
        ->where('subject_id', $account->id)
        ->first();

    expect($log)->not->toBeNull()
        ->and($log->new_values['name'])->toBe('Savings Account')
        ->and($log->old_values)->toBeEmpty();
});

test('creating a category logs activity', function () {
    $this->actingAs($this->user);

    $category = Category::factory()
        ->for($this->ledger)
        ->create(['name' => 'Utilities']);

    $log = ActivityLog::query()
        ->where('ledger_id', $this->ledger->id)
        ->where('action', 'created')
        ->where('subject_type', Category::class)
        ->where('subject_id', $category->id)
        ->first();

    expect($log)->not->toBeNull()
        ->and($log->new_values['name'])->toBe('Utilities')
        ->and($log->old_values)->toBeEmpty();
});

test('updating a category logs activity with changed fields only', function () {
    $this->actingAs($this->user);

    $category = Category::factory()
        ->for($this->ledger)
        ->create(['name' => 'Utilities', 'color' => '#ff0000']);

    $category->update(['name' => 'Bills & Utilities']);

    $log = ActivityLog::query()
        ->where('ledger_id', $this->ledger->id)
        ->where('action', 'updated')
        ->where('subject_type', Category::class)
        ->first();

    expect($log)->not->toBeNull()
        ->and($log->old_values)->toHaveKey('name', 'Utilities')
        ->and($log->new_values)->toHaveKey('name', 'Bills & Utilities')
        ->and($log->old_values)->not->toHaveKey('color')
        ->and($log->new_values)->not->toHaveKey('color');
});

test('deleting a category logs activity', function () {
    $this->actingAs($this->user);

    $category = Category::factory()
        ->for($this->ledger)
        ->create(['name' => 'Old Category']);

    $categoryId = $category->id;
    $category->delete();

    $log = ActivityLog::query()
        ->where('ledger_id', $this->ledger->id)
        ->where('action', 'deleted')
        ->where('subject_type', Category::class)
        ->where('subject_id', $categoryId)
        ->first();

    expect($log)->not->toBeNull();
});

test('creating a budget logs activity', function () {
    $this->actingAs($this->user);

    $budget = Budget::create([
        'ledger_id' => $this->ledger->id,
        'category_id' => $this->category->id,
        'amount' => 500.00,
        'period' => 'monthly',
        'start_date' => '2026-03-01',
        'is_active' => true,
        'rollover' => false,
    ]);

    $log = ActivityLog::query()
        ->where('ledger_id', $this->ledger->id)
        ->where('action', 'created')
        ->where('subject_type', Budget::class)
        ->where('subject_id', $budget->id)
        ->first();

    expect($log)->not->toBeNull()
        ->and($log->old_values)->toBeEmpty();
});

test('updating a budget logs activity with old and new values', function () {
    $this->actingAs($this->user);

    $budget = Budget::create([
        'ledger_id' => $this->ledger->id,
        'category_id' => $this->category->id,
        'amount' => 500.00,
        'period' => 'monthly',
        'start_date' => '2026-03-01',
        'is_active' => true,
        'rollover' => false,
    ]);

    $budget->update(['amount' => 750.00]);

    $log = ActivityLog::query()
        ->where('ledger_id', $this->ledger->id)
        ->where('action', 'updated')
        ->where('subject_type', Budget::class)
        ->first();

    expect($log)->not->toBeNull()
        ->and($log->old_values)->toHaveKey('amount')
        ->and($log->new_values)->toHaveKey('amount');
});

test('activity page renders successfully', function () {
    $this->withoutVite();
    $this->actingAs($this->user);

    $response = $this->get(route('ledgers.activity.index', $this->ledger))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('ledgers/activity/index')
            ->where('filters.subject_type', null)
            ->where('filters.action', null)
            ->where('filters.page', 1)
            ->missing('activity')
        );

    $response->assertInertia(fn (Assert $page) => $page
        ->loadDeferredProps(fn (Assert $reload) => $reload
            ->has('activity.data')
            ->where('activity.current_page', 1)
        )
    );
});

test('activity page supports partial reload filters', function () {
    $this->actingAs($this->user);

    ActivityLog::query()->create([
        'ledger_id' => $this->ledger->id,
        'user_id' => $this->user->id,
        'action' => 'created',
        'subject_type' => Budget::class,
        'subject_id' => 10,
        'old_values' => [],
        'new_values' => ['name' => 'Groceries'],
        'created_at' => now(),
    ]);

    $response = $this->get(route('ledgers.activity.index', [
        $this->ledger,
        'subject_type' => 'Budget',
        'action' => 'created',
    ]));

    $response->assertInertia(fn (Assert $page) => $page
        ->component('ledgers/activity/index')
        ->where('filters.subject_type', 'Budget')
        ->where('filters.action', 'created')
        ->loadDeferredProps(fn (Assert $reload) => $reload
            ->has('activity.data', 1, fn (Assert $entry) => $entry
                ->where('subject_type', 'Budget')
                ->where('action', 'created')
                ->etc()
            )
        )
    );

    $response->assertInertia(fn (Assert $page) => $page
        ->reloadOnly('activity', fn (Assert $reload) => $reload
            ->has('activity.data', 1)
            ->missing('filters')
        )
    );
});

test('activity log excludes sensitive timestamp fields', function () {
    $this->actingAs($this->user);

    Transaction::factory()
        ->for($this->ledger)
        ->for($this->account)
        ->for($this->category)
        ->create(['description' => 'Test']);

    $log = ActivityLog::query()
        ->where('ledger_id', $this->ledger->id)
        ->where('action', 'created')
        ->where('subject_type', Transaction::class)
        ->first();

    expect($log->new_values)->not->toHaveKey('created_at')
        ->and($log->new_values)->not->toHaveKey('updated_at')
        ->and($log->new_values)->not->toHaveKey('deleted_at');
});
