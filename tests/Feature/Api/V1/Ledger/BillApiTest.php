<?php

use App\Models\Account;
use App\Models\AccountType;
use App\Models\Bill;
use App\Models\Category;
use App\Models\Ledger;
use App\Models\User;
use Carbon\CarbonImmutable;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    config()->set('app.paywall_enabled', true);
});

test('token authenticated premium client can list bills for a ledger', function () {
    $user = User::factory()->create();
    $user->membership()->update(['tier' => 'premium', 'status' => 'active']);
    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create();
    Bill::factory()->for($ledger)->for($account)->create(['name' => 'Main Bill']);

    Sanctum::actingAs($user, ['*']);

    $this->getJson(route('api.v1.ledgers.bills.index', $ledger))
        ->assertSuccessful()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Main Bill')
        ->assertJsonPath('data.0.ledger_id', $ledger->id);
});

test('bill api dashboard upcoming loader groups missed due and upcoming bills', function () {
    $user = User::factory()->create();
    $user->membership()->update(['tier' => 'premium', 'status' => 'active']);
    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create();

    Bill::factory()->for($ledger)->for($account)->create([
        'name' => 'Missed bill',
        'next_due_date' => CarbonImmutable::today()->subDay(),
        'is_active' => true,
    ]);

    Bill::factory()->for($ledger)->for($account)->create([
        'name' => 'Due bill',
        'next_due_date' => CarbonImmutable::today(),
        'is_active' => true,
    ]);

    Bill::factory()->for($ledger)->for($account)->create([
        'name' => 'Upcoming bill',
        'next_due_date' => CarbonImmutable::today()->addDays(2),
        'is_active' => true,
    ]);

    Bill::factory()->for($ledger)->for($account)->create([
        'name' => 'Inactive bill',
        'next_due_date' => CarbonImmutable::today()->addDay(),
        'is_active' => false,
    ]);

    Sanctum::actingAs($user, ['*']);

    $response = $this->getJson(route('api.v1.ledgers.bills.dashboard-upcoming', $ledger));

    $response->assertSuccessful()
        ->assertJsonCount(1, 'data.missed')
        ->assertJsonCount(1, 'data.due')
        ->assertJsonCount(1, 'data.upcoming')
        ->assertJsonPath('data.missed.0.name', 'Missed bill')
        ->assertJsonPath('data.due.0.name', 'Due bill')
        ->assertJsonPath('data.upcoming.0.name', 'Upcoming bill');

    $returnedNames = collect(['missed', 'due', 'upcoming'])
        ->flatMap(fn (string $group) => collect($response->json("data.{$group}"))->pluck('name'));

    expect($returnedNames)->not->toContain('Inactive bill');
});

test('bill api create returns validation errors as json', function () {
    $user = User::factory()->create();
    $user->membership()->update(['tier' => 'premium', 'status' => 'active']);
    $ledger = Ledger::factory()->for($user)->create();

    Sanctum::actingAs($user, ['*']);

    $this->postJson(route('api.v1.ledgers.bills.store', $ledger), [
        'name' => '',
        'transaction_type' => '',
        'amount' => 0,
        'recurrence_type' => '',
        'next_due_date' => '',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'name',
            'transaction_type',
            'amount',
            'account_id',
            'recurrence_type',
            'recurrence_interval',
            'next_due_date',
        ]);
});

