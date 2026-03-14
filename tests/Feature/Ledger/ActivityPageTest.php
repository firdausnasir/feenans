<?php

use App\Models\Account;
use App\Models\AccountType;
use App\Models\Ledger;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('ledger activity page renders recent activity', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create();

    $this->actingAs($user)
        ->post(route('ledgers.transactions.store', $ledger), [
            'account_id' => $account->id,
            'transaction_type' => 'expense',
            'amount' => 10,
            'description' => 'Coffee',
            'transaction_date' => '2026-03-13',
        ])
        ->assertRedirect();

    $this->actingAs($user)
        ->get(route('ledgers.activity.index', $ledger))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('ledgers/activity/index')
            ->has('activity')
            ->where('activity.0.action', 'created')
        );
});
