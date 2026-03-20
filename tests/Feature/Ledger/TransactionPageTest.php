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
