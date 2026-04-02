<?php

namespace App\Actions\Transactions\UseCases;

use App\Models\Transaction;
use App\Services\TransactionService;

class DeleteTransactionAction
{
    public function __construct(private readonly TransactionService $transactionService) {}

    public function __invoke(Transaction $transaction): Transaction
    {
        $transaction->load([
            'account',
            'category',
            'payee',
            'tags',
            'splits.category',
            'splits.payee',
            'attachments.transaction',
            'transferPair.account',
            'transferPair.category',
            'transferPair.payee',
        ])->loadCount(['splits', 'attachments']);

        $deletedTransaction = clone $transaction;

        $this->transactionService->forceDelete($transaction);

        return $deletedTransaction;
    }
}
