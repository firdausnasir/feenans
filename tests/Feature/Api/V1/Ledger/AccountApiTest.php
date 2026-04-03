<?php

use App\Models\Account;
use App\Models\AccountType;
use App\Models\Ledger;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    config()->set('app.paywall_enabled', true);
});

test('token authenticated client can list accounts for a ledger', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->create(['name' => 'Checking']);
    Account::factory()->for($ledger)->for($accountType)->create(['name' => 'Main Account']);

    Sanctum::actingAs($user, ['*']);

    $response = $this->getJson(route('api.v1.ledgers.accounts.index', $ledger));

    $response->assertSuccessful()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Main Account')
        ->assertJsonPath('data.0.ledger_id', $ledger->id);
});

test('token authenticated client can load grouped account page data', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->create(['name' => 'Checking']);

    Account::factory()->for($ledger)->for($accountType)->create([
        'name' => 'Checking',
        'include_in_totals' => true,
        'initial_balance' => 1000,
    ]);

    Account::factory()->for($ledger)->for($accountType)->create([
        'name' => 'Rainy Day',
        'include_in_totals' => false,
        'initial_balance' => 5000,
    ]);

    Sanctum::actingAs($user, ['*']);

    $response = $this->getJson(route('api.v1.ledgers.accounts.grouped', $ledger));

    $response->assertSuccessful()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.group', 'included')
        ->assertJsonPath('data.0.label', 'Included in totals')
        ->assertJsonPath('data.0.accounts.0.name', 'Checking')
        ->assertJsonPath('data.0.total_balance', '1000.00')
        ->assertJsonPath('data.1.group', 'excluded')
        ->assertJsonPath('data.1.label', 'Savings')
        ->assertJsonPath('data.1.accounts.0.name', 'Rainy Day')
        ->assertJsonPath('data.1.total_balance', '5000.00');
});

test('token authenticated client can load account types for the accounts page', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();

    $checking = AccountType::factory()->for($ledger)->create([
        'name' => 'Checking',
        'position' => 1,
    ]);

    $creditCard = AccountType::factory()->for($ledger)->credit()->create([
        'name' => 'Credit Card',
        'position' => 2,
    ]);

    Sanctum::actingAs($user, ['*']);

    $response = $this->getJson(route('api.v1.ledgers.accounts.types', $ledger));

    $response->assertSuccessful()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.id', $checking->id)
        ->assertJsonPath('data.0.name', 'Checking')
        ->assertJsonPath('data.0.is_credit', false)
        ->assertJsonPath('data.1.id', $creditCard->id)
        ->assertJsonPath('data.1.name', 'Credit Card')
        ->assertJsonPath('data.1.is_credit', true);
});

test('token authenticated client can load net worth for the accounts page', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $assetType = AccountType::factory()->for($ledger)->create(['name' => 'Checking']);
    $creditType = AccountType::factory()->for($ledger)->credit()->create(['name' => 'Credit Card']);

    Account::factory()->for($ledger)->for($assetType)->create([
        'initial_balance' => 1000,
    ]);

    Account::factory()->for($ledger)->for($creditType)->create([
        'initial_balance' => -250,
    ]);

    Sanctum::actingAs($user, ['*']);

    $response = $this->getJson(route('api.v1.ledgers.accounts.net-worth', $ledger));

    $response->assertSuccessful()
        ->assertJsonPath('data.assets', 1000.0)
        ->assertJsonPath('data.liabilities', -250.0)
        ->assertJsonPath('data.net', 750.0)
        ->assertJsonCount(6, 'data.trend');

    expect($response->getContent())
        ->toContain('"assets":1000.0')
        ->toContain('"liabilities":-250.0')
        ->toContain('"net":750.0');
});

test('account api list is forbidden for another user', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $ledger = Ledger::factory()->for($owner)->create();

    Sanctum::actingAs($other, ['*']);

    $this->getJson(route('api.v1.ledgers.accounts.index', $ledger))
        ->assertForbidden();
});

test('account page loaders are forbidden for another user', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $ledger = Ledger::factory()->for($owner)->create();

    Sanctum::actingAs($other, ['*']);

    $this->getJson(route('api.v1.ledgers.accounts.grouped', $ledger))
        ->assertForbidden();

    $this->getJson(route('api.v1.ledgers.accounts.types', $ledger))
        ->assertForbidden();

    $this->getJson(route('api.v1.ledgers.accounts.net-worth', $ledger))
        ->assertForbidden();
});

