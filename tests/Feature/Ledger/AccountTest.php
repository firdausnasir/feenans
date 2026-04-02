<?php

use App\Actions\Accounts\Queries\GetAccountPageQuery;
use App\Models\Account;
use App\Models\AccountType;
use App\Models\Ledger;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

test('users can create an account inside their ledger', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->create();

    $response = $this
        ->actingAs($user)
        ->from(route('ledgers.accounts.index', $ledger))
        ->post(route('ledgers.accounts.store', $ledger), [
            'account_type_id' => $accountType->id,
            'name' => 'Main Wallet',
            'initial_balance' => 150.75,
            'statement_day' => null,
            'include_in_totals' => true,
        ]);

    $response->assertRedirect(route('ledgers.accounts.index', $ledger))
        ->assertSessionHasNoErrors();

    expect($ledger->accounts()->where('name', 'Main Wallet')->exists())->toBeTrue();
});

test('account store rejects an account type from another ledger', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $foreignLedger = Ledger::factory()->create();
    $foreignAccountType = AccountType::factory()->for($foreignLedger)->create();

    $response = $this->actingAs($user)
        ->from(route('ledgers.accounts.index', $ledger))
        ->post(route('ledgers.accounts.store', $ledger), [
            'account_type_id' => $foreignAccountType->id,
            'name' => 'Main Wallet',
            'initial_balance' => 150.75,
            'statement_day' => null,
            'include_in_totals' => true,
        ]);

    $response->assertRedirect(route('ledgers.accounts.index', $ledger))
        ->assertSessionHasErrors('account_type_id');
});

test('account update updates the account and redirects', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create([
        'name' => 'Old Name',
        'initial_balance' => 100.00,
    ]);

    $response = $this
        ->actingAs($user)
        ->from(route('ledgers.accounts.index', $ledger))
        ->put(route('ledgers.accounts.update', [$ledger, $account]), [
            'name' => 'New Name',
            'account_type_id' => $accountType->id,
            'initial_balance' => 200.00,
            'statement_day' => null,
            'include_in_totals' => true,
        ]);

    $response->assertRedirect(route('ledgers.accounts.index', $ledger))
        ->assertSessionHasNoErrors();

    expect($account->fresh()->name)->toBe('New Name')
        ->and((float) $account->fresh()->initial_balance)->toBe(200.00);
});

test('account destroy deletes account and redirects to index', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create();

    $response = $this
        ->actingAs($user)
        ->from(route('ledgers.accounts.index', $ledger))
        ->delete(route('ledgers.accounts.destroy', [$ledger, $account]));

    $response->assertRedirect(route('ledgers.accounts.index', $ledger));

    expect(Account::find($account->id))->toBeNull();
});

test('account index is forbidden for another user', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $ledger = Ledger::factory()->for($owner)->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    Account::factory()->for($ledger)->for($accountType)->create();

    $response = $this
        ->actingAs($other)
        ->get(route('ledgers.accounts.index', $ledger));

    $response->assertForbidden();
});

test('accounts index groups by include_in_totals', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $type = AccountType::factory()->for($ledger)->create();

    Account::factory()->for($ledger)->for($type)->create([
        'name' => 'Checking',
        'include_in_totals' => true,
        'initial_balance' => 1000,
    ]);
    Account::factory()->for($ledger)->for($type)->create([
        'name' => 'Rainy Day',
        'include_in_totals' => false,
        'initial_balance' => 5000,
    ]);

    $this->actingAs($user)
        ->get(route('ledgers.accounts.index', $ledger))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->loadDeferredProps('accounts', fn (Assert $reload) => $reload
                ->has('accounts', 2)
                ->where('accounts.0.group', 'included')
                ->where('accounts.0.label', 'Included in totals')
                ->has('accounts.0.accounts', 1)
                ->where('accounts.0.accounts.0.name', 'Checking')
                ->where('accounts.0.total_balance', '1000.00')
                ->where('accounts.1.group', 'excluded')
                ->where('accounts.1.label', 'Savings')
                ->has('accounts.1.accounts', 1)
                ->where('accounts.1.accounts.0.name', 'Rainy Day')
                ->where('accounts.1.total_balance', '5000.00')
            )
        );
});

