<?php

use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\AccountType;
use App\Models\Category;
use App\Models\Ledger;
use App\Models\Payee;
use App\Models\Transaction;
use App\Models\User;
use App\Services\TransactionService;
use Illuminate\Support\Str;

test('users can create an expense transaction', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create();
    $category = Category::factory()->for($ledger)->create();
    $payee = Payee::factory()->for($ledger)->create();

    $response = $this
        ->actingAs($user)
        ->post(route('ledgers.transactions.store', $ledger), [
            'account_id' => $account->id,
            'category_id' => $category->id,
            'payee_id' => $payee->id,
            'transaction_type' => 'expense',
            'amount' => 20.25,
            'description' => 'Coffee',
            'notes' => 'Morning coffee',
            'transaction_date' => '2026-03-13',
        ]);

    $response->assertRedirect();

    expect($ledger->transactions()->count())->toBe(1)
        ->and((float) $ledger->transactions()->first()->amount)->toBe(-20.25);
});

test('transaction store rejects cross ledger related ids', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create();

    $foreignLedger = Ledger::factory()->create();
    $foreignAccountType = AccountType::factory()->for($foreignLedger)->create();
    $foreignAccount = Account::factory()->for($foreignLedger)->for($foreignAccountType)->create();
    $foreignCategory = Category::factory()->for($foreignLedger)->create();
    $foreignPayee = Payee::factory()->for($foreignLedger)->create();

    $response = $this->actingAs($user)
        ->post(route('ledgers.transactions.store', $ledger), [
            'account_id' => $account->id,
            'category_id' => $foreignCategory->id,
            'payee_id' => $foreignPayee->id,
            'to_account_id' => $foreignAccount->id,
            'transaction_type' => 'transfer',
            'amount' => 20.25,
            'description' => 'Coffee',
            'notes' => 'Morning coffee',
            'transaction_date' => '2026-03-13',
        ]);

    $response->assertSessionHasErrors(['category_id', 'payee_id', 'to_account_id']);
});

test('transaction service can update a regular transaction', function () {
    $ledger = Ledger::factory()->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create();
    $newAccount = Account::factory()->for($ledger)->for($accountType)->create();
    $category = Category::factory()->for($ledger)->create();
    $newCategory = Category::factory()->for($ledger)->create();
    $payee = Payee::factory()->for($ledger)->create();

    $transaction = Transaction::factory()->for($ledger)->for($account)->for($category)->for($payee)->create([
        'transaction_type' => TransactionType::Expense,
        'amount' => '-50.00',
        'description' => 'Old description',
        'transaction_date' => '2026-01-01',
    ]);

    $updated = app(TransactionService::class)->update($transaction, [
        'account' => $newAccount,
        'category' => $newCategory,
        'payee' => $payee,
        'amount' => -75.00,
        'description' => 'Updated description',
        'notes' => 'Updated notes',
        'transaction_date' => '2026-03-13',
    ]);

    expect((string) $updated->amount)->toBe('-75.00')
        ->and($updated->description)->toBe('Updated description')
        ->and($updated->account_id)->toBe($newAccount->id)
        ->and($updated->category_id)->toBe($newCategory->id)
        ->and($updated->transaction_date->toDateString())->toBe('2026-03-13');
});