test('account api create returns validation errors as json', function () {
    $user = User::factory()->create();
    $user->membership()->update(['tier' => 'premium', 'status' => 'active']);
    $ledger = Ledger::factory()->for($user)->create();

    Sanctum::actingAs($user, ['*']);

    $response = $this->postJson(route('api.v1.ledgers.accounts.store', $ledger), [
        'name' => '',
        'include_in_totals' => null,
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['name', 'account_type_id', 'include_in_totals', 'initial_balance']);
});

test('account api create returns created account contract', function () {
    $user = User::factory()->create();
    $user->membership()->update(['tier' => 'premium', 'status' => 'active']);
    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->create();

    Sanctum::actingAs($user, ['*']);

    $response = $this->postJson(route('api.v1.ledgers.accounts.store', $ledger), [
        'account_type_id' => $accountType->id,
        'name' => 'Savings Account',
        'initial_balance' => 500.00,
        'include_in_totals' => true,
        'statement_day' => null,
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.ledger_id', $ledger->id)
        ->assertJsonPath('data.name', 'Savings Account')
        ->assertJsonPath('data.account_type_id', $accountType->id)
        ->assertJsonPath('data.created_at', fn (mixed $v): bool => is_string($v) && $v !== '');

    expect($ledger->accounts()->where('name', 'Savings Account')->exists())->toBeTrue();
});

test('account api create rejects account type from another ledger', function () {
    $user = User::factory()->create();
    $user->membership()->update(['tier' => 'premium', 'status' => 'active']);
    $ledger = Ledger::factory()->for($user)->create();
    $foreignLedger = Ledger::factory()->create();
    $foreignAccountType = AccountType::factory()->for($foreignLedger)->create();

    Sanctum::actingAs($user, ['*']);

    $this->postJson(route('api.v1.ledgers.accounts.store', $ledger), [
        'account_type_id' => $foreignAccountType->id,
        'name' => 'Hacked Account',
        'initial_balance' => 0,
        'include_in_totals' => true,
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['account_type_id']);
});

test('account api create is forbidden for free user at or above 7 accounts', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->create();

    Account::factory()->for($ledger)->for($accountType)->count(7)->create();

    Sanctum::actingAs($user, ['*']);

    $this->postJson(route('api.v1.ledgers.accounts.store', $ledger), [
        'account_type_id' => $accountType->id,
        'name' => 'Eighth Account',
        'initial_balance' => 0,
        'include_in_totals' => true,
    ])
        ->assertForbidden();
});

test('account api create is allowed for premium user with 7+ accounts', function () {
    $user = User::factory()->create();
    $user->membership()->update(['tier' => 'premium', 'status' => 'active']);
    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->create();

    Account::factory()->for($ledger)->for($accountType)->count(7)->create();

    Sanctum::actingAs($user, ['*']);

    $this->postJson(route('api.v1.ledgers.accounts.store', $ledger), [
        'account_type_id' => $accountType->id,
        'name' => 'Eighth Account',
        'initial_balance' => 0,
        'include_in_totals' => true,
    ])
        ->assertCreated();
});

test('account api update returns updated account json', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create(['name' => 'Old Name']);

    Sanctum::actingAs($user, ['*']);

    $response = $this->patchJson(route('api.v1.ledgers.accounts.update', [$ledger, $account]), [
        'name' => 'New Name',
        'account_type_id' => $accountType->id,
        'initial_balance' => 100.00,
        'include_in_totals' => true,
    ]);

    $response->assertSuccessful()
        ->assertJsonPath('data.id', $account->id)
        ->assertJsonPath('data.name', 'New Name');

    expect($account->fresh()->name)->toBe('New Name');
});

test('account api destroy deletes the account', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create();

    Sanctum::actingAs($user, ['*']);

    $this->deleteJson(route('api.v1.ledgers.accounts.destroy', [$ledger, $account]))
        ->assertSuccessful();

    expect(Account::find($account->id))->toBeNull();
});

test('account api reorder updates account positions', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->create();

    $first = Account::factory()->for($ledger)->for($accountType)->create(['position' => 1]);
    $second = Account::factory()->for($ledger)->for($accountType)->create(['position' => 2]);

    Sanctum::actingAs($user, ['*']);

    $this->postJson(route('api.v1.ledgers.accounts.reorder', $ledger), [
        'items' => [
            ['id' => $first->id, 'position' => 2],
            ['id' => $second->id, 'position' => 1],
        ],
    ])->assertNoContent();

    expect($first->fresh()->position)->toBe(2)
        ->and($second->fresh()->position)->toBe(1);
});

