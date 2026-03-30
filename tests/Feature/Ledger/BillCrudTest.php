<?php

use App\Models\Account;
use App\Models\AccountType;
use App\Models\Bill;
use App\Models\Category;
use App\Models\Ledger;
use App\Models\User;

test('bill update via HTTP updates the bill', function () {
    $user = User::factory()->create();
    $user->membership()->update(['tier' => 'premium', 'status' => 'active']);
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
        ->from(route('ledgers.bills.edit', [$ledger, $bill]))
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

    $response->assertRedirect(route('ledgers.bills.index', $ledger))
        ->assertSessionHasNoErrors();

    $bill->refresh();
    expect($bill->name)->toBe('Updated Bill')
        ->and((string) $bill->amount)->toBe('250.00')
        ->and($bill->category_id)->toBe($category->id);
});

test('bill destroy deletes bill via HTTP', function () {
    $user = User::factory()->create();
    $user->membership()->update(['tier' => 'premium', 'status' => 'active']);
    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create();

    $bill = Bill::factory()->for($ledger)->for($account)->create();

    $response = $this
        ->actingAs($user)
        ->from(route('ledgers.bills.index', $ledger))
        ->delete(route('ledgers.bills.destroy', [$ledger, $bill]));

    $response->assertRedirect(route('ledgers.bills.index', $ledger));

    expect(Bill::find($bill->id))->toBeNull();
});

test('bill create page renders', function () {
    $user = User::factory()->create();
    $user->membership()->update(['tier' => 'premium', 'status' => 'active']);
    $ledger = Ledger::factory()->for($user)->create();

    $response = $this
        ->actingAs($user)
        ->get(route('ledgers.bills.create', $ledger));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('ledgers/bills/create')
    );
});

test('bill edit page renders', function () {
    $user = User::factory()->create();
    $user->membership()->update(['tier' => 'premium', 'status' => 'active']);
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
    );
});

test('bill store can create a new payee through the web route', function () {
    $user = User::factory()->create();
    $user->membership()->update(['tier' => 'premium', 'status' => 'active']);
    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create();
    $category = Category::factory()->for($ledger)->create();

    $response = $this
        ->actingAs($user)
        ->from(route('ledgers.bills.create', $ledger))
        ->post(route('ledgers.bills.store', $ledger), [
            'name' => 'New Bill With Payee',
            'transaction_type' => 'expense',
            'amount' => 50.00,
            'account_id' => $account->id,
            'category_id' => $category->id,
            'payee_id' => null,
            'new_payee_name' => 'Brand New Payee',
            'recurrence_type' => 'monthly',
            'recurrence_interval' => 1,
            'recurrence_day' => null,
            'next_due_date' => '2026-05-01',
            'auto_create' => false,
            'end_type' => null,
            'end_date' => null,
            'end_after_occurrences' => null,
        ]);

    $response->assertRedirect(route('ledgers.bills.index', $ledger))
        ->assertSessionHasNoErrors();

    $bill = $ledger->bills()->where('name', 'New Bill With Payee')->first();

    expect($ledger->payees()->where('name', 'Brand New Payee')->exists())->toBeTrue()
        ->and($bill?->payee?->name)->toBe('Brand New Payee');
});

test('bill update can create a new payee through the web route', function () {
    $user = User::factory()->create();
    $user->membership()->update(['tier' => 'premium', 'status' => 'active']);
    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create();
    $category = Category::factory()->for($ledger)->create();

    $bill = Bill::factory()->for($ledger)->for($account)->create([
        'name' => 'Bill Without Payee',
        'amount' => 75.00,
    ]);

    $response = $this
        ->actingAs($user)
        ->from(route('ledgers.bills.edit', [$ledger, $bill]))
        ->put(route('ledgers.bills.update', [$ledger, $bill]), [
            'name' => 'Bill Without Payee',
            'transaction_type' => 'expense',
            'amount' => 75.00,
            'account_id' => $account->id,
            'category_id' => $category->id,
            'payee_id' => null,
            'new_payee_name' => 'Fresh Update Payee',
            'recurrence_type' => 'monthly',
            'recurrence_interval' => 1,
            'recurrence_day' => null,
            'next_due_date' => '2026-05-01',
            'auto_create' => false,
            'end_type' => null,
            'end_date' => null,
            'end_after_occurrences' => null,
        ]);

    $response->assertRedirect(route('ledgers.bills.index', $ledger))
        ->assertSessionHasNoErrors();

    $bill->refresh();

    expect($ledger->payees()->where('name', 'Fresh Update Payee')->exists())->toBeTrue()
        ->and($bill->payee?->name)->toBe('Fresh Update Payee');
});

test('bill store validation redirects back with submitted input', function () {
    $user = User::factory()->create();
    $user->membership()->update(['tier' => 'premium', 'status' => 'active']);
    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create();
    $category = Category::factory()->for($ledger)->create();

    $response = $this
        ->actingAs($user)
        ->from(route('ledgers.bills.index', $ledger))
        ->post(route('ledgers.bills.store', $ledger), [
            'name' => 'Keep My Draft',
            'transaction_type' => 'expense',
            'amount' => 50.00,
            'account_id' => $account->id,
            'category_id' => $category->id,
            'payee_id' => null,
            'recurrence_type' => 'monthly',
            'recurrence_interval' => 1,
            'recurrence_day' => null,
            'next_due_date' => '',
            'auto_create' => false,
            'end_type' => null,
            'end_date' => null,
            'end_after_occurrences' => null,
        ]);

    $response->assertRedirect(route('ledgers.bills.index', $ledger))
        ->assertSessionHasErrors(['next_due_date'])
        ->assertSessionHasInput([
            'name' => 'Keep My Draft',
            'amount' => 50.00,
            'account_id' => $account->id,
            'category_id' => $category->id,
            'next_due_date' => '',
        ]);
});

test('bill store is forbidden for another users ledger', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $intruder->membership()->update(['tier' => 'premium', 'status' => 'active']);
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
    $intruder->membership()->update(['tier' => 'premium', 'status' => 'active']);
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
