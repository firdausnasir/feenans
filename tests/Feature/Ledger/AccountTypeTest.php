<?php

use App\Models\Ledger;
use App\Models\User;

test('users can create custom account types in a ledger', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();

    $response = $this
        ->actingAs($user)
        ->from(route('ledgers.settings.index', $ledger))
        ->post(route('ledgers.settings.account-types.store', $ledger), [
            'name' => 'Crypto Wallet',
            'color' => '#22c55e',
            'is_credit' => false,
        ]);

    $response->assertRedirect(route('ledgers.settings.index', $ledger))
        ->assertSessionHasNoErrors();

    expect($ledger->accountTypes()->where('name', 'Crypto Wallet')->exists())->toBeTrue();
});
