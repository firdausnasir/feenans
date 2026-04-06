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

test('transaction index page renders shell props without deferred transactions payload', function () {
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

    $response
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('ledgers/transactions/index')
            ->has('filters')
            ->has('accounts')
            ->has('categories')
            ->has('payees')
            ->has('tags')
            ->missing('transactions')
        );

    expect(collect(data_get(
        json_decode(json_encode($response->viewData('page')), true),
        'deferredProps',
        [],
    ))->flatten()->all())->toBeEmpty();
});

test('transaction index includes current balances for modal account options', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create([
        'name' => 'Checking',
        'initial_balance' => '100.00',
    ]);

    Transaction::factory()->for($ledger)->for($account)->create([
        'amount' => '-20.00',
        'category_id' => null,
        'payee_id' => null,
        'transaction_date' => now()->toDateString(),
    ]);

    $this->actingAs($user)
        ->get(route('ledgers.transactions.index', $ledger))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->where('accounts.0.name', 'Checking')
            ->where('accounts.0.current_balance', '80.00')
        );
});

test('uncategorized filter stays in shell props without transaction payload', function () {
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

    $response
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.uncategorized', '1')
            ->missing('transactions')
        );

    expect(collect(data_get(
        json_decode(json_encode($response->viewData('page')), true),
        'deferredProps',
        [],
    ))->flatten()->all())->toBeEmpty();
});

test('transaction edit page bootstraps transaction form data through inertia props', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create([
        'name' => 'Checking',
        'position' => 1,
    ]);
    Account::factory()->for($ledger)->for($accountType)->create([
        'name' => 'Savings',
        'include_in_totals' => false,
        'position' => 2,
    ]);
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
        ->where('transaction_id', $transaction->id)
    );
});
