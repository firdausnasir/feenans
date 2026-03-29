<?php

use App\Models\Account;
use App\Models\AccountType;
use App\Models\Category;
use App\Models\Ledger;
use App\Models\Payee;
use App\Models\Tag;
use App\Models\Transaction;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('transaction index page renders the correct component', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create();
    $category = Category::factory()->for($ledger)->create();

    Transaction::factory()->for($ledger)->for($account)->for($category)->create([
        'description' => 'Initial deferred transaction',
        'transaction_date' => now()->toDateString(),
    ]);

    $response = $this
        ->actingAs($user)
        ->get(route('ledgers.transactions.index', $ledger));

    $response->assertInertia(fn (Assert $page) => $page
        ->component('ledgers/transactions/index')
        ->has('filters')
        ->has('accounts')
        ->has('categories')
        ->has('payees')
        ->has('tags')
        ->missing('transactions')
        ->loadDeferredProps(fn (Assert $reload) => $reload
            ->has('transactions.data', 1)
            ->where('transactions.data.0.description', 'Initial deferred transaction')
        )
    );
});

test('uncategorized filter returns only non-transfer transactions without a category', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create();
    $category = Category::factory()->for($ledger)->create();

    Transaction::factory()->for($ledger)->for($account)->for($category)->create([
        'description' => 'Has category',
        'transaction_date' => now()->toDateString(),
    ]);

    Transaction::factory()->for($ledger)->for($account)->create([
        'description' => 'No category',
        'category_id' => null,
        'transaction_date' => now()->toDateString(),
    ]);

    Transaction::factory()->transferOut()->for($ledger)->for($account)->create([
        'description' => 'Transfer no category',
        'transaction_date' => now()->toDateString(),
    ]);

    $response = $this
        ->actingAs($user)
        ->get(route('ledgers.transactions.index', [$ledger, 'uncategorized' => '1']));

    $response->assertSuccessful();
    $response->assertInertia(fn (Assert $page) => $page
        ->missing('transactions')
        ->loadDeferredProps(fn (Assert $reload) => $reload
            ->has('transactions.data', 1)
            ->where('transactions.data.0.description', 'No category')
        )
    );
});

test('transaction index deferred payload includes transfer account data and split line relations for mobile rendering', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    $sourceAccount = Account::factory()->for($ledger)->for($accountType)->create([
        'name' => 'Checking',
    ]);
    $destinationAccount = Account::factory()->for($ledger)->for($accountType)->create([
        'name' => 'Savings',
    ]);
    $foodCategory = Category::factory()->for($ledger)->create([
        'name' => 'Food',
    ]);
    $travelCategory = Category::factory()->for($ledger)->create([
        'name' => 'Travel',
    ]);
    $marketPayee = Payee::factory()->for($ledger)->create([
        'name' => 'Market',
    ]);
    $airlinePayee = Payee::factory()->for($ledger)->create([
        'name' => 'Airline',
    ]);

    $transferOut = Transaction::factory()->transferOut()->for($ledger)->for($sourceAccount)->create([
        'description' => 'Move to savings',
        'transaction_date' => '2026-03-21',
    ]);

    $transferIn = Transaction::factory()->transferIn()->for($ledger)->for($destinationAccount)->create([
        'description' => 'Move to savings',
        'transaction_date' => '2026-03-21',
        'transfer_pair_id' => $transferOut->transfer_pair_id,
    ]);

    $transferOut->update(['transfer_pair_id' => $transferIn->transfer_pair_id]);

    $splitTransaction = Transaction::factory()->for($ledger)->for($sourceAccount)->create([
        'description' => 'Split purchase',
        'transaction_date' => '2026-03-20',
    ]);

    $splitTransaction->splits()->createMany([
        [
            'category_id' => $foodCategory->id,
            'payee_id' => $marketPayee->id,
            'amount' => '-12.50',
            'description' => 'Groceries',
        ],
        [
            'category_id' => $travelCategory->id,
            'payee_id' => $airlinePayee->id,
            'amount' => '-7.50',
            'description' => 'Baggage fee',
        ],
    ]);

    $response = $this
        ->actingAs($user)
        ->get(route('ledgers.transactions.index', $ledger));

    $response->assertSuccessful();
    $response->assertInertia(fn (Assert $page) => $page
        ->missing('transactions')
        ->loadDeferredProps(fn (Assert $reload) => $reload
            ->has('transactions.data', 3)
            ->where('transactions.data', function ($transactions) use ($transferOut, $splitTransaction) {
                $transactions = collect($transactions);

                $transferPayload = $transactions->firstWhere('id', $transferOut->id);
                $splitPayload = $transactions->firstWhere('id', $splitTransaction->id);

                expect($transferPayload)->not->toBeNull();
                expect($splitPayload)->not->toBeNull();

                expect($transferPayload['transfer_pair']['id'] ?? null)->toBeInt();
                expect($transferPayload['transfer_pair']['account']['name'] ?? null)->toBe('Savings');

                expect($splitPayload['splits'] ?? [])->toHaveCount(2);

                $splitLines = collect($splitPayload['splits']);
                $groceries = $splitLines->firstWhere('description', 'Groceries');
                $baggageFee = $splitLines->firstWhere('description', 'Baggage fee');

                expect($groceries['category']['name'] ?? null)->toBe('Food');
                expect($groceries['payee']['name'] ?? null)->toBe('Market');
                expect($baggageFee['category']['name'] ?? null)->toBe('Travel');
                expect($baggageFee['payee']['name'] ?? null)->toBe('Airline');

                return true;
            })
        )
    );
});

