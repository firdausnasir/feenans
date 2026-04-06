<?php

use App\Models\Account;
use App\Models\AccountType;
use App\Models\Attachment;
use App\Models\Category;
use App\Models\Ledger;
use App\Models\Payee;
use App\Models\Transaction;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    config()->set('app.paywall_enabled', true);
});

test('transaction api create returns validation errors as json', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();

    Sanctum::actingAs($user, ['*']);

    $this->postJson(route('api.v1.ledgers.transactions.store', $ledger), [
        'transaction_type' => '',
        'amount' => 0,
        'transaction_date' => '',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['account_id', 'transaction_type', 'amount', 'transaction_date']);
});

test('transaction api create returns created transaction contract', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create();
    $category = Category::factory()->for($ledger)->create();
    $payee = Payee::factory()->for($ledger)->create();

    Sanctum::actingAs($user, ['*']);

    $response = $this->postJson(route('api.v1.ledgers.transactions.store', $ledger), [
        'account_id' => $account->id,
        'category_id' => $category->id,
        'payee_id' => $payee->id,
        'transaction_type' => 'expense',
        'amount' => 20.25,
        'description' => 'API coffee',
        'notes' => 'Morning run',
        'transaction_date' => '2026-03-13',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.ledger_id', $ledger->id)
        ->assertJsonPath('data.account_id', $account->id)
        ->assertJsonPath('data.category_id', $category->id)
        ->assertJsonPath('data.payee_id', $payee->id)
        ->assertJsonPath('data.transaction_type', 'expense')
        ->assertJsonPath('data.amount', '-20.25')
        ->assertJsonPath('data.description', 'API coffee');

    expect($ledger->transactions()->count())->toBe(1);
});

test('transaction api create rejects related ids from another ledger', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();

    $foreignLedger = Ledger::factory()->create();
    $foreignAccountType = AccountType::factory()->for($foreignLedger)->create();
    $foreignAccount = Account::factory()->for($foreignLedger)->for($foreignAccountType)->create();
    $foreignCategory = Category::factory()->for($foreignLedger)->create();
    $foreignPayee = Payee::factory()->for($foreignLedger)->create();

    Sanctum::actingAs($user, ['*']);

    $this->postJson(route('api.v1.ledgers.transactions.store', $ledger), [
        'account_id' => $foreignAccount->id,
        'category_id' => $foreignCategory->id,
        'payee_id' => $foreignPayee->id,
        'transaction_type' => 'expense',
        'amount' => 20.25,
        'transaction_date' => '2026-03-13',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['account_id', 'category_id', 'payee_id']);
});

test('transaction api create enforces free-plan account restriction as validation error', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    $accounts = Account::factory()->for($ledger)->for($accountType)->count(8)->create();
    $category = Category::factory()->for($ledger)->create();

    Sanctum::actingAs($user, ['*']);

    $eighthAccount = $accounts->sortBy('id')->values()->get(7);

    $this->postJson(route('api.v1.ledgers.transactions.store', $ledger), [
        'account_id' => $eighthAccount->id,
        'category_id' => $category->id,
        'transaction_type' => 'expense',
        'amount' => 50.00,
        'transaction_date' => '2026-03-26',
    ])
        ->assertUnprocessable()
        ->assertJsonPath('errors.account_id.0', 'This account is not available on the free plan.');
});

test('transaction api update returns updated transaction json', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create();
    $newAccount = Account::factory()->for($ledger)->for($accountType)->create();
    $category = Category::factory()->for($ledger)->create();
    $transaction = Transaction::factory()->for($ledger)->for($account)->for($category)->create([
        'amount' => '-10.00',
        'description' => 'Before update',
        'transaction_date' => '2026-03-13',
    ]);

    Sanctum::actingAs($user, ['*']);

    $this->patchJson(route('api.v1.ledgers.transactions.update', [$ledger, $transaction]), [
        'account_id' => $newAccount->id,
        'category_id' => $category->id,
        'transaction_type' => 'expense',
        'amount' => 45.00,
        'description' => 'Updated through api',
        'notes' => 'Updated notes',
        'transaction_date' => '2026-03-20',
    ])
        ->assertSuccessful()
        ->assertJsonPath('data.id', $transaction->id)
        ->assertJsonPath('data.account_id', $newAccount->id)
        ->assertJsonPath('data.amount', '-45.00')
        ->assertJsonPath('data.description', 'Updated through api');

    expect((float) $transaction->fresh()->amount)->toBe(-45.00);
});

