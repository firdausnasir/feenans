<?php

use App\Actions\Transactions\UseCases\StoreTransactionAction;
use App\Enums\ApiTokenAbility;
use App\Jobs\ProcessTransactionWebhook;
use App\Models\Account;
use App\Models\AccountType;
use App\Models\Category;
use App\Models\Ledger;
use App\Models\Transaction;
use App\Models\User;
use App\Notifications\TransactionWebhookFailed;
use App\Services\TransactionCategoryGuesser;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;

test('transaction webhook requires a bearer token with the webhook ability', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    Account::factory()->for($ledger)->for($accountType)->create(['name' => 'Maybank']);

    $payload = [
        'amount' => 'MYR 12.35',
        'account' => 'Maybank',
        'description' => 'Nasi lemak breakfast',
    ];

    $this->postJson(route('api.v1.webhooks.transactions.store'), $payload)
        ->assertUnauthorized();

    $this->actingAs($user)
        ->postJson(route('api.v1.webhooks.transactions.store'), $payload)
        ->assertForbidden();

    $token = $user->createToken('Read only', ['read']);

    $this->withToken($token->plainTextToken)
        ->postJson(route('api.v1.webhooks.transactions.store'), $payload)
        ->assertForbidden();
});

test('transaction webhook queues a job on the default connection and returns only a general accepted message', function () {
    Queue::fake();
    CarbonImmutable::setTestNow('2026-05-07 10:15:00');

    $user = User::factory()->create(['timezone' => 'Asia/Kuala_Lumpur']);
    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create(['name' => 'Maybank']);
    $token = $user->createToken('Webhook', [ApiTokenAbility::transactionWebhookForLedger($ledger->id)]);

    $this->withToken($token->plainTextToken)
        ->postJson(route('api.v1.webhooks.transactions.store'), [
            'amount' => 'MYR 12.35',
            'type' => null,
            'date' => null,
            'account' => 'Maybank',
            'description' => 'Nasi lemak breakfast',
        ])
        ->assertAccepted()
        ->assertExactJson([
            'message' => 'Transaction webhook accepted for processing.',
        ]);

    Queue::assertPushedOn(
        'webhooks',
        ProcessTransactionWebhook::class,
        fn (ProcessTransactionWebhook $job): bool => $job->connection === null
            && $job->payload['user_id'] === $user->id
            && $job->payload['ledger_id'] === $ledger->id
            && $job->payload['account_id'] === $account->id
            && $job->payload['transaction_type'] === 'expense'
            && $job->payload['amount'] === 12.35
            && $job->payload['transaction_date'] === '2026-05-07'
            && $job->payload['description'] === 'Nasi lemak breakfast',
    );

    CarbonImmutable::setTestNow();
});

test('transaction webhook uses the provided ledger id when present', function () {
    Queue::fake();

    $user = User::factory()->create();
    Ledger::factory()->for($user)->create();
    $targetLedger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($targetLedger)->create();
    $account = Account::factory()->for($targetLedger)->for($accountType)->create(['name' => 'CIMB']);
    $token = $user->createToken('Webhook', [ApiTokenAbility::TransactionWebhook->value]);

    $this->withToken($token->plainTextToken)
        ->postJson(route('api.v1.webhooks.transactions.store'), [
            'ledger_id' => $targetLedger->id,
            'amount' => 'MYR 99.90',
            'type' => 'income',
            'date' => '2026-05-01',
            'account' => 'CIMB',
            'description' => 'Freelance payment',
        ])
        ->assertAccepted();

    Queue::assertPushed(
        ProcessTransactionWebhook::class,
        fn (ProcessTransactionWebhook $job): bool => $job->payload['ledger_id'] === $targetLedger->id
            && $job->payload['account_id'] === $account->id
            && $job->payload['transaction_type'] === 'income',
    );
});

