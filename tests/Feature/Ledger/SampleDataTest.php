<?php

use App\Models\Account;
use App\Models\Bill;
use App\Models\Ledger;
use App\Models\Payee;
use App\Models\Transaction;
use App\Models\User;
use App\Services\LedgerSetupService;
use App\Services\SampleDataService;

function createLedgerWithSetup(User $user): Ledger
{
    return app(LedgerSetupService::class)->createForUser($user, [
        'name' => 'Test Ledger',
        'currency_code' => 'MYR',
        'uses_seeded_categories' => true,
    ]);
}

test('sample data generation creates expected records', function () {
    $user = User::factory()->create();
    $ledger = createLedgerWithSetup($user);

    app(SampleDataService::class)->generate($ledger);

    expect(Account::where('ledger_id', $ledger->id)->where('is_sample', true)->count())->toBe(2)
        ->and(Payee::where('ledger_id', $ledger->id)->where('is_sample', true)->count())->toBe(10)
        ->and(Transaction::where('ledger_id', $ledger->id)->where('is_sample', true)->count())->toBeGreaterThanOrEqual(30)
        ->and(Bill::where('ledger_id', $ledger->id)->where('is_sample', true)->count())->toBe(3);
});

test('all generated records are flagged as sample data', function () {
    $user = User::factory()->create();
    $ledger = createLedgerWithSetup($user);

    app(SampleDataService::class)->generate($ledger);

    $nonSampleAccounts = Account::where('ledger_id', $ledger->id)->where('is_sample', false)->count();
    $nonSampleTransactions = Transaction::where('ledger_id', $ledger->id)->where('is_sample', false)->count();

    // No non-sample accounts or transactions should exist since we only ran sample data
    expect($nonSampleAccounts)->toBe(0)
        ->and($nonSampleTransactions)->toBe(0);
});

test('sample data removal cleans up all flagged records', function () {
    $user = User::factory()->create();
    $ledger = createLedgerWithSetup($user);
    $service = app(SampleDataService::class);

    $service->generate($ledger);

    expect($service->hasSampleData($ledger))->toBeTrue();

    $service->remove($ledger);

    expect(Account::where('ledger_id', $ledger->id)->where('is_sample', true)->count())->toBe(0)
        ->and(Transaction::where('ledger_id', $ledger->id)->where('is_sample', true)->count())->toBe(0)
        ->and(Bill::where('ledger_id', $ledger->id)->where('is_sample', true)->count())->toBe(0)
        ->and(Payee::where('ledger_id', $ledger->id)->where('is_sample', true)->count())->toBe(0)
        ->and($service->hasSampleData($ledger))->toBeFalse();
});

test('non-sample data is not affected by removal', function () {
    $user = User::factory()->create();
    $ledger = createLedgerWithSetup($user);
    $service = app(SampleDataService::class);

    // Create real user data
    $accountType = $ledger->accountTypes()->first();
    $realAccount = $ledger->accounts()->create([
        'account_type_id' => $accountType->id,
        'name' => 'My Real Account',
        'initial_balance' => 1000,
        'include_in_totals' => true,
        'is_sample' => false,
    ]);

    $category = $ledger->categories()->first();
    $realTransaction = $ledger->transactions()->create([
        'account_id' => $realAccount->id,
        'category_id' => $category->id,
        'transaction_type' => 'expense',
        'amount' => -50.00,
        'description' => 'Real expense',
        'transaction_date' => now(),
        'is_sample' => false,
    ]);

    $realPayee = $ledger->payees()->create([
        'name' => 'My Real Payee',
        'is_sample' => false,
    ]);

    // Generate and then remove sample data
    $service->generate($ledger);
    $service->remove($ledger);

    // Real data should still exist
    expect(Account::find($realAccount->id))->not->toBeNull()
        ->and(Transaction::find($realTransaction->id))->not->toBeNull()
        ->and(Payee::find($realPayee->id))->not->toBeNull();
});

test('hasSampleData returns false for ledger without sample data', function () {
    $user = User::factory()->create();
    $ledger = createLedgerWithSetup($user);

    expect(app(SampleDataService::class)->hasSampleData($ledger))->toBeFalse();
});

test('hasSampleData returns true after generating sample data', function () {
    $user = User::factory()->create();
    $ledger = createLedgerWithSetup($user);

    app(SampleDataService::class)->generate($ledger);

    expect(app(SampleDataService::class)->hasSampleData($ledger))->toBeTrue();
});

test('store route generates sample data', function () {
    $user = User::factory()->create();
    $ledger = createLedgerWithSetup($user);

    $this->actingAs($user)
        ->post(route('ledgers.sample-data.store', $ledger))
        ->assertRedirect();

    expect(Account::where('ledger_id', $ledger->id)->where('is_sample', true)->count())->toBe(2)
        ->and(Transaction::where('ledger_id', $ledger->id)->where('is_sample', true)->count())->toBeGreaterThanOrEqual(30);
});

test('store route rejects when sample data already exists', function () {
    $user = User::factory()->create();
    $ledger = createLedgerWithSetup($user);

    app(SampleDataService::class)->generate($ledger);

    $this->actingAs($user)
        ->post(route('ledgers.sample-data.store', $ledger))
        ->assertRedirect()
        ->assertSessionHas('error', 'Sample data already exists.');
});

test('destroy route removes sample data', function () {
    $user = User::factory()->create();
    $ledger = createLedgerWithSetup($user);

    app(SampleDataService::class)->generate($ledger);

    $this->actingAs($user)
        ->delete(route('ledgers.sample-data.destroy', $ledger))
        ->assertRedirect()
        ->assertSessionHas('success', 'Sample data removed successfully.');

    expect(Account::where('ledger_id', $ledger->id)->where('is_sample', true)->count())->toBe(0)
        ->and(Transaction::where('ledger_id', $ledger->id)->where('is_sample', true)->count())->toBe(0);
});

test('unauthorized user cannot load sample data', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $ledger = createLedgerWithSetup($user);

    $this->actingAs($otherUser)
        ->post(route('ledgers.sample-data.store', $ledger))
        ->assertForbidden();
});

test('unauthorized user cannot remove sample data', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $ledger = createLedgerWithSetup($user);

    app(SampleDataService::class)->generate($ledger);

    $this->actingAs($otherUser)
        ->delete(route('ledgers.sample-data.destroy', $ledger))
        ->assertForbidden();
});

test('unauthenticated user cannot access sample data routes', function () {
    $user = User::factory()->create();
    $ledger = createLedgerWithSetup($user);

    $this->post(route('ledgers.sample-data.store', $ledger))
        ->assertRedirect(route('login'));

    $this->delete(route('ledgers.sample-data.destroy', $ledger))
        ->assertRedirect(route('login'));
});

test('settings page renders successfully for ledger with sample data', function () {
    $user = User::factory()->create();
    $ledger = createLedgerWithSetup($user);

    $this->actingAs($user)
        ->get(route('ledgers.settings.index', $ledger))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('ledgers/settings/index')
        );
});
