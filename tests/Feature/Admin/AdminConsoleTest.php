<?php

use App\Models\DailyPageAnalytics;
use App\Models\Ledger;
use App\Models\MembershipChangeLog;
use App\Models\User;
use Illuminate\Support\Carbon;
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

    DailyPageAnalytics::query()->create([
        'metric_date' => now()->toDateString(),
        'page_key' => 'home',
        'audience' => 'guest',
        'membership_tier' => 'none',
        'hits' => 4,
    ]);

    DailyPageAnalytics::query()->create([
        'metric_date' => now()->toDateString(),
        'page_key' => 'profile.edit',
        'audience' => 'authenticated',
        'membership_tier' => 'premium',
        'hits' => 3,
    ]);

    $response = $this->actingAs($admin)->getJson(route('admin.overview'));

    $response->assertOk()
        ->assertJsonPath('users.total', 3)
        ->assertJsonPath('users.verified', 3)
        ->assertJsonPath('memberships.by_tier.free', 2)
        ->assertJsonPath('memberships.by_tier.premium', 1)
        ->assertJsonPath('analytics.today_hits', 7)
        ->assertJsonPath('analytics.last_30_days_hits', 7)
        ->assertJsonMissingPath('ledgers')
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

test('analytics middleware stores aggregate page hits by route name only', function () {
    Carbon::setTestNow('2026-03-26 08:00:00');

    $user = User::factory()->create();
    $user->membership()->update([
        'tier' => 'premium',
        'status' => 'active',
    ]);
    $ledger = Ledger::factory()->for($user)->create();

    $this->actingAs($user)
        ->get(route('ledgers.dashboard', $ledger).'?view=month')
        ->assertOk();

    $this->actingAs($user)
        ->get(route('ledgers.dashboard', $ledger).'?view=year')
        ->assertOk();

    $analytics = DailyPageAnalytics::query()->firstOrFail();

    expect(DailyPageAnalytics::query()->count())->toBe(1)
        ->and($analytics->metric_date->toDateString())->toBe('2026-03-26')
        ->and($analytics->page_key)->toBe('ledgers.dashboard')
        ->and($analytics->page_key)->not->toContain((string) $ledger->id)
        ->and($analytics->page_key)->not->toContain('?')
        ->and($analytics->audience)->toBe('authenticated')
        ->and($analytics->membership_tier)->toBe('premium')
        ->and($analytics->hits)->toBe(2);
});

test('analytics middleware ignores admin and api requests', function () {
    Carbon::setTestNow('2026-03-26 08:00:00');

    $admin = User::factory()->create(['is_admin' => true]);

    $this->get(route('home'))->assertOk();

    $this->actingAs($admin)
        ->get(route('admin.index'))
        ->assertOk();

    $this->actingAs($admin)
        ->getJson(route('admin.overview'))
        ->assertOk();

    expect(DailyPageAnalytics::query()->count())->toBe(1);

    $analytics = DailyPageAnalytics::query()->firstOrFail();

    expect($analytics->page_key)->toBe('home')
        ->and($analytics->audience)->toBe('guest')
        ->and($analytics->membership_tier)->toBe('none')
        ->and($analytics->hits)->toBe(1);
});
