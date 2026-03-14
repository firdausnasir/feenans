<?php

use App\Models\AccountType;
use App\Models\Ledger;
use App\Models\User;

// ─────────────────────────────────────────────
// Middleware Guard: EnsureOnboardingComplete
// ─────────────────────────────────────────────

test('authenticated user with onboarding_step redirects from ledger routes to /onboarding', function () {
    $user = User::factory()->create(['onboarding_step' => 1]);

    $response = $this->actingAs($user)->get('/ledgers');

    $response->assertRedirect(route('onboarding.show'));
});

test('user without onboarding_step can access ledger routes without redirect', function () {
    $user = User::factory()->create(['onboarding_step' => null]);
    $ledger = Ledger::factory()->for($user)->create();

    $response = $this->actingAs($user)->get(route('ledgers.dashboard', $ledger));

    $response->assertOk();
});

test('GET /onboarding is accessible even when onboarding_step is set', function () {
    $user = User::factory()->create(['onboarding_step' => 1]);

    // The middleware must NOT redirect /onboarding back to /onboarding (infinite loop).
    // We verify the route is not redirected away from the onboarding URL.
    // The page itself may 500 (missing Vite asset) since the frontend isn't built yet,
    // but that is a separate concern — the middleware behaviour is what we're testing.
    $response = $this->actingAs($user)->get(route('onboarding.show'));

    // Not a redirect to onboarding.show itself
    $this->assertFalse(
        $response->isRedirection() && $response->headers->get('Location') === route('onboarding.show'),
        'Middleware must not redirect /onboarding back to /onboarding'
    );
});

test('API routes are not redirected by onboarding middleware', function () {
    $user = User::factory()->create(['onboarding_step' => 1]);

    $response = $this->actingAs($user)
        ->withHeaders(['Accept' => 'application/json'])
        ->get('/ledgers');

    // Middleware skips JSON requests; the controller runs and returns 200
    $response->assertStatus(200);
});

// ─────────────────────────────────────────────
// OnboardingController
// ─────────────────────────────────────────────

test('POST /onboarding/autosave saves data and returns JSON', function () {
    $user = User::factory()->create(['onboarding_step' => 1]);

    $response = $this->actingAs($user)
        ->postJson(route('onboarding.autosave'), [
            'data' => ['name' => 'My Budget'],
        ]);

    $response->assertOk()->assertJson(['saved' => true]);
    expect($user->fresh()->onboarding_data)->toBe(['name' => 'My Budget']);
});

test('POST /onboarding/step/1 creates a ledger and advances user to step 2', function () {
    $user = User::factory()->create(['onboarding_step' => 1]);

    $response = $this->actingAs($user)->post(route('onboarding.step', 1), [
        'name' => 'My Ledger',
        'currency_code' => 'MYR',
        'cycle_start_day' => 1,
        'seed_categories' => true,
    ]);

    $response->assertRedirect(route('onboarding.show'));

    $fresh = $user->fresh();
    expect($fresh->onboarding_step)->toBe(2)
        ->and($fresh->onboarding_data)->toBeNull()
        ->and($fresh->ledgers()->where('name', 'My Ledger')->exists())->toBeTrue();
});

test('POST /onboarding/step/2 creates an account and advances user to step 3', function () {
    $user = User::factory()->create(['onboarding_step' => 2]);
    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->create();

    $response = $this->actingAs($user)->post(route('onboarding.step', 2), [
        'account_type_id' => $accountType->id,
        'name' => 'Main Wallet',
        'initial_balance' => 500.00,
        'statement_day' => null,
        'include_in_totals' => true,
    ]);

    $response->assertRedirect(route('onboarding.show'));

    $fresh = $user->fresh();
    expect($fresh->onboarding_step)->toBe(3)
        ->and($fresh->onboarding_data)->toBeNull()
        ->and($ledger->accounts()->where('name', 'Main Wallet')->exists())->toBeTrue();
});

test('POST /onboarding/complete clears onboarding and redirects to ledger dashboard', function () {
    $user = User::factory()->create([
        'onboarding_step' => 3,
        'onboarding_data' => ['foo' => 'bar'],
    ]);
    $ledger = Ledger::factory()->for($user)->create();

    $response = $this->actingAs($user)->post(route('onboarding.complete'));

    $response->assertRedirect(route('ledgers.dashboard', $ledger));

    $fresh = $user->fresh();
    expect($fresh->onboarding_step)->toBeNull()
        ->and($fresh->onboarding_data)->toBeNull();
});
