<?php

use App\Models\Ledger;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('dashboard page still renders the paired card data needed for desktop layout sections', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();

    $this->actingAs($user)
        ->get(route('ledgers.dashboard', $ledger))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('ledgers/dashboard')
            ->has('upcomingBills')
            ->has('dailyExpenseTrend')
            ->has('accounts')
            ->has('topCategories')
        );
});
