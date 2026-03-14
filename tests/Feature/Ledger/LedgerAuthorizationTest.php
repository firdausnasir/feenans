<?php

use App\Models\Ledger;
use App\Models\User;

test('users cannot view another users ledger dashboard', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $ledger = Ledger::factory()->for($owner)->create();

    $response = $this
        ->actingAs($intruder)
        ->get(route('ledgers.dashboard', $ledger));

    $response->assertForbidden();
});
