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

test('payee index renders the inertia shell for shared-core payee data', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    Payee::factory()->for($ledger)->create([
        'name' => 'shell-payee',
        'is_sample' => true,
    ]);

    $this->actingAs($user)
        ->get(route('ledgers.payees.index', $ledger))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('ledgers/payees/index')
            ->where('currentLedger.id', $ledger->id)
            ->missing('payees')
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->has('payees', 1, fn (Assert $payeePage) => $payeePage
                    ->where('name', 'shell-payee')
                    ->where('is_sample', true)
                    ->etc()
                )
            )
        );
});

test('payee index supports partial reloads for payees', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    Payee::factory()->for($ledger)->create(['name' => 'groceries']);

    $response = $this->actingAs($user)
        ->get(route('ledgers.payees.index', $ledger));

    $response->assertSuccessful();
    $response->assertInertia(fn (Assert $page) => $page
        ->where('currentLedger.id', $ledger->id)
        ->reloadOnly('payees', fn (Assert $reload) => $reload
            ->has('payees', 1, fn (Assert $payeePage) => $payeePage
                ->where('name', 'groceries')
                ->etc()
            )
            ->missing('currentLedger')
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

test('payee web create uses correct redirect and flash under shared actions', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $filteredUrl = route('ledgers.payees.index', ['ledger' => $ledger, 'search' => 'shared']);

    $response = $this->actingAs($user)
        ->from($filteredUrl)
        ->withHeaders([
            'X-Inertia' => 'true',
            'X-Requested-With' => 'XMLHttpRequest',
        ])
        ->post(route('ledgers.payees.store', $ledger), [
            'name' => 'shared-create',
        ]);

    $response->assertRedirect($filteredUrl)
        ->assertSessionHasNoErrors()
        ->assertSessionHas('success', 'Payee added.');
});

test('payee web update uses correct redirect and flash under shared actions', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $payee = Payee::factory()->for($ledger)->create(['name' => 'before-update']);
    $filteredUrl = route('ledgers.payees.index', ['ledger' => $ledger, 'search' => 'before']);

    $response = $this->actingAs($user)
        ->from($filteredUrl)
        ->withHeaders([
            'X-Inertia' => 'true',
            'X-Requested-With' => 'XMLHttpRequest',
        ])
        ->patch(route('ledgers.payees.update', [$ledger, $payee]), [
            'name' => 'after-update',
        ]);

    $response->assertRedirect($filteredUrl)
        ->assertSessionHasNoErrors()
        ->assertSessionHas('success', 'Payee updated.');
});

test('payee web delete uses correct redirect and flash under shared actions', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $payee = Payee::factory()->for($ledger)->create(['name' => 'delete-shared']);
    $filteredUrl = route('ledgers.payees.index', ['ledger' => $ledger, 'search' => 'delete']);

    $response = $this->actingAs($user)
        ->from($filteredUrl)
        ->withHeaders([
            'X-Inertia' => 'true',
            'X-Requested-With' => 'XMLHttpRequest',
        ])
        ->delete(route('ledgers.payees.destroy', [$ledger, $payee]));

    $response->assertRedirect($filteredUrl)
        ->assertSessionHas('success', 'Payee deleted.');
});

test('payee web routes continue to enforce ledger authorization through shared actions', function () {
    $owner = User::factory()->create();
    $outsider = User::factory()->create();
    $ledger = Ledger::factory()->for($owner)->create();
    $payee = Payee::factory()->for($ledger)->create();

    $this->actingAs($outsider)
        ->get(route('ledgers.payees.index', $ledger))
        ->assertForbidden();

    $this->actingAs($outsider)
        ->post(route('ledgers.payees.store', $ledger), [
            'name' => 'forbidden-create',
        ])
        ->assertForbidden();

    $this->actingAs($outsider)
        ->patch(route('ledgers.payees.update', [$ledger, $payee]), [
            'name' => 'forbidden-update',
        ])
        ->assertForbidden();

    $this->actingAs($outsider)
        ->delete(route('ledgers.payees.destroy', [$ledger, $payee]))
        ->assertForbidden();
});

test('ledger payee web routes are available for inertia actions', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $payee = Payee::factory()->for($ledger)->create();

    expect(parse_url(route('ledgers.payees.store', $ledger), PHP_URL_PATH))->toBe("/ledgers/{$ledger->id}/payees")
        ->and(parse_url(route('ledgers.payees.update', [$ledger, $payee]), PHP_URL_PATH))->toBe("/ledgers/{$ledger->id}/payees/{$payee->id}")
        ->and(parse_url(route('ledgers.payees.destroy', [$ledger, $payee]), PHP_URL_PATH))->toBe("/ledgers/{$ledger->id}/payees/{$payee->id}");
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
