<?php

use App\Models\Account;
use App\Models\AccountType;
use App\Models\Category;
use App\Models\Ledger;
use App\Models\Payee;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Str;

test('bulk change category updates all selected transactions', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create();
    $originalCategory = Category::factory()->for($ledger)->create();
    $newCategory = Category::factory()->for($ledger)->create();

    $transactions = Transaction::factory()
        ->count(3)
        ->for($ledger)
        ->for($account)
        ->for($originalCategory)
        ->expense()
        ->create();

    $response = $this->actingAs($user)
        ->from(route('ledgers.transactions.index', $ledger))
        ->post(route('ledgers.transactions.bulk-update', $ledger), [
            'ids' => $transactions->pluck('id')->all(),
            'action' => 'change_category',
            'value' => $newCategory->id,
        ]);

    $response->assertRedirect(route('ledgers.transactions.index', $ledger))
        ->assertSessionHas('success', 'Transactions updated.');

    foreach ($transactions as $transaction) {
        expect($transaction->fresh()->category_id)->toBe($newCategory->id);
    }
});

test('bulk change account updates all selected transactions', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    $originalAccount = Account::factory()->for($ledger)->for($accountType)->create();
    $newAccount = Account::factory()->for($ledger)->for($accountType)->create();
    $category = Category::factory()->for($ledger)->create();

    $transactions = Transaction::factory()
        ->count(3)
        ->for($ledger)
        ->for($originalAccount)
        ->for($category)
        ->expense()
        ->create();

    $response = $this->actingAs($user)
        ->from(route('ledgers.transactions.index', $ledger))
        ->post(route('ledgers.transactions.bulk-update', $ledger), [
            'ids' => $transactions->pluck('id')->all(),
            'action' => 'change_account',
            'value' => $newAccount->id,
        ]);

    $response->assertRedirect(route('ledgers.transactions.index', $ledger))
        ->assertSessionHas('success', 'Transactions updated.');

    foreach ($transactions as $transaction) {
        expect($transaction->fresh()->account_id)->toBe($newAccount->id);
    }
});

test('bulk change payee updates all selected transactions', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create();
    $category = Category::factory()->for($ledger)->create();
    $originalPayee = Payee::factory()->for($ledger)->create();
    $newPayee = Payee::factory()->for($ledger)->create();

    $transactions = Transaction::factory()
        ->count(3)
        ->for($ledger)
        ->for($account)
        ->for($category)
        ->for($originalPayee)
        ->expense()
        ->create();

    $response = $this->actingAs($user)
        ->from(route('ledgers.transactions.index', $ledger))
        ->post(route('ledgers.transactions.bulk-update', $ledger), [
            'ids' => $transactions->pluck('id')->all(),
            'action' => 'change_payee',
            'value' => $newPayee->id,
        ]);

    $response->assertRedirect(route('ledgers.transactions.index', $ledger))
        ->assertSessionHas('success', 'Transactions updated.');

    foreach ($transactions as $transaction) {
        expect($transaction->fresh()->payee_id)->toBe($newPayee->id);
    }
});

test('bulk update skips transfer transactions', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create();
    $category = Category::factory()->for($ledger)->create();
    $newCategory = Category::factory()->for($ledger)->create();
    $pairId = (string) Str::uuid();

    $expense = Transaction::factory()
        ->for($ledger)
        ->for($account)
        ->for($category)
        ->expense()
        ->create();

    $transfer = Transaction::factory()
        ->for($ledger)
        ->for($account)
        ->transferOut()
        ->create(['transfer_pair_id' => $pairId]);

    $response = $this->actingAs($user)
        ->from(route('ledgers.transactions.index', $ledger))
        ->post(route('ledgers.transactions.bulk-update', $ledger), [
            'ids' => [$expense->id, $transfer->id],
            'action' => 'change_category',
            'value' => $newCategory->id,
        ]);

    $response->assertRedirect(route('ledgers.transactions.index', $ledger))
        ->assertSessionHas('success', 'Transactions updated.');

    expect($expense->fresh()->category_id)->toBe($newCategory->id)
        ->and($transfer->fresh()->category_id)->toBeNull();
});

test('bulk update rejects cross-ledger values', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create();
    $category = Category::factory()->for($ledger)->create();

    $foreignLedger = Ledger::factory()->create();
    $foreignCategory = Category::factory()->for($foreignLedger)->create();

    $transaction = Transaction::factory()
        ->for($ledger)
        ->for($account)
        ->for($category)
        ->expense()
        ->create();

    $response = $this->actingAs($user)
        ->from(route('ledgers.transactions.index', $ledger))
        ->post(route('ledgers.transactions.bulk-update', $ledger), [
            'ids' => [$transaction->id],
            'action' => 'change_category',
            'value' => $foreignCategory->id,
        ]);

    $response->assertRedirect(route('ledgers.transactions.index', $ledger))
        ->assertSessionHasErrors(['value']);
});

test('bulk update rejects invalid action', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create();
    $category = Category::factory()->for($ledger)->create();

    $transaction = Transaction::factory()
        ->for($ledger)
        ->for($account)
        ->for($category)
        ->expense()
        ->create();

    $response = $this->actingAs($user)
        ->from(route('ledgers.transactions.index', $ledger))
        ->post(route('ledgers.transactions.bulk-update', $ledger), [
            'ids' => [$transaction->id],
            'action' => 'invalid_action',
            'value' => $category->id,
        ]);

    $response->assertRedirect(route('ledgers.transactions.index', $ledger))
        ->assertSessionHasErrors(['action']);
});