test('transaction api destroy deletes the transaction', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create();
    $transaction = Transaction::factory()->for($ledger)->for($account)->create();

    Sanctum::actingAs($user, ['*']);

    $this->deleteJson(route('api.v1.ledgers.transactions.destroy', [$ledger, $transaction]))
        ->assertSuccessful()
        ->assertJsonPath('data.id', $transaction->id);

    expect(Transaction::query()->whereKey($transaction->id)->exists())->toBeFalse();
});

test('transaction api index lists transactions using filter contract', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    $checking = Account::factory()->for($ledger)->for($accountType)->create(['name' => 'Checking']);
    $savings = Account::factory()->for($ledger)->for($accountType)->create(['name' => 'Savings']);
    $category = Category::factory()->for($ledger)->create();

    $matching = Transaction::factory()->for($ledger)->for($checking)->for($category)->create([
        'description' => 'Coffee beans',
        'notes' => 'morning setup',
        'transaction_date' => '2026-03-20',
    ]);

    Transaction::factory()->for($ledger)->for($savings)->for($category)->create([
        'description' => 'Savings move',
        'notes' => 'monthly transfer',
        'transaction_date' => '2026-03-19',
    ]);

    Sanctum::actingAs($user, ['*']);

    $this->getJson(route('api.v1.ledgers.transactions.index', [
        'ledger' => $ledger,
        'search' => 'coffee',
        'account_ids' => (string) $checking->id,
    ]))
        ->assertSuccessful()
        ->assertJsonPath('data.0.id', $matching->id)
        ->assertJsonPath('meta.filters.search', 'coffee')
        ->assertJsonPath('meta.filters.account_ids.0', (string) $checking->id);
});

test('transaction api index returns only uncategorized non-transfer transactions when requested', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create();
    $category = Category::factory()->for($ledger)->create();

    Transaction::factory()->for($ledger)->for($account)->for($category)->create([
        'description' => 'Has category',
        'transaction_date' => '2026-03-20',
    ]);

    $uncategorized = Transaction::factory()->for($ledger)->for($account)->create([
        'description' => 'No category',
        'category_id' => null,
        'transaction_date' => '2026-03-19',
    ]);

    Transaction::factory()->transferOut()->for($ledger)->for($account)->create([
        'description' => 'Transfer no category',
        'transaction_date' => '2026-03-18',
    ]);

    Sanctum::actingAs($user, ['*']);

    $this->getJson(route('api.v1.ledgers.transactions.index', [
        'ledger' => $ledger,
        'uncategorized' => '1',
    ]))
        ->assertSuccessful()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $uncategorized->id)
        ->assertJsonPath('meta.filters.uncategorized', '1');
});

test('transaction api dashboard summary returns the selected cycle summary payload', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create(['cycle_start_day' => 1]);
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create();
    $category = Category::factory()->for($ledger)->create();

    $now = CarbonImmutable::now();
    ['start' => $previousStart] = $ledger->cycleBounds($now->subMonthNoOverflow());

    Transaction::factory()->for($ledger)->for($account)->for($category)->create([
        'transaction_type' => 'income',
        'amount' => '200.00',
        'transaction_date' => $previousStart->addDay()->toDateString(),
    ]);

    Transaction::factory()->for($ledger)->for($account)->for($category)->create([
        'transaction_type' => 'expense',
        'amount' => '-55.00',
        'transaction_date' => $previousStart->addDays(2)->toDateString(),
    ]);

    Sanctum::actingAs($user, ['*']);

    $this->getJson(route('api.v1.ledgers.transactions.dashboard-summary', [
        'ledger' => $ledger,
        'offset' => -1,
    ]))
        ->assertSuccessful()
        ->assertJsonPath('data.cycle.offset', -1)
        ->assertJsonPath('data.summary.income', 200.0)
        ->assertJsonPath('data.summary.expense', -55.0)
        ->assertJsonPath('data.summary.net', 145.0);
});

