<?php

use App\Models\Account;
use App\Models\AccountType;
use App\Models\Ledger;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('users can create an account inside their ledger', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->create();

    $response = $this
        ->actingAs($user)
        ->from(route('ledgers.accounts.index', $ledger))
        ->post(route('ledgers.accounts.store', $ledger), [
            'account_type_id' => $accountType->id,
            'name' => 'Main Wallet',
            'initial_balance' => 150.75,
            'statement_day' => null,
            'include_in_totals' => true,
        ]);

    $response->assertRedirect(route('ledgers.accounts.index', $ledger))
        ->assertSessionHasNoErrors();

    expect($ledger->accounts()->where('name', 'Main Wallet')->exists())->toBeTrue();
});

test('account store rejects an account type from another ledger', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $foreignLedger = Ledger::factory()->create();
    $foreignAccountType = AccountType::factory()->for($foreignLedger)->create();

    $response = $this->actingAs($user)
        ->from(route('ledgers.accounts.index', $ledger))
        ->post(route('ledgers.accounts.store', $ledger), [
            'account_type_id' => $foreignAccountType->id,
            'name' => 'Main Wallet',
            'initial_balance' => 150.75,
            'statement_day' => null,
            'include_in_totals' => true,
        ]);

    $response->assertRedirect(route('ledgers.accounts.index', $ledger))
        ->assertSessionHasErrors('account_type_id');
});

test('account update updates the account and redirects', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create([
        'name' => 'Old Name',
        'initial_balance' => 100.00,
    ]);

    $response = $this
        ->actingAs($user)
        ->from(route('ledgers.accounts.index', $ledger))
        ->put(route('ledgers.accounts.update', [$ledger, $account]), [
            'name' => 'New Name',
            'account_type_id' => $accountType->id,
            'initial_balance' => 200.00,
            'statement_day' => null,
            'include_in_totals' => true,
        ]);

    $response->assertRedirect(route('ledgers.accounts.index', $ledger))
        ->assertSessionHasNoErrors();

    expect($account->fresh()->name)->toBe('New Name')
        ->and((float) $account->fresh()->initial_balance)->toBe(200.00);
});

test('account destroy deletes account and redirects to index', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create();

    $response = $this
        ->actingAs($user)
        ->from(route('ledgers.accounts.index', $ledger))
        ->delete(route('ledgers.accounts.destroy', [$ledger, $account]));

    $response->assertRedirect(route('ledgers.accounts.index', $ledger));

    expect(Account::find($account->id))->toBeNull();
});

test('account index is forbidden for another user', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $ledger = Ledger::factory()->for($owner)->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    Account::factory()->for($ledger)->for($accountType)->create();

    $response = $this
        ->actingAs($other)
        ->get(route('ledgers.accounts.index', $ledger));

    $response->assertForbidden();
});

test('accounts index omits account loader props from the inertia shell', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $type = AccountType::factory()->for($ledger)->create();

    Account::factory()->for($ledger)->for($type)->create([
        'name' => 'Checking',
        'include_in_totals' => true,
        'initial_balance' => 1000,
    ]);
    Account::factory()->for($ledger)->for($type)->create([
        'name' => 'Rainy Day',
        'include_in_totals' => false,
        'initial_balance' => 5000,
    ]);

    $this->actingAs($user)
        ->get(route('ledgers.accounts.index', $ledger))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->missing('accounts')
            ->missing('accountTypes')
            ->missing('netWorth')
        );
});
