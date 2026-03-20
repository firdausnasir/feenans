<?php

use App\Models\AccountType;
use App\Models\Ledger;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('settings page renders for ledger owner', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();

    $response = $this
        ->actingAs($user)
        ->get(route('ledgers.settings.index', $ledger));

    $response->assertSuccessful();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('ledgers/settings/index')
        ->has('ledger', fn (Assert $ledgerPage) => $ledgerPage
            ->where('id', $ledger->id)
            ->where('name', $ledger->name)
            ->etc()
        )
        ->has('accountTypes')
        ->has('hasSampleData')
    );
});

test('settings page is forbidden for non-owner', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $ledger = Ledger::factory()->for($owner)->create();

    $this->actingAs($other)
        ->get(route('ledgers.settings.index', $ledger))
        ->assertForbidden();
});

test('settings update changes ledger name and cycle day', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create([
        'name' => 'Old Name',
        'cycle_start_day' => 1,
    ]);

    $response = $this
        ->actingAs($user)
        ->from(route('ledgers.settings.index', $ledger))
        ->put(route('ledgers.settings.update', $ledger), [
            'name' => 'New Name',
            'currency_code' => 'USD',
            'cycle_start_day' => 15,
        ]);

    $response->assertRedirect(route('ledgers.settings.index', $ledger))
        ->assertSessionHasNoErrors();

    $ledger->refresh();
    expect($ledger->name)->toBe('New Name')
        ->and($ledger->cycle_start_day)->toBe(15);
});

test('settings update is forbidden for non-owner', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $ledger = Ledger::factory()->for($owner)->create();

    $this->actingAs($other)
        ->put(route('ledgers.settings.update', $ledger), [
            'name' => 'Hacked',
            'currency_code' => 'USD',
            'cycle_start_day' => 1,
        ])
        ->assertForbidden();
});

test('settings update validates through web routes', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();

    $response = $this->actingAs($user)
        ->from(route('ledgers.settings.index', $ledger))
        ->put(route('ledgers.settings.update', $ledger), [
            'name' => '',
            'currency_code' => 'USD',
            'cycle_start_day' => 40,
        ]);

    $response->assertRedirect(route('ledgers.settings.index', $ledger))
        ->assertSessionHasErrors(['name', 'cycle_start_day']);
});

test('account type update validates through web routes with form request messages', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->create();

    $response = $this->actingAs($user)
        ->from(route('ledgers.settings.index', $ledger))
        ->put(route('ledgers.settings.account-types.update', [$ledger, $accountType]), [
            'color' => str_repeat('#', 21),
            'is_credit' => 'not-a-boolean',
        ]);

    $response->assertRedirect(route('ledgers.settings.index', $ledger))
        ->assertSessionHasErrors([
            'name' => 'Please enter an account type name.',
            'is_credit' => 'Please specify whether this is a credit account type.',
        ]);
});

test('account type update saves through web routes', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->create([
        'name' => 'Old Type',
        'color' => '#111111',
        'is_credit' => false,
    ]);

    $response = $this->actingAs($user)
        ->from(route('ledgers.settings.index', $ledger))
        ->put(route('ledgers.settings.account-types.update', [$ledger, $accountType]), [
            'name' => 'Updated Type',
            'color' => '#abcdef',
            'is_credit' => true,
        ]);

    $response->assertRedirect(route('ledgers.settings.index', $ledger))
        ->assertSessionHasNoErrors()
        ->assertSessionHas('success', 'Account type updated.');

    expect($accountType->fresh())
        ->name->toBe('Updated Type')
        ->color->toBe('#abcdef')
        ->is_credit->toBeTrue();
});

test('unauthenticated users cannot access settings', function () {
    $ledger = Ledger::factory()->create();

    $this->get(route('ledgers.settings.index', $ledger))
        ->assertRedirect(route('login'));
});
