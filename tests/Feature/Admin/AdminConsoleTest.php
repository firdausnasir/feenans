<?php

use App\Models\Ledger;
use App\Models\MembershipChangeLog;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Fortify\Features;

test('non-admin users cannot access the admin console or admin api', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('admin.index'))
        ->assertForbidden();

    $this->actingAs($user)
        ->getJson(route('admin.overview'))
        ->assertForbidden();
});

test('admin users can view the admin page shell', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    $this->actingAs($admin)
        ->get(route('admin.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/index')
            ->where('auth.user.is_admin', true)
            ->where('currentLedger', null)
            ->etc()
        );
});

test('admin overview returns aggregate counts without ledger data', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $freeUser = User::factory()->create();
    $premiumUser = User::factory()->create();

    $premiumUser->membership()->update([
        'tier' => 'premium',
        'status' => 'active',
    ]);

    Ledger::factory()->for($premiumUser)->create([
        'name' => 'Private Household Ledger',
    ]);

    $response = $this->actingAs($admin)->getJson(route('admin.overview'));

    $response->assertOk()
        ->assertJsonPath('users.total', 3)
        ->assertJsonPath('users.verified', 3)
        ->assertJsonPath('memberships.by_tier.free', 2)
        ->assertJsonPath('memberships.by_tier.premium', 1)
        ->assertJsonMissingPath('analytics')
        ->assertJsonMissing(['Private Household Ledger']);
});

test('admin can filter the user membership list', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $freeUser = User::factory()->create([
        'name' => 'Free User',
        'email' => 'free@example.com',
    ]);
    $premiumUser = User::factory()->create([
        'name' => 'Premium Member',
        'email' => 'premium@example.com',
    ]);

    $premiumUser->membership()->update([
        'tier' => 'premium',
        'status' => 'trialing',
    ]);

    $response = $this->actingAs($admin)->getJson(route('admin.users.index', [
        'tier' => 'premium',
        'search' => 'premium',
    ]));

    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.email', 'premium@example.com')
        ->assertJsonPath('data.0.membership.tier', 'premium')
        ->assertJsonPath('data.0.membership.status', 'trialing')
        ->assertJsonPath('filters.tier', 'premium')
        ->assertJsonPath('filters.search', 'premium');

    expect($response->json('data.0.id'))->not->toBe($freeUser->id);
});

test('admin membership updates are persisted and audited', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $user = User::factory()->create([
        'name' => 'Member To Upgrade',
    ]);

    $this->actingAs($admin)
        ->patchJson(route('admin.users.membership.update', $user), [
            'tier' => 'premium',
            'status' => 'trialing',
            'reason' => 'Manual upgrade for launch cohort',
        ])
        ->assertOk()
        ->assertJsonPath('membership.tier', 'premium')
        ->assertJsonPath('membership.status', 'trialing');

    expect($user->refresh()->membership)
        ->tier->toBe('premium')
        ->status->toBe('trialing');

    expect(MembershipChangeLog::query()->where([
        'user_id' => $user->id,
        'changed_by_user_id' => $admin->id,
        'previous_tier' => 'free',
        'previous_status' => 'active',
        'new_tier' => 'premium',
        'new_status' => 'trialing',
    ])->exists())->toBeTrue();
});

test('new registrations create a default free active membership', function () {
    $this->skipUnlessFortifyFeature(Features::registration());

    $this->post(route('register.store'), [
        'name' => 'Membership Test User',
        'email' => 'membership-test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertRedirect(route('dashboard', absolute: false));

    $user = User::query()->where('email', 'membership-test@example.com')->firstOrFail();

    expect($user->membership)
        ->not->toBeNull()
        ->tier->toBe('free')
        ->status->toBe('active');
});
