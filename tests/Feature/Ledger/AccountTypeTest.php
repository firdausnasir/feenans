<?php

use App\Models\Ledger;
use App\Models\User;

test('users can create custom account types in a ledger', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();

    $response = $this
        ->actingAs($user)
        ->post(route('api.v1.ledgers.account-types.store', $ledger), [
            'name' => 'Crypto Wallet',
            'color' => '#22c55e',
            'is_credit' => false,
        ]);

    $response->assertStatus(201);

    expect($ledger->accountTypes()->where('name', 'Crypto Wallet')->exists())->toBeTrue();
});
