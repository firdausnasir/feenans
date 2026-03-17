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
        ->postJson(route('api.v1.ledgers.payees.store', $ledger), [
            'name' => 'Local Cafe',
        ]);

    $response->assertStatus(201);

    expect($ledger->payees()->where('name', 'Local Cafe')->exists())->toBeTrue();
});

test('payee index page renders successfully', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();

    $response = $this
        ->actingAs($user)
        ->get(route('ledgers.payees.index', $ledger));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('ledgers/payees/index')
    );
});

test('payee update updates payee name', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $payee = Payee::factory()->for($ledger)->create(['name' => 'Old Payee']);

    $response = $this
        ->actingAs($user)
        ->putJson(route('api.v1.ledgers.payees.update', [$ledger, $payee]), [
            'name' => 'New Payee',
        ]);

    $response->assertOk();

    expect($payee->fresh()->name)->toBe('New Payee');
});

test('payee destroy deletes payee', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $payee = Payee::factory()->for($ledger)->create();

    $response = $this
        ->actingAs($user)
        ->deleteJson(route('api.v1.ledgers.payees.destroy', [$ledger, $payee]));

    $response->assertNoContent();

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
        ->putJson(route('api.v1.ledgers.payees.update', [$ledger, $payee]), [
            'name' => 'New Store',
        ]);

    $response->assertOk();

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
        ->postJson(route('api.v1.ledgers.payees.merge', $ledger), [
            'source_id' => $sourcePayee->id,
            'target_id' => $targetPayee->id,
        ]);

    $response->assertOk();

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
        ->postJson(route('api.v1.ledgers.payees.merge', $ledger), [
            'source_id' => $payee->id,
            'target_id' => $payee->id,
        ]);

    $response->assertStatus(422)->assertJsonValidationErrors('source_id');
});

test('merge payees fails when source payee does not exist', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $targetPayee = Payee::factory()->for($ledger)->create();

    $response = $this
        ->actingAs($user)
        ->postJson(route('api.v1.ledgers.payees.merge', $ledger), [
            'source_id' => 99999,
            'target_id' => $targetPayee->id,
        ]);

    $response->assertStatus(404);
});

test('payee index page renders with search parameter', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();

    $response = $this
        ->actingAs($user)
        ->get(route('ledgers.payees.index', ['ledger' => $ledger, 'search' => 'Coffee']));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('ledgers/payees/index')
    );
});