test('account api reorder rejects items from another ledger', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $foreignLedger = Ledger::factory()->create();
    $foreignAccountType = AccountType::factory()->for($foreignLedger)->create();
    $foreignAccount = Account::factory()->for($foreignLedger)->for($foreignAccountType)->create(['position' => 1]);

    Sanctum::actingAs($user, ['*']);

    $this->postJson(route('api.v1.ledgers.accounts.reorder', $ledger), [
        'items' => [
            ['id' => $foreignAccount->id, 'position' => 1],
        ],
    ])->assertUnprocessable();
});

test('account api adjust balance creates a transaction', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create(['initial_balance' => 1000]);

    Sanctum::actingAs($user, ['*']);

    $this->postJson(route('api.v1.ledgers.accounts.adjust-balance', [$ledger, $account]), [
        'amount' => 250.00,
        'description' => 'Manual top-up',
    ])->assertNoContent();

    expect($ledger->transactions()->where('account_id', $account->id)->count())->toBe(1);
});

test('account api adjust balance rejects zero amount', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create();

    Sanctum::actingAs($user, ['*']);

    $this->postJson(route('api.v1.ledgers.accounts.adjust-balance', [$ledger, $account]), [
        'amount' => 0,
    ])
        ->assertUnprocessable()
        ->assertJsonPath('errors.amount.0', 'The adjustment amount cannot be zero.');
});

test('grouped account loader preserves account balances without N plus 1 queries', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $type = AccountType::factory()->for($ledger)->create();

    $checking = Account::factory()->for($ledger)->for($type)->create([
        'name' => 'Checking',
        'include_in_totals' => true,
        'initial_balance' => '1000.00',
    ]);

    $savings = Account::factory()->for($ledger)->for($type)->create([
        'name' => 'Savings',
        'include_in_totals' => false,
        'initial_balance' => '500.00',
    ]);

    Transaction::factory()->for($ledger)->for($checking)->create([
        'amount' => '250.00',
        'category_id' => null,
        'payee_id' => null,
    ]);

    Transaction::factory()->for($ledger)->for($savings)->create([
        'amount' => '-75.00',
        'category_id' => null,
        'payee_id' => null,
    ]);

    Sanctum::actingAs($user, ['*']);

    DB::flushQueryLog();
    DB::enableQueryLog();

    $this->getJson(route('api.v1.ledgers.accounts.grouped', $ledger))
        ->assertSuccessful()
        ->assertJsonPath('data.0.accounts.0.current_balance', '1250.00')
        ->assertJsonPath('data.0.total_balance', '1250.00')
        ->assertJsonPath('data.1.accounts.0.current_balance', '425.00')
        ->assertJsonPath('data.1.total_balance', '425.00');

    $balanceQueries = collect(DB::getQueryLog())
        ->filter(function (array $query): bool {
            $sql = strtolower($query['query']);

            return str_starts_with($sql, 'select')
                && str_contains($sql, 'sum(')
                && str_contains($sql, 'from "transactions"');
        });

    DB::disableQueryLog();

    expect($balanceQueries)->toHaveCount(1);
});

test('grouped account loader omits empty groups and carries account type data', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $type = AccountType::factory()->for($ledger)->create(['name' => 'Savings']);

    Account::factory()->for($ledger)->for($type)->create([
        'include_in_totals' => true,
    ]);

    Sanctum::actingAs($user, ['*']);

    $this->getJson(route('api.v1.ledgers.accounts.grouped', $ledger))
        ->assertSuccessful()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.group', 'included')
        ->assertJsonPath('data.0.accounts.0.account_type.name', 'Savings');
});

test('grouped account loader excludes computed statement fields from credit card accounts', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->credit()->create();
    Account::factory()->for($ledger)->for($accountType)->create([
        'initial_balance' => 0,
        'statement_day' => 15,
        'payment_due_day' => 25,
    ]);

    Sanctum::actingAs($user, ['*']);

    $this->getJson(route('api.v1.ledgers.accounts.grouped', $ledger))
        ->assertSuccessful()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.accounts.0.statement_day', 15)
        ->assertJsonPath('data.0.accounts.0.payment_due_day', 25)
        ->assertJsonMissingPath('data.0.accounts.0.statement_balance')
        ->assertJsonMissingPath('data.0.accounts.0.current_spending')
        ->assertJsonMissingPath('data.0.accounts.0.outstanding')
        ->assertJsonMissingPath('data.0.accounts.0.payment_due_date')
        ->assertJsonMissingPath('data.0.accounts.0.statement_start')
        ->assertJsonMissingPath('data.0.accounts.0.statement_end');
});
