<?php

use App\Actions\Transactions\UseCases\DeleteTransactionAction;
use App\Actions\Transactions\UseCases\StoreTransactionAction;
use App\Actions\Transactions\UseCases\UpdateTransactionAction;
use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\AccountType;
use App\Models\Category;
use App\Models\Ledger;
use App\Models\Payee;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

test('transaction store routes through StoreTransactionAction', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create();
    $category = Category::factory()->for($ledger)->create(['transaction_type' => TransactionType::Expense]);

    $called = false;
    $real = app()->make(StoreTransactionAction::class);
    app()->bind(StoreTransactionAction::class, function () use ($real, &$called) {
        $called = true;

        return $real;
    });

    $this->actingAs($user)
        ->from(route('ledgers.transactions.index', $ledger))
        ->post(route('ledgers.transactions.store', $ledger), [
            'account_id' => $account->id,
            'category_id' => $category->id,
            'transaction_type' => 'expense',
            'amount' => 25.00,
            'description' => 'Store through action',
            'transaction_date' => '2026-03-13',
        ])
        ->assertRedirect();

    expect($called)->toBeTrue();
});

test('transaction update routes through UpdateTransactionAction', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create();
    $category = Category::factory()->for($ledger)->create(['transaction_type' => TransactionType::Expense]);
    $transaction = Transaction::factory()
        ->for($ledger)
        ->for($account)
        ->for($category)
        ->expense()
        ->create([
            'description' => 'Before action update',
            'transaction_date' => '2026-03-12',
        ]);

    $called = false;
    $real = app()->make(UpdateTransactionAction::class);
    app()->bind(UpdateTransactionAction::class, function () use ($real, &$called) {
        $called = true;

        return $real;
    });

    $this->actingAs($user)
        ->from(route('ledgers.transactions.edit', [$ledger, $transaction]))
        ->put(route('ledgers.transactions.update', [$ledger, $transaction]), [
            'account_id' => $account->id,
            'category_id' => $category->id,
            'transaction_type' => 'expense',
            'amount' => 25.00,
            'description' => 'Updated through action',
            'transaction_date' => '2026-03-20',
        ])
        ->assertRedirect(route('ledgers.transactions.index', $ledger))
        ->assertSessionHas('success', 'Transaction updated.');

    expect($called)->toBeTrue();
});

test('transaction destroy routes through DeleteTransactionAction', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create();
    $transaction = Transaction::factory()
        ->for($ledger)
        ->for($account)
        ->expense()
        ->create();

    $called = false;
    $real = app()->make(DeleteTransactionAction::class);
    app()->bind(DeleteTransactionAction::class, function () use ($real, &$called) {
        $called = true;

        return $real;
    });

    $this->actingAs($user)
        ->from(route('ledgers.transactions.index', $ledger))
        ->delete(route('ledgers.transactions.destroy', [$ledger, $transaction]))
        ->assertRedirect(route('ledgers.transactions.index', $ledger))
        ->assertSessionHas('success', 'Transaction deleted.');

    expect($called)->toBeTrue();
});

test('users can create an income transaction', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create();
    $category = Category::factory()->for($ledger)->create();

    $response = $this
        ->actingAs($user)
        ->post(route('ledgers.transactions.store', $ledger), [
            'account_id' => $account->id,
            'category_id' => $category->id,
            'transaction_type' => 'income',
            'amount' => 500.00,
            'description' => 'Salary',
            'transaction_date' => '2026-03-01',
        ]);

    $response->assertRedirect();

    $transaction = $ledger->transactions()->first();
    expect($transaction)->not->toBeNull()
        ->and((float) $transaction->amount)->toBe(500.00)
        ->and($transaction->transaction_type)->toBe(TransactionType::Income);
});