test('transaction index deferred payload keeps counterpart account data when only one transfer side is on the current page', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    $sourceAccount = Account::factory()->for($ledger)->for($accountType)->create([
        'name' => 'Checking',
    ]);
    $destinationAccount = Account::factory()->for($ledger)->for($accountType)->create([
        'name' => 'Savings',
    ]);

    $transferOut = Transaction::factory()->transferOut()->for($ledger)->for($sourceAccount)->create([
        'description' => 'Move to savings',
        'transaction_date' => '2026-03-22',
    ]);

    $transferIn = Transaction::factory()->transferIn()->for($ledger)->for($destinationAccount)->create([
        'description' => 'Move to savings',
        'transaction_date' => '2026-03-22',
        'transfer_pair_id' => $transferOut->transfer_pair_id,
    ]);

    $transferOut->update(['transfer_pair_id' => $transferIn->transfer_pair_id]);

    Transaction::factory()->for($ledger)->for($sourceAccount)->create([
        'description' => 'Later transaction',
        'transaction_date' => '2026-03-23',
    ]);

    Transaction::factory()->for($ledger)->for($sourceAccount)->create([
        'description' => 'Earlier transaction',
        'transaction_date' => '2026-03-21',
    ]);

    $response = $this
        ->actingAs($user)
        ->get(route('ledgers.transactions.index', [$ledger, 'per_page' => 2, 'page' => 2]));

    $response->assertSuccessful();
    $response->assertInertia(fn (Assert $page) => $page
        ->missing('transactions')
        ->loadDeferredProps(fn (Assert $reload) => $reload
            ->has('transactions.data', 2)
            ->where('transactions.data.0.id', $transferOut->id)
            ->where('transactions.data.0.transfer_pair.account.name', 'Savings')
        )
    );
});

test('transaction index transfer backfill stays within the current ledger', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $otherUser = User::factory()->create();
    $otherLedger = Ledger::factory()->for($otherUser)->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    $otherAccountType = AccountType::factory()->for($otherLedger)->create();

    $sourceAccount = Account::factory()->for($ledger)->for($accountType)->create([
        'name' => 'Checking',
    ]);
    $destinationAccount = Account::factory()->for($ledger)->for($accountType)->create([
        'name' => 'Savings',
    ]);

    $foreignAccount = Account::factory()->for($otherLedger)->for($otherAccountType)->create([
        'name' => 'Foreign account',
    ]);

    $sharedPairId = 'pair-shared-across-ledgers';

    $transferOut = Transaction::factory()->transferOut()->for($ledger)->for($sourceAccount)->create([
        'description' => 'Move to savings',
        'transaction_date' => '2026-03-22',
        'transfer_pair_id' => $sharedPairId,
    ]);

    Transaction::factory()->transferIn()->for($ledger)->for($destinationAccount)->create([
        'description' => 'Move to savings',
        'transaction_date' => '2026-03-22',
        'transfer_pair_id' => $sharedPairId,
    ]);

    Transaction::factory()->transferIn()->for($otherLedger)->for($foreignAccount)->create([
        'description' => 'Other ledger transfer',
        'transaction_date' => '2026-03-22',
        'transfer_pair_id' => $sharedPairId,
    ]);

    Transaction::factory()->for($ledger)->for($sourceAccount)->create([
        'description' => 'Later transaction',
        'transaction_date' => '2026-03-23',
    ]);

    Transaction::factory()->for($ledger)->for($sourceAccount)->create([
        'description' => 'Earlier transaction',
        'transaction_date' => '2026-03-21',
    ]);

    $response = $this
        ->actingAs($user)
        ->get(route('ledgers.transactions.index', [$ledger, 'per_page' => 2, 'page' => 2]));

    $response->assertSuccessful();
    $response->assertInertia(fn (Assert $page) => $page
        ->missing('transactions')
        ->loadDeferredProps(fn (Assert $reload) => $reload
            ->has('transactions.data', 2)
            ->where('transactions.data.0.id', $transferOut->id)
            ->where('transactions.data.0.transfer_pair.account.name', 'Savings')
        )
    );
});

test('transaction edit page bootstraps transaction form data through inertia props', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create();
    $category = Category::factory()->for($ledger)->create();
    $payee = Payee::factory()->for($ledger)->create();
    $tag = Tag::factory()->for($ledger)->create();

    $transaction = Transaction::factory()
        ->for($ledger)
        ->for($account)
        ->for($category)
        ->for($payee)
        ->create([
            'description' => 'Bootstrapped transaction',
            'transaction_date' => '2026-03-20',
        ]);

    $transaction->tags()->attach($tag);

    $transaction->attachments()->create([
        'filename' => 'receipt.pdf',
        'path' => 'attachments/'.$ledger->id.'/receipt.pdf',
        'mime_type' => 'application/pdf',
        'size' => 1024,
    ]);

    $transaction->splits()->create([
        'category_id' => $category->id,
        'payee_id' => $payee->id,
        'amount' => '-10.00',
        'description' => 'Split line',
    ]);

    $response = $this
        ->actingAs($user)
        ->get(route('ledgers.transactions.edit', [$ledger, $transaction]));

    $response->assertSuccessful();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('ledgers/transactions/edit')
        ->has('ledger')
        ->has('transaction', fn (Assert $transactionProp) => $transactionProp
            ->etc()
            ->where('id', $transaction->id)
            ->where('description', 'Bootstrapped transaction')
            ->has('attachments', 1)
            ->has('splits', 1)
            ->has('tags', 1)
        )
        ->has('accounts', 1)
        ->has('categories', 1)
        ->has('payees', 1)
        ->has('tags', 1)
    );
});