test('accounts index loads account balances without N+1 queries', function () {
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

    DB::flushQueryLog();
    DB::enableQueryLog();

    $this->actingAs($user)
        ->get(route('ledgers.accounts.index', $ledger))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->loadDeferredProps('accounts', fn (Assert $reload) => $reload
                ->where('accounts.0.accounts.0.current_balance', '1250.00')
                ->where('accounts.0.total_balance', '1250.00')
                ->where('accounts.1.accounts.0.current_balance', '425.00')
                ->where('accounts.1.total_balance', '425.00')
            )
        );

    $balanceQueries = collect(DB::getQueryLog())
        ->filter(function (array $query): bool {
            $sql = strtolower($query['query']);

            return str_starts_with($sql, 'select')
                && str_contains($sql, 'sum(')
                && str_contains($sql, 'from "transactions"');
        });

    DB::disableQueryLog();

    // 1 from ListAccountsByTotalsQuery (withCurrentBalance embedded subquery)
    // 1 from GetNetWorthQuery (withCurrentBalance embedded subquery)
    // 1 from GetNetWorthQuery priorSum (SELECT sum(amount) FROM transactions)
    // This is a fixed cost regardless of N accounts — not N+1.
    expect($balanceQueries)->toHaveCount(3);
});

test('accounts index omits empty groups', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $type = AccountType::factory()->for($ledger)->create();

    Account::factory()->for($ledger)->for($type)->create([
        'name' => 'Checking',
        'include_in_totals' => true,
    ]);

    $this->actingAs($user)
        ->get(route('ledgers.accounts.index', $ledger))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->loadDeferredProps('accounts', fn (Assert $reload) => $reload
                ->has('accounts', 1)
                ->where('accounts.0.group', 'included')
            )
        );
});

test('accounts within each group carry their account type', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $type = AccountType::factory()->for($ledger)->create(['name' => 'Savings']);

    Account::factory()->for($ledger)->for($type)->create([
        'include_in_totals' => true,
    ]);

    $this->actingAs($user)
        ->get(route('ledgers.accounts.index', $ledger))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->loadDeferredProps('accounts', fn (Assert $reload) => $reload
                ->where('accounts.0.accounts.0.account_type.name', 'Savings')
            )
        );
});

test('credit card accounts do not include computed statement fields', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->credit()->create();
    Account::factory()->for($ledger)->for($accountType)->create([
        'initial_balance' => 0,
        'statement_day' => 15,
        'payment_due_day' => 25,
    ]);

    $this->actingAs($user)
        ->get(route('ledgers.accounts.index', $ledger))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->loadDeferredProps('accounts', fn (Assert $reload) => $reload
                ->has('accounts', 1)
                ->has('accounts.0.accounts', 1)
                ->where('accounts.0.accounts.0.statement_day', 15)
                ->where('accounts.0.accounts.0.payment_due_day', 25)
                ->missing('accounts.0.accounts.0.statement_balance')
                ->missing('accounts.0.accounts.0.current_spending')
                ->missing('accounts.0.accounts.0.outstanding')
                ->missing('accounts.0.accounts.0.payment_due_date')
                ->missing('accounts.0.accounts.0.statement_start')
                ->missing('accounts.0.accounts.0.statement_end')
            )
        );
});

test('accounts index routes data fetching through GetAccountPageQuery', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    AccountType::factory()->for($ledger)->create();

    $called = false;
    $real = app()->make(GetAccountPageQuery::class);

    app()->bind(GetAccountPageQuery::class, function () use ($real, &$called) {
        $called = true;

        return $real;
    });

    $this->actingAs($user)
        ->get(route('ledgers.accounts.index', $ledger))
        ->assertSuccessful();

    expect($called)->toBeTrue('AccountController@index must resolve GetAccountPageQuery from the container');
});

test('accounts index defers netWorth in the same group as accounts and accountTypes', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $type = AccountType::factory()->for($ledger)->create();

    Account::factory()->for($ledger)->for($type)->create([
        'initial_balance' => 1000,
        'include_in_totals' => true,
    ]);

    $this->actingAs($user)
        ->get(route('ledgers.accounts.index', $ledger))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->loadDeferredProps('accounts', fn (Assert $reload) => $reload
                ->has('accounts')
                ->has('accountTypes')
                ->has('netWorth')
            )
        );
});
