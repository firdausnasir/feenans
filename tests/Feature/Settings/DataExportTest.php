<?php

use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\AccountType;
use App\Models\Bill;
use App\Models\Category;
use App\Models\Ledger;
use App\Models\Payee;
use App\Models\Tag;
use App\Models\Transaction;
use App\Models\User;

test('data export returns JSON download with correct structure', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create(['name' => 'Test Ledger']);
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create();
    $category = Category::factory()->for($ledger)->create();

    $response = $this
        ->actingAs($user)
        ->get(route('ledgers.export', $ledger));

    $response->assertOk();
    $response->assertHeader('content-type', 'application/json');
    $response->assertHeader('content-disposition');

    $content = json_decode($response->streamedContent(), true);

    expect($content)->toHaveKeys([
        'exported_at',
        'ledger_name',
        'currency_code',
        'accounts',
        'categories',
        'payees',
        'tags',
        'transactions',
        'bills',
        'budgets',
    ]);

    expect($content['ledger_name'])->toBe('Test Ledger');
});

test('data export contains expected data', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create(['name' => 'Checking']);
    $category = Category::factory()->for($ledger)->create(['name' => 'Groceries']);
    $payee = Payee::factory()->for($ledger)->create(['name' => 'Supermarket']);
    $tag = Tag::factory()->for($ledger)->create(['name' => 'essential']);

    $transaction = Transaction::factory()->for($ledger)->for($account)->for($category)->for($payee)->create([
        'transaction_type' => TransactionType::Expense,
        'amount' => '-50.00',
        'description' => 'Weekly shop',
    ]);

    $transaction->tags()->attach($tag);

    $bill = Bill::factory()->for($ledger)->create([
        'account_id' => $account->id,
        'name' => 'Rent',
    ]);

    $response = $this
        ->actingAs($user)
        ->get(route('ledgers.export', $ledger));

    $content = json_decode($response->streamedContent(), true);

    expect($content['accounts'])->toHaveCount(1);
    expect($content['accounts'][0]['name'])->toBe('Checking');

    expect($content['categories'])->toHaveCount(1);
    expect($content['categories'][0]['name'])->toBe('Groceries');

    expect($content['payees'])->toHaveCount(1);
    expect($content['payees'][0]['name'])->toBe('Supermarket');

    expect($content['tags'])->toHaveCount(1);
    expect($content['tags'][0]['name'])->toBe('essential');

    expect($content['transactions'])->toHaveCount(1);
    expect($content['transactions'][0]['description'])->toBe('Weekly shop');
    expect($content['transactions'][0]['tags'])->toHaveCount(1);

    expect($content['bills'])->toHaveCount(1);
    expect($content['bills'][0]['name'])->toBe('Rent');
});

test('data export excludes hard-deleted records', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create(['name' => 'Deleted Account']);
    $account->delete();

    $response = $this
        ->actingAs($user)
        ->get(route('ledgers.export', $ledger));

    $content = json_decode($response->streamedContent(), true);

    expect($content['accounts'])->toHaveCount(0);
});

test('unauthorized users cannot export another user data', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $ledger = Ledger::factory()->for($owner)->create();

    $response = $this
        ->actingAs($other)
        ->get(route('ledgers.export', $ledger));

    $response->assertForbidden();
});

test('unauthenticated users cannot access data export', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();

    $response = $this->get(route('ledgers.export', $ledger));

    $response->assertRedirect();
});

test('data export does not include data from other ledgers', function () {
    $user = User::factory()->create();
    $ledger1 = Ledger::factory()->for($user)->create();
    $ledger2 = Ledger::factory()->for($user)->create();

    $accountType1 = AccountType::factory()->for($ledger1)->create();
    $accountType2 = AccountType::factory()->for($ledger2)->create();

    Account::factory()->for($ledger1)->for($accountType1)->create(['name' => 'My Account']);
    Account::factory()->for($ledger2)->for($accountType2)->create(['name' => 'Other Account']);

    $response = $this
        ->actingAs($user)
        ->get(route('ledgers.export', $ledger1));

    $content = json_decode($response->streamedContent(), true);

    expect($content['accounts'])->toHaveCount(1);
    expect($content['accounts'][0]['name'])->toBe('My Account');
});

test('data export filename contains ledger name and date', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create(['name' => 'My Finances']);

    $response = $this
        ->actingAs($user)
        ->get(route('ledgers.export', $ledger));

    $disposition = $response->headers->get('content-disposition');
    expect($disposition)->toContain('my-finances-export-');
    expect($disposition)->toContain('.json');
});
