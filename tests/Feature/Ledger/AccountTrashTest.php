<?php

use App\Models\Account;
use App\Models\AccountType;
use App\Models\Ledger;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('deleted accounts appear in the trash view', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create([
        'name' => 'Old wallet',
    ]);

    $this->actingAs($user)
        ->delete(route('ledgers.accounts.destroy', [$ledger, $account]))
        ->assertRedirect(route('ledgers.accounts.index', $ledger));

    $this->assertSoftDeleted('accounts', ['id' => $account->id]);

    $this->actingAs($user)
        ->get(route('ledgers.accounts.trash', $ledger))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('ledgers/accounts/trash/index')
            ->has('accounts', 1)
            ->where('accounts.0.id', $account->id)
        );
});

test('users can restore a soft deleted account', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->trashed()->create();

    $this->actingAs($user)
        ->patch(route('ledgers.accounts.restore', [$ledger, $account]))
        ->assertRedirect(route('ledgers.accounts.trash', $ledger));

    $this->assertNotSoftDeleted('accounts', ['id' => $account->id]);
});

test('users can permanently delete a soft deleted account', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->trashed()->create();

    $this->actingAs($user)
        ->delete(route('ledgers.accounts.force-destroy', [$ledger, $account]))
        ->assertRedirect(route('ledgers.accounts.trash', $ledger));

    expect(Account::withTrashed()->find($account->id))->toBeNull();
});
