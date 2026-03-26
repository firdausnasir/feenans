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
