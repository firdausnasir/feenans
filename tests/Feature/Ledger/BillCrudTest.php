<?php

use App\Models\Account;
use App\Models\AccountType;
use App\Models\Bill;
use App\Models\Category;
use App\Models\Ledger;
use App\Models\Payee;
use App\Models\User;

test('bill update via HTTP updates the bill', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create();
    $category = Category::factory()->for($ledger)->create();

    $bill = Bill::factory()->for($ledger)->for($account)->create([
        'name' => 'Old Bill',
        'amount' => 100.00,
    ]);

    $response = $this
        ->actingAs($user)
        ->put(route('ledgers.bills.update', [$ledger, $bill]), [
            'name' => 'Updated Bill',
            'transaction_type' => 'expense',
            'amount' => 250.00,
            'account_id' => $account->id,
            'category_id' => $category->id,
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

    $response->assertRedirect(route('ledgers.bills.index', $ledger));

    $bill->refresh();
    expect($bill->name)->toBe('Updated Bill')
        ->and((string) $bill->amount)->toBe('250.00')
        ->and($bill->category_id)->toBe($category->id);
});

test('bill destroy deletes bill via HTTP', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create();

    $bill = Bill::factory()->for($ledger)->for($account)->create();

    $response = $this
        ->actingAs($user)
        ->delete(route('ledgers.bills.destroy', [$ledger, $bill]));

    $response->assertRedirect(route('ledgers.bills.index', $ledger));

    expect(Bill::find($bill->id))->toBeNull()
        ->and(Bill::withTrashed()->find($bill->id)?->trashed())->toBeTrue();
});

test('bill trash page shows deleted bills', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create();

    $bill = Bill::factory()->for($ledger)->for($account)->trashed()->create([
        'name' => 'Deleted Bill',
    ]);

    $response = $this
        ->actingAs($user)
        ->get(route('ledgers.bills.trash', $ledger));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('ledgers/bills/trash/index')
        ->has('bills', 1)
    );
});

test('bill restore restores a soft deleted bill', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create();

    $bill = Bill::factory()->for($ledger)->for($account)->trashed()->create();

    $response = $this
        ->actingAs($user)
        ->patch(route('ledgers.bills.restore', [$ledger, $bill]));

    $response->assertRedirect(route('ledgers.bills.trash', $ledger));

    $this->assertNotSoftDeleted('bills', ['id' => $bill->id]);
});

test('bill force destroy permanently deletes a bill', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create();

    $bill = Bill::factory()->for($ledger)->for($account)->trashed()->create();

    $response = $this
        ->actingAs($user)
        ->delete(route('ledgers.bills.force-destroy', [$ledger, $bill]));

    $response->assertRedirect(route('ledgers.bills.trash', $ledger));

    expect(Bill::withTrashed()->find($bill->id))->toBeNull();
});

test('bill create page renders with necessary data', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    Account::factory()->for($ledger)->for($accountType)->create();
    Category::factory()->for($ledger)->create();
    Payee::factory()->for($ledger)->create();

    $response = $this
        ->actingAs($user)
        ->get(route('ledgers.bills.create', $ledger));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('ledgers/bills/create')
        ->has('accounts', 1)
        ->has('categories')
        ->has('payees', 1)
    );
});

test('bill edit page renders with bill data', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create();

    $bill = Bill::factory()->for($ledger)->for($account)->create(['name' => 'Edit Me']);

    $response = $this
        ->actingAs($user)
        ->get(route('ledgers.bills.edit', [$ledger, $bill]));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('ledgers/bills/edit')
        ->has('bill')
        ->has('accounts')
        ->has('categories')
        ->has('payees')
    );
});

test('bill store is forbidden for another users ledger', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $ledger = Ledger::factory()->for($owner)->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create();

    $this->actingAs($intruder)
        ->post(route('ledgers.bills.store', $ledger), [
            'name' => 'Test',
            'transaction_type' => 'expense',
            'amount' => 100.00,
            'account_id' => $account->id,
            'recurrence_type' => 'monthly',
            'recurrence_interval' => 1,
            'next_due_date' => '2026-04-01',
            'auto_create' => false,
        ])
        ->assertForbidden();
});

test('bill update is forbidden for another users ledger', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $ledger = Ledger::factory()->for($owner)->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create();
    $bill = Bill::factory()->for($ledger)->for($account)->create();

    $this->actingAs($intruder)
        ->put(route('ledgers.bills.update', [$ledger, $bill]), [
            'name' => 'Hacked',
            'transaction_type' => 'expense',
            'amount' => 1.00,
            'account_id' => $account->id,
            'recurrence_type' => 'monthly',
            'recurrence_interval' => 1,
            'next_due_date' => '2026-04-01',
            'auto_create' => false,
        ])
        ->assertForbidden();
});