test('transaction api dashboard daily trend returns cycle-scoped trend data', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create(['cycle_start_day' => 1]);
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create();
    $category = Category::factory()->for($ledger)->create();

    $now = CarbonImmutable::now();
    ['start' => $previousStart] = $ledger->cycleBounds($now->subMonthNoOverflow());
    ['start' => $currentStart] = $ledger->cycleBounds($now);

    Transaction::factory()->for($ledger)->for($account)->for($category)->expense()->create([
        'amount' => '-40.00',
        'transaction_date' => $previousStart->addDay()->toDateString(),
    ]);

    Transaction::factory()->for($ledger)->for($account)->for($category)->expense()->create([
        'amount' => '-99.00',
        'transaction_date' => $currentStart->addDay()->toDateString(),
    ]);

    Sanctum::actingAs($user, ['*']);

    $response = $this->getJson(route('api.v1.ledgers.transactions.dashboard-daily-trend', [
        'ledger' => $ledger,
        'offset' => -1,
    ]));

    $response->assertSuccessful();

    expect(collect($response->json('data'))
        ->firstWhere('date', $previousStart->addDay()->toDateString()))
        ->toMatchArray([
            'date' => $previousStart->addDay()->toDateString(),
            'expense' => 40.0,
        ]);
});

test('transaction api dashboard recent returns only transactions from the selected cycle ordered newest first', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create(['cycle_start_day' => 1]);
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create();
    $category = Category::factory()->for($ledger)->create();

    $now = CarbonImmutable::now();
    ['start' => $previousStart] = $ledger->cycleBounds($now->subMonthNoOverflow());
    ['start' => $currentStart] = $ledger->cycleBounds($now);

    $older = Transaction::factory()->for($ledger)->for($account)->for($category)->create([
        'description' => 'Older previous-cycle transaction',
        'transaction_date' => $previousStart->addDay()->toDateString(),
    ]);

    $newer = Transaction::factory()->for($ledger)->for($account)->for($category)->create([
        'description' => 'Newer previous-cycle transaction',
        'transaction_date' => $previousStart->addDays(2)->toDateString(),
    ]);

    Transaction::factory()->for($ledger)->for($account)->for($category)->create([
        'description' => 'Current-cycle transaction',
        'transaction_date' => $currentStart->addDay()->toDateString(),
    ]);

    Sanctum::actingAs($user, ['*']);

    $response = $this->getJson(route('api.v1.ledgers.transactions.dashboard-recent', [
        'ledger' => $ledger,
        'offset' => -1,
    ]));

    $response->assertSuccessful()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.id', $newer->id)
        ->assertJsonPath('data.1.id', $older->id);
});

