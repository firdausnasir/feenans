<?php

use App\Models\Ledger;
use App\Models\User;

test('free user isPremium returns false', function () {
    $user = User::factory()->create();

    expect($user->isPremium())->toBeFalse();
});

test('premium user isPremium returns true', function () {
    $user = User::factory()->create();
    $user->membership()->update(['tier' => 'premium', 'status' => 'active']);

    expect($user->fresh()->isPremium())->toBeTrue();
});

test('trialing user isPremium returns true', function () {
    $user = User::factory()->create();
    $user->membership()->update(['tier' => 'premium', 'status' => 'trialing']);

    expect($user->fresh()->isPremium())->toBeTrue();
});

test('canceled premium user isPremium returns false', function () {
    $user = User::factory()->create();
    $user->membership()->update(['tier' => 'premium', 'status' => 'canceled']);

    expect($user->fresh()->isPremium())->toBeFalse();
});

test('shared props include membership data for free user', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();

    $this->actingAs($user)
        ->get(route('ledgers.dashboard', $ledger))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->where('auth.user.membership.tier', 'free')
            ->where('auth.user.membership.is_premium', false)
        );
});

test('shared props include membership data for premium user', function () {
    $user = User::factory()->create();
    $user->membership()->update(['tier' => 'premium', 'status' => 'active']);
    $ledger = Ledger::factory()->for($user)->create();

    $this->actingAs($user)
        ->get(route('ledgers.dashboard', $ledger))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->where('auth.user.membership.tier', 'premium')
            ->where('auth.user.membership.is_premium', true)
        );
});

test('free user is redirected from reports to premium page', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();

    $this->actingAs($user)
        ->get(route('ledgers.reports.index', $ledger))
        ->assertRedirect(route('premium'));
});

test('premium user can access reports', function () {
    $user = User::factory()->create();
    $user->membership()->update(['tier' => 'premium', 'status' => 'active']);
    $ledger = Ledger::factory()->for($user)->create();

    $this->actingAs($user)
        ->get(route('ledgers.reports.index', $ledger))
        ->assertSuccessful();
});

test('free user is redirected from bills to premium page', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();

    $this->actingAs($user)
        ->get(route('ledgers.bills.index', $ledger))
        ->assertRedirect(route('premium'));
});

test('premium user can access bills', function () {
    $user = User::factory()->create();
    $user->membership()->update(['tier' => 'premium', 'status' => 'active']);
    $ledger = Ledger::factory()->for($user)->create();

    $this->actingAs($user)
        ->get(route('ledgers.bills.index', $ledger))
        ->assertSuccessful();
});

test('free user is redirected from budgets to premium page', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();

    $this->actingAs($user)
        ->get(route('ledgers.budgets.index', $ledger))
        ->assertRedirect(route('premium'));
});

test('premium user can access budgets', function () {
    $user = User::factory()->create();
    $user->membership()->update(['tier' => 'premium', 'status' => 'active']);
    $ledger = Ledger::factory()->for($user)->create();

    $this->actingAs($user)
        ->get(route('ledgers.budgets.index', $ledger))
        ->assertSuccessful();
});

test('premium page renders for authenticated user', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('premium'))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('premium/index')
        );
});