test('bill api create returns created bill contract', function () {
    $user = User::factory()->create();
    $user->membership()->update(['tier' => 'premium', 'status' => 'active']);
    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create();
    $category = Category::factory()->for($ledger)->create();

    Sanctum::actingAs($user, ['*']);

    $response = $this->postJson(route('api.v1.ledgers.bills.store', $ledger), [
        'name' => 'Internet',
        'transaction_type' => 'expense',
        'amount' => 50.00,
        'account_id' => $account->id,
        'category_id' => $category->id,
        'payee_id' => null,
        'new_payee_name' => 'ISP',
        'recurrence_type' => 'monthly',
        'recurrence_interval' => 1,
        'recurrence_day' => null,
        'next_due_date' => '2026-05-01',
        'auto_create' => false,
        'end_type' => null,
        'end_date' => null,
        'end_after_occurrences' => null,
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.ledger_id', $ledger->id)
        ->assertJsonPath('data.name', 'Internet')
        ->assertJsonPath('data.amount', '50.00')
        ->assertJsonPath('data.payee.name', 'ISP');

    expect($ledger->payees()->where('name', 'ISP')->exists())->toBeTrue();
});

test('bill api create rejects related ids from another ledger', function () {
    $user = User::factory()->create();
    $user->membership()->update(['tier' => 'premium', 'status' => 'active']);
    $ledger = Ledger::factory()->for($user)->create();

    $foreignLedger = Ledger::factory()->create();
    $foreignAccountType = AccountType::factory()->for($foreignLedger)->create();
    $foreignAccount = Account::factory()->for($foreignLedger)->for($foreignAccountType)->create();
    $foreignCategory = Category::factory()->for($foreignLedger)->create();

    Sanctum::actingAs($user, ['*']);

    $this->postJson(route('api.v1.ledgers.bills.store', $ledger), [
        'name' => 'Blocked',
        'transaction_type' => 'expense',
        'amount' => 120.00,
        'account_id' => $foreignAccount->id,
        'category_id' => $foreignCategory->id,
        'recurrence_type' => 'monthly',
        'recurrence_interval' => 1,
        'next_due_date' => '2026-04-01',
        'auto_create' => false,
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['account_id', 'category_id']);
});

test('bill api update returns updated bill json', function () {
    $user = User::factory()->create();
    $user->membership()->update(['tier' => 'premium', 'status' => 'active']);
    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create();
    $bill = Bill::factory()->for($ledger)->for($account)->create(['name' => 'Old Name']);

    Sanctum::actingAs($user, ['*']);

    $response = $this->patchJson(route('api.v1.ledgers.bills.update', [$ledger, $bill]), [
        'name' => 'New Name',
        'amount' => 99.50,
    ]);

    $response->assertSuccessful()
        ->assertJsonPath('data.id', $bill->id)
        ->assertJsonPath('data.name', 'New Name')
        ->assertJsonPath('data.amount', '99.50');

    expect($bill->fresh()->name)->toBe('New Name');
});

test('bill api delete returns deleted bill json', function () {
    $user = User::factory()->create();
    $user->membership()->update(['tier' => 'premium', 'status' => 'active']);
    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create();
    $bill = Bill::factory()->for($ledger)->for($account)->create(['name' => 'Delete Me']);

    Sanctum::actingAs($user, ['*']);

    $this->deleteJson(route('api.v1.ledgers.bills.destroy', [$ledger, $bill]))
        ->assertSuccessful()
        ->assertJsonPath('data.id', $bill->id)
        ->assertJsonPath('data.name', 'Delete Me');

    expect(Bill::query()->whereKey($bill->id)->exists())->toBeFalse();
});

test('bill api toggle returns updated bill json', function () {
    $user = User::factory()->create();
    $user->membership()->update(['tier' => 'premium', 'status' => 'active']);
    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create();
    $bill = Bill::factory()->for($ledger)->for($account)->create(['is_active' => true]);

    Sanctum::actingAs($user, ['*']);

    $this->patchJson(route('api.v1.ledgers.bills.toggle', [$ledger, $bill]))
        ->assertSuccessful()
        ->assertJsonPath('data.id', $bill->id)
        ->assertJsonPath('data.is_active', false);

    expect($bill->fresh()->is_active)->toBeFalse();
});

test('bill api pay returns updated bill json and creates a transaction', function () {
    $user = User::factory()->create();
    $user->membership()->update(['tier' => 'premium', 'status' => 'active']);
    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create();
    $bill = Bill::factory()->for($ledger)->for($account)->create([
        'name' => 'Water',
        'next_due_date' => '2026-04-01',
        'recurrence_type' => 'monthly',
        'recurrence_interval' => 1,
        'amount' => 42.00,
    ]);

    Sanctum::actingAs($user, ['*']);

    $this->postJson(route('api.v1.ledgers.bills.pay', [$ledger, $bill]))
        ->assertSuccessful()
        ->assertJsonPath('data.id', $bill->id)
        ->assertJsonPath('data.occurrences_count', 1)
        ->assertJsonPath('data.next_due_date', '2026-05-01');

    expect($ledger->transactions()->where('bill_id', $bill->id)->count())->toBe(1);
});

test('bill api returns json forbidden when ledger policy denies access', function () {
    $owner = User::factory()->create();
    $owner->membership()->update(['tier' => 'premium', 'status' => 'active']);
    $outsider = User::factory()->create();
    $outsider->membership()->update(['tier' => 'premium', 'status' => 'active']);
    $ledger = Ledger::factory()->for($owner)->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create();
    $bill = Bill::factory()->for($ledger)->for($account)->create();

    Sanctum::actingAs($outsider, ['*']);

    $this->getJson(route('api.v1.ledgers.bills.index', $ledger))
        ->assertForbidden();

    $this->postJson(route('api.v1.ledgers.bills.store', $ledger), [
        'name' => 'Blocked',
        'transaction_type' => 'expense',
        'amount' => 25.00,
        'account_id' => $account->id,
        'recurrence_type' => 'monthly',
        'recurrence_interval' => 1,
        'next_due_date' => '2026-04-01',
        'auto_create' => false,
    ])->assertForbidden();

    $this->patchJson(route('api.v1.ledgers.bills.update', [$ledger, $bill]), [
        'name' => 'Blocked',
    ])->assertForbidden();

    $this->patchJson(route('api.v1.ledgers.bills.toggle', [$ledger, $bill]))
        ->assertForbidden();

    $this->postJson(route('api.v1.ledgers.bills.pay', [$ledger, $bill]))
        ->assertForbidden();

    $this->deleteJson(route('api.v1.ledgers.bills.destroy', [$ledger, $bill]))
        ->assertForbidden();
});

test('bill api rejects guest requests', function () {
    $user = User::factory()->create();
    $user->membership()->update(['tier' => 'premium', 'status' => 'active']);
    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create();
    $bill = Bill::factory()->for($ledger)->for($account)->create();

    $this->getJson(route('api.v1.ledgers.bills.index', $ledger))
        ->assertUnauthorized();

    $this->postJson(route('api.v1.ledgers.bills.store', $ledger), [
        'name' => 'Guest',
        'transaction_type' => 'expense',
        'amount' => 25.00,
        'account_id' => $account->id,
        'recurrence_type' => 'monthly',
        'recurrence_interval' => 1,
        'next_due_date' => '2026-04-01',
        'auto_create' => false,
    ])->assertUnauthorized();

    $this->patchJson(route('api.v1.ledgers.bills.update', [$ledger, $bill]), [
        'name' => 'Guest',
    ])->assertUnauthorized();

    $this->patchJson(route('api.v1.ledgers.bills.toggle', [$ledger, $bill]))
        ->assertUnauthorized();

    $this->postJson(route('api.v1.ledgers.bills.pay', [$ledger, $bill]))
        ->assertUnauthorized();

    $this->deleteJson(route('api.v1.ledgers.bills.destroy', [$ledger, $bill]))
        ->assertUnauthorized();
});

test('free token authenticated client cannot access the bill api', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();

    Sanctum::actingAs($user, ['*']);

    $this->getJson(route('api.v1.ledgers.bills.index', $ledger))
        ->assertForbidden();
});
