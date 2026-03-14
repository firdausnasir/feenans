<?php

use App\Models\Account;
use App\Models\AccountType;
use App\Models\Bill;
use App\Models\Ledger;
use App\Models\User;
use App\Notifications\BillDueReminder;
use Inertia\Testing\AssertableInertia as Assert;

test('auth.user contains expected user fields', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('ledgers.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('auth.user.id')
            ->has('auth.user.name')
            ->has('auth.user.email')
            ->has('auth.user.onboarding_step')
            ->where('auth.user.id', $user->id)
            ->where('auth.user.email', $user->email)
        );
});

test('auth.user is null when unauthenticated', function () {
    $this->get('/')
        ->assertInertia(fn (Assert $page) => $page
            ->where('auth.user', null)
        );
});

test('flash prop is present with success and error keys', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('ledgers.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('flash')
            ->has('flash.success')
            ->has('flash.error')
        );
});

test('flash success message is shared from session', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->withSession(['success' => 'Ledger created!'])
        ->get(route('ledgers.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('flash.success', 'Ledger created!')
        );
});

test('flash error message is shared from session', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->withSession(['error' => 'Something went wrong.'])
        ->get(route('ledgers.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('flash.error', 'Something went wrong.')
        );
});

test('currentLedger is null on non-ledger routes', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('ledgers.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('currentLedger', null)
        );
});

test('currentLedger is set from route binding on ledger routes', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();

    $this->actingAs($user)
        ->get(route('ledgers.dashboard', $ledger))
        ->assertInertia(fn (Assert $page) => $page
            ->where('currentLedger.id', $ledger->id)
            ->missing('currentLedger.user_id')
            ->missing('currentLedger.uses_seeded_categories')
            ->has('currentLedger.cycle_start_day')
        );
});

test('availableLedgers contains all ledgers for the current user', function () {
    $user = User::factory()->create();
    Ledger::factory()->for($user)->count(3)->create();

    $this->actingAs($user)
        ->get(route('ledgers.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('availableLedgers', 3)
        );
});

test('availableLedgers is empty for unauthenticated user', function () {
    $this->get('/')
        ->assertInertia(fn (Assert $page) => $page
            ->has('availableLedgers', 0)
        );
});

test('availableLedgers does not include other users ledgers', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    Ledger::factory()->for($user)->count(2)->create();
    Ledger::factory()->for($otherUser)->count(5)->create();

    $this->actingAs($user)
        ->get(route('ledgers.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('availableLedgers', 2)
        );
});

test('unread notification count is shared for authenticated users', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create();
    $bill = Bill::factory()->for($ledger)->for($account)->create();

    $user->notifyNow(new BillDueReminder($bill));
    $user->notifyNow(new BillDueReminder($bill));

    $this->actingAs($user)
        ->get(route('ledgers.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('unread_notifications_count', 2)
        );
});