test('users can create a transfer via HTTP', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    $fromAccount = Account::factory()->for($ledger)->for($accountType)->create();
    $toAccount = Account::factory()->for($ledger)->for($accountType)->create();

    $response = $this
        ->actingAs($user)
        ->post(route('ledgers.transactions.store', $ledger), [
            'account_id' => $fromAccount->id,
            'to_account_id' => $toAccount->id,
            'transaction_type' => 'transfer',
            'amount' => 150.00,
            'description' => 'Move to savings',
            'transaction_date' => '2026-03-01',
        ]);

    $response->assertRedirect();

    expect($ledger->transactions()->count())->toBe(2);

    $outgoing = $ledger->transactions()->where('amount', '<', 0)->first();
    $incoming = $ledger->transactions()->where('amount', '>', 0)->first();

    expect($outgoing->transfer_pair_id)->not->toBeNull()
        ->and($outgoing->transfer_pair_id)->toBe($incoming->transfer_pair_id)
        ->and((float) $outgoing->amount)->toBe(-150.00)
        ->and((float) $incoming->amount)->toBe(150.00)
        ->and($outgoing->account_id)->toBe($fromAccount->id)
        ->and($incoming->account_id)->toBe($toAccount->id);
});

test('transaction index preserves account filter in shell props', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    $accountA = Account::factory()->for($ledger)->for($accountType)->create();
    $accountB = Account::factory()->for($ledger)->for($accountType)->create();

    Transaction::factory()->for($ledger)->for($accountA)->create([
        'description' => 'Account A transaction',
        'transaction_date' => now()->toDateString(),
    ]);
    Transaction::factory()->for($ledger)->for($accountB)->create([
        'description' => 'Account B transaction',
        'transaction_date' => now()->toDateString(),
    ]);

    $response = $this
        ->actingAs($user)
        ->get(route('ledgers.transactions.index', [
            'ledger' => $ledger,
            'account_ids' => [$accountA->id],
        ]));

    $response->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('ledgers/transactions/index')
            ->where('filters.account_ids', [(string) $accountA->id])
            ->missing('transactions')
        );

    $response->assertViewMissing('page.deferredProps');
});

test('transaction index preserves search term in shell props', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create();

    Transaction::factory()->for($ledger)->for($account)->create([
        'description' => 'Morning coffee',
        'transaction_date' => now()->toDateString(),
    ]);
    Transaction::factory()->for($ledger)->for($account)->create([
        'description' => 'Lunch at restaurant',
        'transaction_date' => now()->toDateString(),
    ]);

    $response = $this
        ->actingAs($user)
        ->get(route('ledgers.transactions.index', [
            'ledger' => $ledger,
            'search' => 'coffee',
        ]));

    $response->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('ledgers/transactions/index')
            ->where('filters.search', 'coffee')
            ->missing('transactions')
        );

    $response->assertViewMissing('page.deferredProps');
});

test('transaction index preserves transaction type filter in shell props', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create();

    Transaction::factory()->for($ledger)->for($account)->create([
        'transaction_type' => TransactionType::Expense,
        'amount' => '-20.00',
        'transaction_date' => now()->toDateString(),
    ]);
    Transaction::factory()->for($ledger)->for($account)->create([
        'transaction_type' => TransactionType::Income,
        'amount' => '100.00',
        'transaction_date' => now()->toDateString(),
    ]);

    $response = $this
        ->actingAs($user)
        ->get(route('ledgers.transactions.index', [
            'ledger' => $ledger,
            'transaction_types' => ['income'],
        ]));

    $response->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('ledgers/transactions/index')
            ->where('filters.transaction_types', ['income'])
            ->missing('transactions')
        );

    $response->assertViewMissing('page.deferredProps');
});