test('generic transaction webhook token requires a ledger id', function () {
    Queue::fake();

    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    Account::factory()->for($ledger)->for($accountType)->create(['name' => 'Maybank']);
    $token = $user->createToken('Webhook', [ApiTokenAbility::TransactionWebhook->value]);

    $this->withToken($token->plainTextToken)
        ->postJson(route('api.v1.webhooks.transactions.store'), [
            'amount' => 'MYR 12.35',
            'account' => 'Maybank',
            'description' => 'Missing ledger id',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['ledger_id']);

    Queue::assertNothingPushed();
});

test('ledger scoped transaction webhook token rejects a different payload ledger id', function () {
    Queue::fake();

    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $otherLedger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    Account::factory()->for($ledger)->for($accountType)->create(['name' => 'Maybank']);
    $token = $user->createToken('Webhook', [ApiTokenAbility::transactionWebhookForLedger($ledger->id)]);

    $this->withToken($token->plainTextToken)
        ->postJson(route('api.v1.webhooks.transactions.store'), [
            'ledger_id' => $otherLedger->id,
            'amount' => 'MYR 12.35',
            'account' => 'Maybank',
            'description' => 'Wrong ledger',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['ledger_id']);

    Queue::assertNothingPushed();
});

test('transaction webhook rejects accounts outside the resolved ledger', function () {
    Queue::fake();

    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $foreignLedger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($foreignLedger)->create();
    Account::factory()->for($foreignLedger)->for($accountType)->create(['name' => 'Foreign Account']);
    $token = $user->createToken('Webhook', [ApiTokenAbility::transactionWebhookForLedger($ledger->id)]);

    $this->withToken($token->plainTextToken)
        ->postJson(route('api.v1.webhooks.transactions.store'), [
            'amount' => 'MYR 10.00',
            'account' => 'Foreign Account',
            'description' => 'Should fail',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['account']);

    Queue::assertNothingPushed();
});

test('transaction webhook job creates a transaction with an existing ai selected category', function () {
    config()->set('services.deepseek.key', 'test-key');
    config()->set('services.deepseek.base_url', 'https://api.deepseek.test');
    config()->set('services.deepseek.model', 'deepseek-v4-flash');

    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create(['name' => 'Maybank']);
    $parent = Category::factory()->for($ledger)->create([
        'name' => 'Food & Drinks',
        'transaction_type' => 'expense',
    ]);
    $category = Category::factory()->for($ledger)->create([
        'name' => 'Coffee & Tea',
        'parent_id' => $parent->id,
        'transaction_type' => 'expense',
    ]);

    Http::fake([
        'api.deepseek.test/*' => Http::response([
            'choices' => [[
                'message' => [
                    'content' => json_encode([
                        'category_id' => $category->id,
                        'confidence' => 0.92,
                        'reason' => 'Coffee purchase',
                    ]),
                ],
            ]],
        ]),
    ]);

    $job = new ProcessTransactionWebhook([
        'user_id' => $user->id,
        'ledger_id' => $ledger->id,
        'account_id' => $account->id,
        'account_name' => $account->name,
        'transaction_type' => 'expense',
        'amount' => 12.35,
        'transaction_date' => '2026-05-07',
        'description' => 'Kopitiam iced coffee',
    ]);

    $job->handle(app(StoreTransactionAction::class), app(TransactionCategoryGuesser::class));

    $transaction = Transaction::query()->firstOrFail();

    expect($transaction->ledger_id)->toBe($ledger->id)
        ->and($transaction->account_id)->toBe($account->id)
        ->and($transaction->category_id)->toBe($category->id)
        ->and($transaction->transaction_type->value)->toBe('expense')
        ->and((float) $transaction->amount)->toBe(-12.35)
        ->and($transaction->description)->toBe('Kopitiam iced coffee')
        ->and($ledger->categories()->count())->toBe(2);

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.deepseek.test/chat/completions'
        && $request['model'] === 'deepseek-v4-flash'
        && $request['response_format']['type'] === 'json_object'
        && str_contains($request['messages'][0]['content'], 'Never invent a category'));
});

test('transaction webhook job leaves transaction uncategorized when ai is uncertain or invalid', function () {
    config()->set('services.deepseek.key', 'test-key');
    config()->set('services.deepseek.base_url', 'https://api.deepseek.test');

    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create(['name' => 'Wallet']);
    Category::factory()->for($ledger)->create([
        'name' => 'Transport',
        'transaction_type' => 'expense',
    ]);

    Http::fake([
        'api.deepseek.test/*' => Http::response([
            'choices' => [[
                'message' => [
                    'content' => json_encode([
                        'category_id' => 999999,
                        'confidence' => 0.99,
                        'reason' => 'Invalid id',
                    ]),
                ],
            ]],
        ]),
    ]);

    $job = new ProcessTransactionWebhook([
        'user_id' => $user->id,
        'ledger_id' => $ledger->id,
        'account_id' => $account->id,
        'account_name' => $account->name,
        'transaction_type' => 'expense',
        'amount' => 8.5,
        'transaction_date' => '2026-05-07',
        'description' => 'Unknown merchant',
    ]);

    $job->handle(app(StoreTransactionAction::class), app(TransactionCategoryGuesser::class));

    $transaction = Transaction::query()->firstOrFail();

    expect($transaction->category_id)->toBeNull()
        ->and($ledger->categories()->count())->toBe(1);
});

test('transaction webhook failed job notifies the token owner by mail', function () {
    Notification::fake();

    $user = User::factory()->create();

    $job = new ProcessTransactionWebhook([
        'user_id' => $user->id,
        'ledger_id' => 123,
        'account_id' => 456,
        'account_name' => 'Maybank',
        'transaction_type' => 'expense',
        'amount' => 12.35,
        'transaction_date' => '2026-05-07',
        'description' => 'Webhook payload',
    ]);

    $job->failed(new RuntimeException('Ledger missing'));

    Notification::assertSentTo(
        $user,
        TransactionWebhookFailed::class,
        fn (TransactionWebhookFailed $notification, array $channels): bool => $channels === ['mail']
            && $notification->payload['description'] === 'Webhook payload'
            && $notification->exception?->getMessage() === 'Ledger missing',
    );
});
