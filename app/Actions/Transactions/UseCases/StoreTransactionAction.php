<?php

namespace App\Actions\Transactions\UseCases;

use App\Actions\Budgets\UseCases\CheckBudgetThresholdsAction;
use App\Actions\Payees\UseCases\ResolveLedgerPayeeAction;
use App\Data\Transactions\Input\StoreTransactionData;
use App\Enums\TransactionType;
use App\Models\Category;
use App\Models\Transaction;
use App\Services\TransactionService;

class StoreTransactionAction
{
    public function __construct(
        private readonly TransactionService $transactionService,
        private readonly StoreTransactionAttachmentsAction $storeAttachments,
        private readonly CelebrateFirstTransactionAction $celebrateFirstTransaction,
        private readonly CheckBudgetThresholdsAction $checkBudgetThresholds,
        private readonly ResolveLedgerPayeeAction $resolveLedgerPayee,
    ) {}

    public function __invoke(StoreTransactionData $data): Transaction
    {
        if ($data->transaction_type === TransactionType::Transfer->value) {
            [$outgoing, $incoming] = $this->transactionService->storeTransfer($data->ledger, [
                'from_account' => $data->ledger->accounts()->findOrFail($data->account_id),
                'to_account' => $data->ledger->accounts()->findOrFail($data->to_account_id),
                'amount' => $data->amount,
                'description' => $data->description,
                'notes' => $data->notes,
                'transaction_date' => $data->transaction_date,
            ]);

            if ($data->tag_ids !== null) {
                $outgoing->tags()->sync($data->tag_ids);
                $incoming->tags()->sync($data->tag_ids);
            }

            if ($data->attachments !== null && $data->attachments !== []) {
                ($this->storeAttachments)($data->ledger, $outgoing, $data->attachments);
                ($this->storeAttachments)($data->ledger, $incoming, $data->attachments);
            }

            ($this->celebrateFirstTransaction)($data->user);

            return $this->loadForOutput($outgoing);
        }

        $transaction = $this->transactionService->store([
            'ledger' => $data->ledger,
            'account' => $data->ledger->accounts()->findOrFail($data->account_id),
            'category' => $this->resolveCategory($data),
            'payee' => ($this->resolveLedgerPayee)(
                $data->ledger,
                $data->payee_id,
                $data->new_payee_name,
            ),
            'transaction_type' => TransactionType::from($data->transaction_type),
            'amount' => $data->amount,
            'description' => $data->description,
            'notes' => $data->notes,
            'transaction_date' => $data->transaction_date,
            'splits' => $data->splits,
        ]);

        if ($data->tag_ids !== null) {
            $transaction->tags()->sync($data->tag_ids);
        }

        if ($data->attachments !== null && $data->attachments !== []) {
            ($this->storeAttachments)($data->ledger, $transaction, $data->attachments);
        }

        ($this->checkBudgetThresholds)($data->ledger, $data->category_id);
        ($this->celebrateFirstTransaction)($data->user);

        return $this->loadForOutput($transaction);
    }

    private function resolveCategory(StoreTransactionData $data): ?Category
    {
        if ($data->category_id === null) {
            return null;
        }

        return $data->ledger->categories()->findOrFail($data->category_id);
    }

    private function loadForOutput(Transaction $transaction): Transaction
    {
        return $transaction->load([
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
    }
}
