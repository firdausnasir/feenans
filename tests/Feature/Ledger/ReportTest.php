<?php

use App\Models\Ledger;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('report page renders successfully', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create(['cycle_start_day' => 1]);

    $response = $this
        ->actingAs($user)
        ->get(route('ledgers.reports.index', $ledger));

    $response->assertInertia(fn (Assert $page) => $page
        ->component('ledgers/reports/index')
    );
});

test('financial health report page renders successfully', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();

    $response = $this
        ->actingAs($user)
        ->get(route('ledgers.reports.financial-health', $ledger));

    $response->assertInertia(fn (Assert $page) => $page
        ->component('ledgers/reports/financial-health')
    );
});

test('budget performance report page renders successfully', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();

    $response = $this
        ->actingAs($user)
        ->get(route('ledgers.reports.budget-performance', $ledger));

    $response->assertInertia(fn (Assert $page) => $page
        ->component('ledgers/reports/budget-performance')
    );
});

test('cash flow report page renders successfully', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();

    $response = $this
        ->actingAs($user)
        ->get(route('ledgers.reports.cash-flow', $ledger));

    $response->assertInertia(fn (Assert $page) => $page
        ->component('ledgers/reports/cash-flow')
    );
});

test('another user cannot view reports', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $ledger = Ledger::factory()->for($owner)->create();

    $this->actingAs($other)
        ->get(route('ledgers.reports.index', $ledger))
        ->assertForbidden();
});
