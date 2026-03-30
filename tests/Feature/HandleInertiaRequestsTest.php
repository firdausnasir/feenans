<?php

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Account;
use App\Models\AccountType;
use App\Models\Bill;
use App\Models\Ledger;
use App\Models\Transaction;
use App\Models\User;
use App\Notifications\BillDueReminder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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

test('auth.user contains privacy mode on ledger pages', function () {
    $user = User::factory()->create(['privacy_mode' => true]);
    $ledger = Ledger::factory()->for($user)->create();

    $this->actingAs($user)
        ->get(route('ledgers.dashboard', $ledger))
        ->assertInertia(fn (Assert $page) => $page
            ->where('auth.user.privacy_mode', true)
        );
});

test('shared auth user tolerates stale authenticated models missing optional attributes', function () {
    $persistedUser = User::factory()->create(['privacy_mode' => true]);

    $staleUser = new class extends User
    {
        public function fresh($with = []): ?static
        {
            return $this;
        }

        public function getForeignKey()
        {
            return 'user_id';
        }
    };

    $staleUser->forceFill([
        'id' => $persistedUser->id,
        'name' => $persistedUser->name,
        'email' => $persistedUser->email,
        'email_verified_at' => $persistedUser->email_verified_at,
    ]);
    $staleUser->exists = true;
    $staleUser->setRelation('membership', $persistedUser->membership);

    $request = Request::create('/ledgers', 'GET');
    $request->setLaravelSession(app('session')->driver());
    $request->setUserResolver(fn () => $staleUser);

    $shared = app(HandleInertiaRequests::class)->share($request);

    expect($shared['auth']['user'])->toMatchArray([
        'id' => $persistedUser->id,
        'email' => $persistedUser->email,
        'onboarding_step' => null,
        'privacy_mode' => false,
    ]);
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

    $user->notifyNow(new BillDueReminder(collect(), collect([$bill]), collect()));
    $user->notifyNow(new BillDueReminder(collect(), collect([$bill]), collect()));

    $this->actingAs($user)
        ->get(route('ledgers.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('unread_notifications_count', 2)
        );
});

test('transaction modal data reload shares account balances with a single aggregate query', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->create();

    $checking = Account::factory()->for($ledger)->for($accountType)->create([
        'name' => 'Checking',
        'initial_balance' => '1000.00',
        'position' => 1,
    ]);

    $savings = Account::factory()->for($ledger)->for($accountType)->create([
        'name' => 'Savings',
        'initial_balance' => '200.00',
        'position' => 2,
    ]);

    Transaction::factory()->for($ledger)->for($checking)->create([
        'amount' => '250.00',
        'category_id' => null,
        'payee_id' => null,
    ]);

    Transaction::factory()->for($ledger)->for($savings)->create([
        'amount' => '-50.00',
        'category_id' => null,
        'payee_id' => null,
    ]);

    $response = $this->actingAs($user)
        ->get(route('ledgers.categories.index', $ledger));

    $response->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('ledgers/categories/index')
            ->missing('transactionModalData')
        );

    DB::flushQueryLog();
    DB::enableQueryLog();

    $response->assertInertia(fn (Assert $page) => $page
        ->reloadOnly('transactionModalData', fn (Assert $reload) => $reload
            ->has('transactionModalData.accounts', 2)
            ->where('transactionModalData.accounts.0.name', 'Checking')
            ->where('transactionModalData.accounts.0.current_balance', '1250.00')
            ->where('transactionModalData.accounts.1.name', 'Savings')
            ->where('transactionModalData.accounts.1.current_balance', '150.00')
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

    expect($balanceQueries)->toHaveCount(1);
});
