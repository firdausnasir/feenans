<?php

use App\Actions\Bills\UseCases\DeleteBillAction;
use App\Actions\Bills\UseCases\PayBillAction;
use App\Actions\Bills\UseCases\StoreBillAction;
use App\Actions\Bills\UseCases\ToggleBillAction;
use App\Actions\Bills\UseCases\UpdateBillAction;
use App\Models\Account;
use App\Models\AccountType;
use App\Models\Bill;
use App\Models\Category;
use App\Models\Ledger;
use App\Models\User;

test('bill store routes through StoreBillAction', function () {
    $user = User::factory()->create();
    $user->membership()->update(['tier' => 'premium', 'status' => 'active']);
    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create();
    $category = Category::factory()->for($ledger)->create();

    $called = false;
    $real = app()->make(StoreBillAction::class);
    app()->bind(StoreBillAction::class, function () use ($real, &$called) {
        $called = true;

        return $real;
    });

    $this->actingAs($user)
        ->from(route('ledgers.bills.index', $ledger))
        ->post(route('ledgers.bills.store', $ledger), [
            'name' => 'Streaming',
            'transaction_type' => 'expense',
            'amount' => 29.99,
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
        ])
        ->assertRedirect()
        ->assertSessionHas('success', 'Recurring transaction created.');

    expect($called)->toBeTrue();
});

test('bill update routes through UpdateBillAction', function () {
    $user = User::factory()->create();
    $user->membership()->update(['tier' => 'premium', 'status' => 'active']);
    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create();
    $bill = Bill::factory()->for($ledger)->for($account)->create();

    $called = false;
    $real = app()->make(UpdateBillAction::class);
    app()->bind(UpdateBillAction::class, function () use ($real, &$called) {
        $called = true;

        return $real;
    });

    $this->actingAs($user)
        ->from(route('ledgers.bills.edit', [$ledger, $bill]))
        ->put(route('ledgers.bills.update', [$ledger, $bill]), [
            'name' => 'Updated Bill',
            'transaction_type' => 'expense',
            'amount' => 250.00,
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
        ])
        ->assertRedirect()
        ->assertSessionHas('success', 'Recurring transaction updated.');

    expect($called)->toBeTrue();
});

test('bill destroy routes through DeleteBillAction', function () {
    $user = User::factory()->create();
    $user->membership()->update(['tier' => 'premium', 'status' => 'active']);
    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create();
    $bill = Bill::factory()->for($ledger)->for($account)->create();

    $called = false;
    $real = app()->make(DeleteBillAction::class);
    app()->bind(DeleteBillAction::class, function () use ($real, &$called) {
        $called = true;

        return $real;
    });

    $this->actingAs($user)
        ->from(route('ledgers.bills.index', $ledger))
        ->delete(route('ledgers.bills.destroy', [$ledger, $bill]))
        ->assertRedirect()
        ->assertSessionHas('success', 'Recurring transaction deleted.');

    expect($called)->toBeTrue();
});

test('bill toggle routes through ToggleBillAction', function () {
    $user = User::factory()->create();
    $user->membership()->update(['tier' => 'premium', 'status' => 'active']);
    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create();
    $bill = Bill::factory()->for($ledger)->for($account)->create();

    $called = false;
    $real = app()->make(ToggleBillAction::class);
    app()->bind(ToggleBillAction::class, function () use ($real, &$called) {
        $called = true;

        return $real;
    });

    $this->actingAs($user)
        ->from(route('ledgers.bills.index', $ledger))
        ->patch(route('ledgers.bills.toggle', [$ledger, $bill]))
        ->assertRedirect()
        ->assertSessionHas('success', 'Recurring transaction deactivated.');

    expect($called)->toBeTrue();
});

test('bill pay routes through PayBillAction', function () {
    $user = User::factory()->create();
    $user->membership()->update(['tier' => 'premium', 'status' => 'active']);
    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create();
    $bill = Bill::factory()->for($ledger)->for($account)->create();

    $called = false;
    $real = app()->make(PayBillAction::class);
    app()->bind(PayBillAction::class, function () use ($real, &$called) {
        $called = true;

        return $real;
    });

    $this->actingAs($user)
        ->from(route('ledgers.bills.index', $ledger))
        ->post(route('ledgers.bills.pay', [$ledger, $bill]))
        ->assertRedirect()
        ->assertSessionHas('success', "{$bill->name} marked as paid.");

    expect($called)->toBeTrue();
});

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
    $accountType = AccountType::factory()->for($ledger)->create();

    Account::factory()->for($ledger)->for($accountType)->create([
        'name' => 'Checking',
        'position' => 1,
    ]);
    Account::factory()->for($ledger)->for($accountType)->create([
        'name' => 'Savings',
        'include_in_totals' => false,
        'position' => 2,
    ]);

    $response = $this
        ->actingAs($user)
        ->get(route('ledgers.bills.create', $ledger));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('ledgers/bills/create')
        ->has('accounts', 2)
        ->where('accounts.0.name', 'Checking')
        ->where('accounts.0.include_in_totals', true)
        ->where('accounts.1.name', 'Savings')
        ->where('accounts.1.include_in_totals', false)
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
