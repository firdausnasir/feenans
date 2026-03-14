<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('ledger pages preserve shared inertia props', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->get(route('ledgers.index'));

    $response->assertInertia(fn (Assert $page) => $page
        ->where('name', config('app.name'))
        ->has('auth.user')
        ->has('sidebarOpen')
        ->etc()
    );
});