test('transaction index normalizes transfer filter in shell props from the page query shape', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    $fromAccount = Account::factory()->for($ledger)->for($accountType)->create();
    $toAccount = Account::factory()->for($ledger)->for($accountType)->create();
    $transferPairId = (string) Str::uuid();

    Transaction::factory()->for($ledger)->for($fromAccount)->expense()->create([
        'transaction_date' => now()->toDateString(),
    ]);
    $outgoingTransfer = Transaction::factory()->for($ledger)->for($fromAccount)->transferOut()->create([
        'transaction_date' => now()->toDateString(),
        'transfer_pair_id' => $transferPairId,
    ]);
    $incomingTransfer = Transaction::factory()->for($ledger)->for($toAccount)->transferIn()->create([
        'transaction_date' => now()->toDateString(),
        'transfer_pair_id' => $transferPairId,
    ]);

    $response = $this
        ->actingAs($user)
        ->get(route('ledgers.transactions.index', $ledger).'?transaction_types[][]=transfer');

    $response->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('ledgers/transactions/index')
            ->where('filters.transaction_types', ['transfer'])
            ->missing('transactions')
        );

    $response->assertViewMissing('page.deferredProps');
});

test('transaction index preserves category filter in shell props', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create();
    $catA = Category::factory()->for($ledger)->create();
    $catB = Category::factory()->for($ledger)->create();

    Transaction::factory()->for($ledger)->for($account)->for($catA)->create([
        'transaction_date' => now()->toDateString(),
    ]);
    Transaction::factory()->for($ledger)->for($account)->for($catB)->create([
        'transaction_date' => now()->toDateString(),
    ]);

    $response = $this
        ->actingAs($user)
        ->get(route('ledgers.transactions.index', [
            'ledger' => $ledger,
            'category_ids' => [$catA->id],
        ]));

    $response->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('ledgers/transactions/index')
            ->where('filters.category_ids', [(string) $catA->id])
            ->missing('transactions')
        );

    $response->assertViewMissing('page.deferredProps');
});

test('transaction index preserves payee filter in shell props', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create();
    $payeeA = Payee::factory()->for($ledger)->create();
    $payeeB = Payee::factory()->for($ledger)->create();

    Transaction::factory()->for($ledger)->for($account)->for($payeeA)->create([
        'transaction_date' => now()->toDateString(),
    ]);
    Transaction::factory()->for($ledger)->for($account)->for($payeeB)->create([
        'transaction_date' => now()->toDateString(),
    ]);

    $response = $this
        ->actingAs($user)
        ->get(route('ledgers.transactions.index', [
            'ledger' => $ledger,
            'payee_ids' => [$payeeA->id],
        ]));

    $response->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('ledgers/transactions/index')
            ->where('filters.payee_ids', [(string) $payeeA->id])
            ->missing('transactions')
        );

    $response->assertViewMissing('page.deferredProps');
});

test('transaction store is forbidden for another users ledger', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $ledger = Ledger::factory()->for($owner)->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create();

    $this->actingAs($intruder)
        ->post(route('ledgers.transactions.store', $ledger), [
            'account_id' => $account->id,
            'transaction_type' => 'expense',
            'amount' => 10.00,
            'transaction_date' => '2026-03-01',
        ])
        ->assertForbidden();
});

test('transaction update is forbidden for another users ledger', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $ledger = Ledger::factory()->for($owner)->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create();
    $transaction = Transaction::factory()->for($ledger)->for($account)->create([
        'transaction_type' => TransactionType::Expense,
        'amount' => '-10.00',
    ]);

    $this->actingAs($intruder)
        ->put(route('ledgers.transactions.update', [$ledger, $transaction]), [
            'account_id' => $account->id,
            'transaction_type' => 'expense',
            'amount' => 20.00,
            'transaction_date' => '2026-03-01',
        ])
        ->assertForbidden();
});

test('transaction destroy is forbidden for another users ledger', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $ledger = Ledger::factory()->for($owner)->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create();
    $transaction = Transaction::factory()->for($ledger)->for($account)->create([
        'transaction_type' => TransactionType::Expense,
    ]);

    $this->actingAs($intruder)
        ->delete(route('ledgers.transactions.destroy', [$ledger, $transaction]))
        ->assertForbidden();
});

