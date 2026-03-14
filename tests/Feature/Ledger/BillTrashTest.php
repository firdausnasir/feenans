<?php

use App\Models\Account;
use App\Models\AccountType;
use App\Models\Bill;
use App\Models\Ledger;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('deleted bills appear in the trash view', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create();
    $bill = Bill::factory()->for($ledger)->for($account)->create([
        'name' => 'Archived bill',
    ]);

    $this->actingAs($user)
        ->delete(route('ledgers.bills.destroy', [$ledger, $bill]))
        ->assertRedirect(route('ledgers.bills.index', $ledger));

    $this->assertSoftDeleted('bills', ['id' => $bill->id]);

    $this->actingAs($user)
        ->get(route('ledgers.bills.trash', $ledger))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('ledgers/bills/trash/index')
            ->has('bills', 1)
            ->where('bills.0.id', $bill->id)
        );
});

test('users can restore a soft deleted bill', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create();
    $bill = Bill::factory()->for($ledger)->for($account)->trashed()->create();

    $this->actingAs($user)
        ->patch(route('ledgers.bills.restore', [$ledger, $bill]))
        ->assertRedirect(route('ledgers.bills.trash', $ledger));

    $this->assertNotSoftDeleted('bills', ['id' => $bill->id]);
});

test('users can permanently delete a soft deleted bill', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create();
    $bill = Bill::factory()->for($ledger)->for($account)->trashed()->create();

    $this->actingAs($user)
        ->delete(route('ledgers.bills.force-destroy', [$ledger, $bill]))
        ->assertRedirect(route('ledgers.bills.trash', $ledger));

    expect(Bill::withTrashed()->find($bill->id))->toBeNull();
});
