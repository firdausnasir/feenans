<?php

use App\Models\Account;
use App\Models\Ledger;
use App\Models\Payee;
use App\Models\Transaction;
use App\Models\User;

test('users can create payees in a ledger', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();

    $response = $this
        ->actingAs($user)
        ->post(route('ledgers.payees.store', $ledger), [
            'name' => 'Local Cafe',
        ]);

    $response->assertRedirect();

    expect($ledger->payees()->where('name', 'Local Cafe')->exists())->toBeTrue();
});

test('payee index returns payees with transaction count', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();

    Payee::factory()->for($ledger)->create(['name' => 'Alpha']);
    Payee::factory()->for($ledger)->create(['name' => 'Beta']);

    $response = $this
        ->actingAs($user)
        ->get(route('ledgers.payees.index', $ledger));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('ledgers/payees/index')
        ->has('payees', 2)
        ->has('ledger')
    );
});

test('payee update updates payee name', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $payee = Payee::factory()->for($ledger)->create(['name' => 'Old Payee']);

    $response = $this
        ->actingAs($user)
        ->put(route('ledgers.payees.update', [$ledger, $payee]), [
            'name' => 'New Payee',
        ]);

    $response->assertRedirect();

    expect($payee->fresh()->name)->toBe('New Payee');
});

test('payee destroy deletes payee', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $payee = Payee::factory()->for($ledger)->create();

    $response = $this
        ->actingAs($user)
        ->delete(route('ledgers.payees.destroy', [$ledger, $payee]));

    $response->assertRedirect();

    expect(Payee::find($payee->id))->toBeNull();
});

test('rename payee updates name on associated transactions', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $account = Account::factory()->for($ledger)->create();
    $payee = Payee::factory()->for($ledger)->create(['name' => 'Old Store']);

    Transaction::factory()->for($ledger)->create([
        'account_id' => $account->id,
        'payee_id' => $payee->id,
    ]);

    $response = $this
        ->actingAs($user)
        ->put(route('ledgers.payees.update', [$ledger, $payee]), [
            'name' => 'New Store',
        ]);

    $response->assertRedirect();

    $payee->refresh();
    expect($payee->name)->toBe('New Store');

    $transaction = $payee->transactions()->first();
    expect($transaction->payee_id)->toBe($payee->id);
    expect($transaction->payee->name)->toBe('New Store');
});

test('merge payees reassigns all transactions from source to target and deletes source', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $account = Account::factory()->for($ledger)->create();

    $sourcePayee = Payee::factory()->for($ledger)->create(['name' => 'Source Payee']);
    $targetPayee = Payee::factory()->for($ledger)->create(['name' => 'Target Payee']);

    $sourceTransactions = Transaction::factory()
        ->count(3)
        ->for($ledger)
        ->create([
            'account_id' => $account->id,
            'payee_id' => $sourcePayee->id,
        ]);

    $targetTransaction = Transaction::factory()->for($ledger)->create([
        'account_id' => $account->id,
        'payee_id' => $targetPayee->id,
    ]);

    $response = $this
        ->actingAs($user)
        ->post(route('ledgers.payees.merge', $ledger), [
            'source_id' => $sourcePayee->id,
            'target_id' => $targetPayee->id,
        ]);

    $response->assertRedirect();

    expect(Payee::find($sourcePayee->id))->toBeNull();
    expect(Payee::find($targetPayee->id))->not->toBeNull();

    expect($targetPayee->transactions()->count())->toBe(4);

    foreach ($sourceTransactions as $transaction) {
        expect($transaction->fresh()->payee_id)->toBe($targetPayee->id);
    }
});

test('merge payees validates source and target must be different', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $payee = Payee::factory()->for($ledger)->create();

    $response = $this
        ->actingAs($user)
        ->post(route('ledgers.payees.merge', $ledger), [
            'source_id' => $payee->id,
            'target_id' => $payee->id,
        ]);

    $response->assertSessionHasErrors('source_id');
});

test('merge payees fails when source payee does not exist', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $targetPayee = Payee::factory()->for($ledger)->create();

    $response = $this
        ->actingAs($user)
        ->post(route('ledgers.payees.merge', $ledger), [
            'source_id' => 99999,
            'target_id' => $targetPayee->id,
        ]);

    $response->assertStatus(404);
});

test('payee search filters by name', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();

    Payee::factory()->for($ledger)->create(['name' => 'Coffee Shop']);
    Payee::factory()->for($ledger)->create(['name' => 'Grocery Store']);
    Payee::factory()->for($ledger)->create(['name' => 'Coffee House']);

    $response = $this
        ->actingAs($user)
        ->get(route('ledgers.payees.index', ['ledger' => $ledger, 'search' => 'Coffee']));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('ledgers/payees/index')
        ->has('payees', 2)
        ->where('filters.search', 'Coffee')
    );
});

test('payee search with no match returns empty results', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();

    Payee::factory()->for($ledger)->create(['name' => 'Coffee Shop']);

    $response = $this
        ->actingAs($user)
        ->get(route('ledgers.payees.index', ['ledger' => $ledger, 'search' => 'NonExistent']));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('ledgers/payees/index')
        ->has('payees', 0)
    );
});
