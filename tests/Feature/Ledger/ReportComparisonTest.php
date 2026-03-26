<?php

use App\Models\Ledger;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('report page renders with comparison query params', function () {
    $user = User::factory()->create();
    $user->membership()->update(['tier' => 'premium', 'status' => 'active']);
    $ledger = Ledger::factory()->for($user)->create(['cycle_start_day' => 1]);

    $response = $this
        ->actingAs($user)
        ->get(route('ledgers.reports.index', $ledger).'?date_from=2026-03-01&date_to=2026-03-31&compare_start=2026-02-01&compare_end=2026-02-28');

    $response->assertInertia(fn (Assert $page) => $page
        ->component('ledgers/reports/index')
    );
});