test('transaction service updating a transfer updates both paired transactions', function () {
    $ledger = Ledger::factory()->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    $fromAccount = Account::factory()->for($ledger)->for($accountType)->create();
    $toAccount = Account::factory()->for($ledger)->for($accountType)->create();
    $newFromAccount = Account::factory()->for($ledger)->for($accountType)->create();
    $newToAccount = Account::factory()->for($ledger)->for($accountType)->create();

    $pairId = (string) Str::uuid();

    $source = Transaction::factory()->for($ledger)->for($fromAccount)->create([
        'transaction_type' => TransactionType::Transfer,
        'amount' => '-100.00',
        'description' => 'Old transfer',
        'transfer_pair_id' => $pairId,
        'category_id' => null,
    ]);

    $destination = Transaction::factory()->for($ledger)->for($toAccount)->create([
        'transaction_type' => TransactionType::Transfer,
        'amount' => '100.00',
        'description' => 'Old transfer',
        'transfer_pair_id' => $pairId,
        'category_id' => null,
    ]);

    app(TransactionService::class)->update($source, [
        'account' => $newFromAccount,
        'to_account' => $newToAccount,
        'amount' => 200.00,
        'description' => 'Updated transfer',
        'notes' => 'Transfer notes',
        'transaction_date' => '2026-03-13',
    ]);

    $source->refresh();
    $destination->refresh();

    expect((string) $source->amount)->toBe('-200.00')
        ->and((string) $destination->amount)->toBe('200.00')
        ->and($source->account_id)->toBe($newFromAccount->id)
        ->and($destination->account_id)->toBe($newToAccount->id)
        ->and($source->description)->toBe('Updated transfer')
        ->and($destination->description)->toBe('Updated transfer')
        ->and($destination->notes)->toBe('Transfer notes')
        ->and($destination->transaction_date->toDateString())->toBe('2026-03-13');
});

test('transaction service can delete a regular transaction', function () {
    $ledger = Ledger::factory()->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create();

    $transaction = Transaction::factory()->for($ledger)->for($account)->create([
        'transaction_type' => TransactionType::Expense,
        'transfer_pair_id' => null,
    ]);

    expect($ledger->transactions()->count())->toBe(1);

    app(TransactionService::class)->delete($transaction);

    expect($ledger->transactions()->count())->toBe(0)
        ->and(Transaction::withTrashed()->find($transaction->id)?->trashed())->toBeTrue();
});

test('transaction service deleting a transfer deletes both paired transactions', function () {
    $ledger = Ledger::factory()->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    $fromAccount = Account::factory()->for($ledger)->for($accountType)->create();
    $toAccount = Account::factory()->for($ledger)->for($accountType)->create();

    $pairId = (string) Str::uuid();

    $source = Transaction::factory()->for($ledger)->for($fromAccount)->create([
        'transaction_type' => TransactionType::Transfer,
        'amount' => '-50.00',
        'transfer_pair_id' => $pairId,
        'category_id' => null,
    ]);

    Transaction::factory()->for($ledger)->for($toAccount)->create([
        'transaction_type' => TransactionType::Transfer,
        'amount' => '50.00',
        'transfer_pair_id' => $pairId,
        'category_id' => null,
    ]);

    expect($ledger->transactions()->count())->toBe(2);

    app(TransactionService::class)->delete($source);

    expect($ledger->transactions()->count())->toBe(0)
        ->and(Transaction::withTrashed()->where('transfer_pair_id', $pairId)->count())->toBe(2);
});

test('transaction update via HTTP updates the transaction', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create();
    $newAccount = Account::factory()->for($ledger)->for($accountType)->create();
    $category = Category::factory()->for($ledger)->create();

    $transaction = Transaction::factory()->for($ledger)->for($account)->for($category)->create([
        'transaction_type' => TransactionType::Expense,
        'amount' => '-30.00',
        'description' => 'Old',
        'transaction_date' => '2026-01-01',
        'transfer_pair_id' => null,
    ]);

    $response = $this
        ->actingAs($user)
        ->put(route('ledgers.transactions.update', [$ledger, $transaction]), [
            'account_id' => $newAccount->id,
            'category_id' => $category->id,
            'payee_id' => null,
            'transaction_type' => 'expense',
            'amount' => 55.00,
            'description' => 'Updated',
            'notes' => null,
            'transaction_date' => '2026-03-13',
        ]);

    $response->assertRedirect(route('ledgers.transactions.index', $ledger));

    expect((float) $transaction->fresh()->amount)->toBe(-55.00)
        ->and($transaction->fresh()->description)->toBe('Updated')
        ->and($transaction->fresh()->account_id)->toBe($newAccount->id);
});

test('transaction destroy deletes transaction via HTTP', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create();

    $transaction = Transaction::factory()->for($ledger)->for($account)->create([
        'transaction_type' => TransactionType::Expense,
        'transfer_pair_id' => null,
    ]);

    $response = $this
        ->actingAs($user)
        ->delete(route('ledgers.transactions.destroy', [$ledger, $transaction]));

    $response->assertRedirect(route('ledgers.transactions.index', $ledger));

    expect(Transaction::find($transaction->id))->toBeNull()
        ->and(Transaction::withTrashed()->find($transaction->id)?->trashed())->toBeTrue();
});

