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

test('credit card accounts do not include computed statement fields', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->credit()->create();
    Account::factory()->for($ledger)->for($accountType)->create([
        'initial_balance' => 0,
        'statement_day' => 15,
        'payment_due_day' => 25,
    ]);

    $this->actingAs($user)
        ->get(route('ledgers.accounts.index', $ledger))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->has('accounts', 1)
            ->has('accounts.0.accounts', 1)
            ->where('accounts.0.accounts.0.statement_day', 15)
            ->where('accounts.0.accounts.0.payment_due_day', 25)
            ->missing('accounts.0.accounts.0.statement_balance')
            ->missing('accounts.0.accounts.0.current_spending')
            ->missing('accounts.0.accounts.0.outstanding')
            ->missing('accounts.0.accounts.0.payment_due_date')
            ->missing('accounts.0.accounts.0.statement_start')
            ->missing('accounts.0.accounts.0.statement_end')
        );
});