test('unauthenticated users cannot access transaction index', function () {
    $ledger = Ledger::factory()->create();

    $this->get(route('ledgers.transactions.index', $ledger))
        ->assertRedirect(route('login'));
});

test('transaction update via web route redirects to the transactions index', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create();
    $category = Category::factory()->for($ledger)->create(['transaction_type' => 'expense']);

    $transaction = Transaction::factory()
        ->for($ledger)
        ->for($account)
        ->for($category)
        ->expense()
        ->create([
            'description' => 'Before update',
            'transaction_date' => '2026-03-12',
        ]);

    $response = $this->actingAs($user)
        ->from(route('ledgers.transactions.edit', [$ledger, $transaction]))
        ->put(route('ledgers.transactions.update', [$ledger, $transaction]), [
            'account_id' => $account->id,
            'category_id' => $category->id,
            'transaction_type' => 'expense',
            'amount' => '25.00',
            'description' => 'Updated from web route',
            'transaction_date' => '2026-03-20',
        ]);

    $response->assertRedirect(route('ledgers.transactions.index', $ledger));

    expect($transaction->fresh()->description)->toBe('Updated from web route');
});

test('transaction update can create a new payee through the web route', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create();
    $category = Category::factory()->for($ledger)->create(['transaction_type' => 'expense']);

    $transaction = Transaction::factory()
        ->for($ledger)
        ->for($account)
        ->for($category)
        ->expense()
        ->create([
            'description' => 'Before payee update',
            'transaction_date' => '2026-03-12',
        ]);

    $response = $this->actingAs($user)
        ->from(route('ledgers.transactions.edit', [$ledger, $transaction]))
        ->put(route('ledgers.transactions.update', [$ledger, $transaction]), [
            'account_id' => $account->id,
            'category_id' => $category->id,
            'transaction_type' => 'expense',
            'amount' => '25.00',
            'description' => 'Updated from web route',
            'transaction_date' => '2026-03-20',
            'new_payee_name' => 'Fresh Edit Payee',
        ]);

    $response->assertRedirect(route('ledgers.transactions.index', $ledger));

    $transaction->refresh();

    expect($ledger->payees()->where('name', 'Fresh Edit Payee')->exists())->toBeTrue()
        ->and($transaction->payee?->name)->toBe('Fresh Edit Payee');
});

test('transaction store validation redirects back with submitted input', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create();
    $category = Category::factory()->for($ledger)->create(['transaction_type' => 'expense']);

    $response = $this
        ->actingAs($user)
        ->from(route('ledgers.transactions.index', $ledger))
        ->post(route('ledgers.transactions.store', $ledger), [
            'account_id' => $account->id,
            'category_id' => $category->id,
            'transaction_type' => 'expense',
            'amount' => '25.00',
            'description' => 'Keep my draft',
            'transaction_date' => '',
        ]);

    $response->assertRedirect(route('ledgers.transactions.index', $ledger))
        ->assertSessionHasErrors(['transaction_date'])
        ->assertSessionHasInput([
            'account_id' => $account->id,
            'category_id' => $category->id,
            'transaction_type' => 'expense',
            'amount' => '25.00',
            'description' => 'Keep my draft',
            'transaction_date' => '',
        ]);
});

test('transaction destroy via web route redirects to the transactions index', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create();

    $transaction = Transaction::factory()
        ->for($ledger)
        ->for($account)
        ->expense()
        ->create();

    $response = $this->actingAs($user)
        ->from(route('ledgers.transactions.edit', [$ledger, $transaction]))
        ->delete(route('ledgers.transactions.destroy', [$ledger, $transaction]));

    $response->assertRedirect(route('ledgers.transactions.index', $ledger));

    expect(Transaction::find($transaction->id))->toBeNull();
});
