<?php

namespace App\Jobs;

use App\Actions\Transactions\UseCases\StoreTransactionAction;
use App\Data\Transactions\Input\StoreTransactionData;
use App\Enums\TransactionType;
use App\Models\User;
use App\Notifications\TransactionWebhookFailed;
use App\Services\TransactionCategoryGuesser;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class ProcessTransactionWebhook implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * @var list<int>
     */
    public array $backoff = [10, 60, 300];

    /**
     * @param  array{
     *     user_id:int,
     *     ledger_id:int,
     *     account_id:int,
     *     account_name:string,
     *     transaction_type:string,
     *     amount:float,
     *     transaction_date:string,
     *     description:string
     * }  $payload
     */
    public function __construct(public readonly array $payload)
    {
        $this->onQueue('webhooks');
    }

    public function handle(
        StoreTransactionAction $storeTransaction,
        TransactionCategoryGuesser $categoryGuesser,
    ): void {
        $user = User::query()->findOrFail($this->payload['user_id']);
        $ledger = $user->ledgers()->whereKey($this->payload['ledger_id'])->firstOrFail();
        $account = $ledger->accounts()->whereKey($this->payload['account_id'])->firstOrFail();
        $transactionType = TransactionType::from($this->payload['transaction_type']);
        $categoryId = $categoryGuesser->guess(
            ledger: $ledger,
            transactionType: $transactionType,
            description: $this->payload['description'],
            amount: $this->payload['amount'],
            accountName: $account->name,
        );

        $storeTransaction(new StoreTransactionData(
            account_id: $account->id,
            transaction_type: $transactionType->value,
            amount: $this->payload['amount'],
            transaction_date: $this->payload['transaction_date'],
            category_id: $categoryId,
            description: $this->payload['description'],
            ledger: $ledger,
            user: $user,
        ));
    }

    public function failed(?Throwable $exception): void
    {
        $userId = $this->payload['user_id'] ?? null;

        if (! is_int($userId)) {
            return;
        }

        $user = User::query()->find($userId);

        if (! $user instanceof User) {
            return;
        }

        $user->notify(new TransactionWebhookFailed($this->payload, $exception));
    }
}