test('bulk delete with confirmation deletes all selected transactions', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create();
    $category = Category::factory()->for($ledger)->create();

    $transactions = Transaction::factory()
        ->count(3)
        ->for($ledger)
        ->for($account)
        ->for($category)
        ->expense()
        ->create();

    $response = $this->actingAs($user)
        ->from(route('ledgers.transactions.index', $ledger))
        ->post(route('ledgers.transactions.bulk-destroy', $ledger), [
            'ids' => $transactions->pluck('id')->all(),
        ]);

    $response->assertRedirect(route('ledgers.transactions.index', $ledger))
        ->assertSessionHas('success', 'Transactions deleted.');

    foreach ($transactions as $transaction) {
        expect(Transaction::find($transaction->id))->toBeNull();
    }
});

test('bulk delete removes both sides of a transfer when one side is selected', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    $fromAccount = Account::factory()->for($ledger)->for($accountType)->create();
    $toAccount = Account::factory()->for($ledger)->for($accountType)->create();
    $pairId = (string) Str::uuid();

    $source = Transaction::factory()->for($ledger)->for($fromAccount)->transferOut()->create([
        'transfer_pair_id' => $pairId,
    ]);

    $paired = Transaction::factory()->for($ledger)->for($toAccount)->transferIn()->create([
        'transfer_pair_id' => $pairId,
    ]);

    $response = $this->actingAs($user)
        ->from(route('ledgers.transactions.index', $ledger))
        ->post(route('ledgers.transactions.bulk-destroy', $ledger), [
            'ids' => [$source->id],
        ]);

    $response->assertRedirect(route('ledgers.transactions.index', $ledger))
        ->assertSessionHas('success', 'Transactions deleted.');

    expect(Transaction::query()->whereKey($source->id)->exists())->toBeFalse()
        ->and(Transaction::query()->whereKey($paired->id)->exists())->toBeFalse();
});

test('bulk change category can target all matching filtered transactions without select-all ids', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    $matchingAccount = Account::factory()->for($ledger)->for($accountType)->create();
    $otherAccount = Account::factory()->for($ledger)->for($accountType)->create();
    $originalCategory = Category::factory()->for($ledger)->create();
    $newCategory = Category::factory()->for($ledger)->create();

    $included = Transaction::factory()
        ->count(2)
        ->for($ledger)
        ->for($matchingAccount)
        ->for($originalCategory)
        ->expense()
        ->create();

    $excluded = Transaction::factory()
        ->for($ledger)
        ->for($matchingAccount)
        ->for($originalCategory)
        ->expense()
        ->create();

    $outsideFilter = Transaction::factory()
        ->for($ledger)
        ->for($otherAccount)
        ->for($originalCategory)
        ->expense()
        ->create();

    $response = $this->actingAs($user)
        ->from(route('ledgers.transactions.index', $ledger))
        ->post(route('ledgers.transactions.bulk-update', $ledger), [
            'apply_to_all_matching' => true,
            'excluded_ids' => [$excluded->id],
            'filters' => [
                'account_ids' => [$matchingAccount->id],
            ],
            'action' => 'change_category',
            'value' => $newCategory->id,
        ]);

    $response->assertRedirect(route('ledgers.transactions.index', $ledger));

    foreach ($included as $transaction) {
        expect($transaction->fresh()->category_id)->toBe($newCategory->id);
    }

    expect($excluded->fresh()->category_id)->toBe($originalCategory->id)
        ->and($outsideFilter->fresh()->category_id)->toBe($originalCategory->id);
});

test('bulk delete can target all matching filtered transactions without select-all ids', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    $matchingAccount = Account::factory()->for($ledger)->for($accountType)->create();
    $otherAccount = Account::factory()->for($ledger)->for($accountType)->create();
    $category = Category::factory()->for($ledger)->create();

    $included = Transaction::factory()
        ->count(2)
        ->for($ledger)
        ->for($matchingAccount)
        ->for($category)
        ->expense()
        ->create();

    $excluded = Transaction::factory()
        ->for($ledger)
        ->for($matchingAccount)
        ->for($category)
        ->expense()
        ->create();

    $outsideFilter = Transaction::factory()
        ->for($ledger)
        ->for($otherAccount)
        ->for($category)
        ->expense()
        ->create();

    $response = $this->actingAs($user)
        ->from(route('ledgers.transactions.index', $ledger))
        ->post(route('ledgers.transactions.bulk-destroy', $ledger), [
            'apply_to_all_matching' => true,
            'excluded_ids' => [$excluded->id],
            'filters' => [
                'account_ids' => [$matchingAccount->id],
            ],
        ]);

    $response->assertRedirect(route('ledgers.transactions.index', $ledger));

    foreach ($included as $transaction) {
        expect(Transaction::find($transaction->id))->toBeNull();
    }

    expect(Transaction::find($excluded->id))->not->toBeNull()
        ->and(Transaction::find($outsideFilter->id))->not->toBeNull();
});