test('transaction api index includes transfer pair account data and split line relations for mobile rendering', function () {
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
    $pairId = (string) Str::uuid();

    $transferOut = Transaction::factory()->transferOut()->for($ledger)->for($sourceAccount)->create([
        'description' => 'Move to savings',
        'transaction_date' => '2026-03-21',
        'transfer_pair_id' => $pairId,
    ]);

    Transaction::factory()->transferIn()->for($ledger)->for($destinationAccount)->create([
        'description' => 'Move to savings',
        'transaction_date' => '2026-03-21',
        'transfer_pair_id' => $pairId,
    ]);

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

    Sanctum::actingAs($user, ['*']);

    $response = $this->getJson(route('api.v1.ledgers.transactions.index', $ledger));

    $response->assertSuccessful()
        ->assertJsonCount(3, 'data');

    $transactions = collect($response->json('data'));

    $transferPayload = $transactions->firstWhere('id', $transferOut->id);
    $splitPayload = $transactions->firstWhere('id', $splitTransaction->id);

    expect($transferPayload)->not->toBeNull()
        ->and($splitPayload)->not->toBeNull()
        ->and(data_get($transferPayload, 'transfer_pair.id'))->toBeInt()
        ->and(data_get($transferPayload, 'transfer_pair.account.name'))->toBe('Savings')
        ->and($splitPayload['splits'] ?? [])->toHaveCount(2);

    $splitLines = collect($splitPayload['splits']);
    $groceries = $splitLines->firstWhere('description', 'Groceries');
    $baggageFee = $splitLines->firstWhere('description', 'Baggage fee');

    expect(data_get($groceries, 'category.name'))->toBe('Food')
        ->and(data_get($groceries, 'payee.name'))->toBe('Market')
        ->and(data_get($baggageFee, 'category.name'))->toBe('Travel')
        ->and(data_get($baggageFee, 'payee.name'))->toBe('Airline');
});

test('transaction api index keeps counterpart account data when only one transfer side is on the current page', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    $sourceAccount = Account::factory()->for($ledger)->for($accountType)->create([
        'name' => 'Checking',
    ]);
    $destinationAccount = Account::factory()->for($ledger)->for($accountType)->create([
        'name' => 'Savings',
    ]);
    $pairId = (string) Str::uuid();

    $transferOut = Transaction::factory()->transferOut()->for($ledger)->for($sourceAccount)->create([
        'description' => 'Move to savings',
        'transaction_date' => '2026-03-22',
        'transfer_pair_id' => $pairId,
    ]);

    Transaction::factory()->transferIn()->for($ledger)->for($destinationAccount)->create([
        'description' => 'Move to savings',
        'transaction_date' => '2026-03-22',
        'transfer_pair_id' => $pairId,
    ]);

    Transaction::factory()->for($ledger)->for($sourceAccount)->create([
        'description' => 'Later transaction',
        'transaction_date' => '2026-03-23',
    ]);

    Transaction::factory()->for($ledger)->for($sourceAccount)->create([
        'description' => 'Earlier transaction',
        'transaction_date' => '2026-03-21',
    ]);

    Sanctum::actingAs($user, ['*']);

    $response = $this->getJson(route('api.v1.ledgers.transactions.index', [
        'ledger' => $ledger,
        'per_page' => 2,
        'page' => 2,
    ]));

    $response->assertSuccessful()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('meta.current_page', 2)
        ->assertJsonPath('data.0.id', $transferOut->id);

    expect(data_get($response->json('data.0'), 'transfer_pair.account.name'))->toBe('Savings');
});

test('transaction api index transfer backfill stays within the current ledger', function () {
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

    Sanctum::actingAs($user, ['*']);

    $response = $this->getJson(route('api.v1.ledgers.transactions.index', [
        'ledger' => $ledger,
        'per_page' => 2,
        'page' => 2,
    ]));

    $response->assertSuccessful()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.id', $transferOut->id);

    $transferPayload = $response->json('data.0');

    expect(data_get($transferPayload, 'transfer_pair.ledger_id'))->toBe($ledger->id)
        ->and(data_get($transferPayload, 'transfer_pair.account.name'))->toBe('Savings')
        ->and(data_get($transferPayload, 'transfer_pair.account.name'))->not->toBe('Foreign account');
});

test('transaction api index requires sanctum authentication', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();

    $this->getJson(route('api.v1.ledgers.transactions.index', $ledger))
        ->assertUnauthorized();
});

test('transaction api bulk update applies to all matching filtered transactions', function () {
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

    Sanctum::actingAs($user, ['*']);

    $this->postJson(route('api.v1.ledgers.transactions.bulk-update', $ledger), [
        'apply_to_all_matching' => true,
        'excluded_ids' => [$excluded->id],
        'filters' => [
            'account_ids' => [$matchingAccount->id],
        ],
        'action' => 'change_category',
        'value' => $newCategory->id,
    ])->assertNoContent();

    foreach ($included as $transaction) {
        expect($transaction->fresh()->category_id)->toBe($newCategory->id);
    }

    expect($excluded->fresh()->category_id)->toBe($originalCategory->id)
        ->and($outsideFilter->fresh()->category_id)->toBe($originalCategory->id);
});