test('bulk destroy deletes multiple transactions', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create();

    $t1 = Transaction::factory()->for($ledger)->for($account)->create([
        'transaction_type' => TransactionType::Expense,
        'transfer_pair_id' => null,
    ]);
    $t2 = Transaction::factory()->for($ledger)->for($account)->create([
        'transaction_type' => TransactionType::Expense,
        'transfer_pair_id' => null,
    ]);
    $t3 = Transaction::factory()->for($ledger)->for($account)->create([
        'transaction_type' => TransactionType::Expense,
        'transfer_pair_id' => null,
    ]);

    expect($ledger->transactions()->count())->toBe(3);

    $response = $this
        ->actingAs($user)
        ->post(route('ledgers.transactions.bulk-destroy', $ledger), [
            'ids' => [$t1->id, $t2->id],
        ]);

    $response->assertRedirect();

    expect($ledger->transactions()->count())->toBe(1)
        ->and(Transaction::find($t3->id))->not->toBeNull();
});

test('transferPair relationship returns the other paired transaction', function () {
    $pairId = (string) Str::uuid();

    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    $fromAccount = Account::factory()->for($ledger)->for($accountType)->create();
    $toAccount = Account::factory()->for($ledger)->for($accountType)->create();

    $outgoing = Transaction::factory()->for($ledger)->for($fromAccount)->create([
        'transaction_type' => TransactionType::Transfer,
        'amount' => -100.00,
        'transfer_pair_id' => $pairId,
    ]);

    $incoming = Transaction::factory()->for($ledger)->for($toAccount)->create([
        'transaction_type' => TransactionType::Transfer,
        'amount' => 100.00,
        'transfer_pair_id' => $pairId,
    ]);

    expect($outgoing->transferPair->id)->toBe($incoming->id)
        ->and($incoming->transferPair->id)->toBe($outgoing->id);
});

test('transaction edit includes transferPair in response', function () {
    $pairId = (string) Str::uuid();

    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    $fromAccount = Account::factory()->for($ledger)->for($accountType)->create();
    $toAccount = Account::factory()->for($ledger)->for($accountType)->create();

    $outgoing = Transaction::factory()->for($ledger)->for($fromAccount)->create([
        'transaction_type' => TransactionType::Transfer,
        'amount' => -100.00,
        'transfer_pair_id' => $pairId,
    ]);

    Transaction::factory()->for($ledger)->for($toAccount)->create([
        'transaction_type' => TransactionType::Transfer,
        'amount' => 100.00,
        'transfer_pair_id' => $pairId,
    ]);

    $response = $this
        ->actingAs($user)
        ->get(route('ledgers.transactions.edit', [$ledger, $outgoing]));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('ledgers/transactions/edit')
        ->has('transaction.transfer_pair')
    );
});

test('transaction edit includes attachments and splits in response', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create();
    $category = Category::factory()->for($ledger)->create();

    $transaction = Transaction::factory()->for($ledger)->for($account)->for($category)->create([
        'transaction_type' => TransactionType::Expense,
        'amount' => '-100.00',
        'transaction_date' => '2026-03-13',
    ]);

    $transaction->attachments()->create([
        'filename' => 'receipt.pdf',
        'path' => 'attachments/'.$ledger->id.'/receipt.pdf',
        'mime_type' => 'application/pdf',
        'size' => 12345,
    ]);

    $transaction->splits()->createMany([
        [
            'category_id' => $category->id,
            'amount' => '-60.00',
            'description' => 'Food',
        ],
        [
            'category_id' => $category->id,
            'amount' => '-40.00',
            'description' => 'Drinks',
        ],
    ]);

    $response = $this
        ->actingAs($user)
        ->get(route('ledgers.transactions.edit', [$ledger, $transaction]));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('ledgers/transactions/edit')
        ->has('transaction.attachments', 1)
        ->has('transaction.splits', 2)
        ->where('transaction.splits.0.description', 'Food')
    );
});
