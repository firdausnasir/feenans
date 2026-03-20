<?php

use App\Models\Account;
use App\Models\Ledger;
use App\Models\Payee;
use App\Models\Transaction;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('users can create payees in a ledger', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();

    $response = $this
        ->actingAs($user)
        ->from(route('ledgers.payees.index', $ledger))
        ->post(route('ledgers.payees.store', $ledger), [
            'name' => 'Local Cafe',
        ]);

    $response->assertRedirect(route('ledgers.payees.index', $ledger))
        ->assertSessionHasNoErrors();

    expect($ledger->payees()->where('name', 'Local Cafe')->exists())->toBeTrue();
});

test('users cannot create payees without a name via the web flow', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();

    $response = $this
        ->actingAs($user)
        ->from(route('ledgers.payees.index', $ledger))
        ->post(route('ledgers.payees.store', $ledger), []);

    $response->assertRedirect(route('ledgers.payees.index', $ledger))
        ->assertSessionHasErrors(['name']);
});

test('users cannot update payees without a name via the web flow', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $payee = Payee::factory()->for($ledger)->create();

    $response = $this
        ->actingAs($user)
        ->from(route('ledgers.payees.index', $ledger))
        ->patch(route('ledgers.payees.update', [$ledger, $payee]), []);

    $response->assertRedirect(route('ledgers.payees.index', $ledger))
        ->assertSessionHasErrors(['name']);
});

test('payee index page renders successfully', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    Payee::factory()->for($ledger)->create(['name' => 'Alpha Payee']);

    $response = $this
        ->actingAs($user)
        ->get(route('ledgers.payees.index', $ledger));

    $response->assertSuccessful();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('ledgers/payees/index')
        ->where('search', '')
        ->missing('payees')
        ->loadDeferredProps(fn (Assert $reload) => $reload
            ->has('payees', 1)
            ->where('payees.0.name', 'Alpha Payee')
        )
    );
});

test('payee update updates payee name', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $payee = Payee::factory()->for($ledger)->create(['name' => 'Old Payee']);

    $response = $this
        ->actingAs($user)
        ->from(route('ledgers.payees.index', $ledger))
        ->patch(route('ledgers.payees.update', [$ledger, $payee]), [
            'name' => 'New Payee',
        ]);

    $response->assertRedirect(route('ledgers.payees.index', $ledger))
        ->assertSessionHasNoErrors();

    expect($payee->fresh()->name)->toBe('New Payee');
});

test('payee destroy deletes payee', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $payee = Payee::factory()->for($ledger)->create();

    $response = $this
        ->actingAs($user)
        ->from(route('ledgers.payees.index', $ledger))
        ->delete(route('ledgers.payees.destroy', [$ledger, $payee]));

    $response->assertRedirect(route('ledgers.payees.index', $ledger));

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
        ->from(route('ledgers.payees.index', $ledger))
        ->patch(route('ledgers.payees.update', [$ledger, $payee]), [
            'name' => 'New Store',
        ]);

    $response->assertRedirect(route('ledgers.payees.index', $ledger))
        ->assertSessionHasNoErrors();

    $payee->refresh();
    expect($payee->name)->toBe('New Store');

    $transaction = $payee->transactions()->first();
    expect($transaction->payee_id)->toBe($payee->id);
    expect($transaction->payee->name)->toBe('New Store');
});

test('payee index page renders with search parameter', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    Payee::factory()->for($ledger)->create(['name' => 'Coffee Shop']);
    Payee::factory()->for($ledger)->create(['name' => 'Grocer']);

    $response = $this
        ->actingAs($user)
        ->get(route('ledgers.payees.index', ['ledger' => $ledger, 'search' => 'Coffee']));

    $response->assertSuccessful();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('ledgers/payees/index')
        ->where('search', 'Coffee')
        ->missing('payees')
        ->loadDeferredProps(fn (Assert $reload) => $reload
            ->has('payees', 1)
            ->where('payees.0.name', 'Coffee Shop')
        )
    );
});