test('transaction api bulk destroy deletes transfer pairs together', function () {
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

    Sanctum::actingAs($user, ['*']);

    $this->postJson(route('api.v1.ledgers.transactions.bulk-destroy', $ledger), [
        'ids' => [$source->id],
    ])->assertNoContent();

    expect(Transaction::query()->whereKey($source->id)->exists())->toBeFalse()
        ->and(Transaction::query()->whereKey($paired->id)->exists())->toBeFalse();
});

test('transaction api select-all returns ids for matching filters', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    $checking = Account::factory()->for($ledger)->for($accountType)->create();
    $savings = Account::factory()->for($ledger)->for($accountType)->create();
    $category = Category::factory()->for($ledger)->create();

    $matching = Transaction::factory()->for($ledger)->for($checking)->for($category)->create([
        'description' => 'Coffee beans',
        'transaction_date' => '2026-03-20',
    ]);

    Transaction::factory()->for($ledger)->for($savings)->for($category)->create([
        'description' => 'Savings move',
        'transaction_date' => '2026-03-19',
    ]);

    Sanctum::actingAs($user, ['*']);

    $this->postJson(route('api.v1.ledgers.transactions.select-all', $ledger), [
        'search' => 'coffee',
        'account_ids' => [$checking->id],
    ])
        ->assertSuccessful()
        ->assertJsonPath('data.ids.0', $matching->id)
        ->assertJsonCount(1, 'data.ids');
});

test('transaction attachment api lists attachments', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create();
    $transaction = Transaction::factory()->for($ledger)->for($account)->create();
    $attachment = $transaction->attachments()->create([
        'filename' => 'receipt.pdf',
        'path' => "attachments/{$ledger->id}/receipt.pdf",
        'mime_type' => 'application/pdf',
        'size' => 256,
    ]);

    Sanctum::actingAs($user, ['*']);

    $this->getJson(route('api.v1.ledgers.transactions.attachments.index', [$ledger, $transaction]))
        ->assertSuccessful()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $attachment->id)
        ->assertJsonPath('data.0.filename', 'receipt.pdf');
});

test('transaction attachment api stores attachments', function () {
    Storage::fake('local');

    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create();
    $transaction = Transaction::factory()->for($ledger)->for($account)->create();
    $file = UploadedFile::fake()->create('receipt.pdf', 256, 'application/pdf');

    Sanctum::actingAs($user, ['*']);

    $response = $this
        ->withHeader('Accept', 'application/json')
        ->post(route('api.v1.ledgers.transactions.attachments.store', [$ledger, $transaction]), [
            'file' => $file,
        ]);

    $response->assertCreated()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.filename', 'receipt.pdf');

    expect($transaction->attachments()->count())->toBe(1);
});

test('transaction attachment api destroys attachment', function () {
    Storage::fake('local');

    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create();
    $transaction = Transaction::factory()->for($ledger)->for($account)->create();
    Storage::disk('local')->put("attachments/{$ledger->id}/receipt.pdf", 'fake');
    $attachment = Attachment::query()->create([
        'transaction_id' => $transaction->id,
        'filename' => 'receipt.pdf',
        'path' => "attachments/{$ledger->id}/receipt.pdf",
        'mime_type' => 'application/pdf',
        'size' => 256,
    ]);

    Sanctum::actingAs($user, ['*']);

    $this->deleteJson(route('api.v1.ledgers.transactions.attachments.destroy', [$ledger, $transaction, $attachment]))
        ->assertSuccessful()
        ->assertJsonPath('data.id', $attachment->id);

    expect(Attachment::query()->whereKey($attachment->id)->exists())->toBeFalse();
    Storage::disk('local')->assertMissing($attachment->path);
});
