<?php

namespace App\Actions\Transactions\UseCases;

use App\Actions\Budgets\UseCases\CheckBudgetThresholdsAction;
use App\Data\Transactions\Input\UpdateTransactionData;
use App\Enums\TransactionType;
use App\Models\Category;
use App\Models\Payee;
use App\Models\Transaction;
use App\Services\TransactionService;

class UpdateTransactionAction
{
    public function __construct(
        private readonly TransactionService $transactionService,
        private readonly CheckBudgetThresholdsAction $checkBudgetThresholds,
    ) {}

    public function __invoke(UpdateTransactionData $data): Transaction
    {
        $type = TransactionType::from($data->transaction_type);
        $wasTransfer = $data->transaction->transfer_pair_id !== null;
        $isTransfer = $type === TransactionType::Transfer;

        if ($wasTransfer && ! $isTransfer) {
            $transaction = $this->transactionService->convertTransferToSingle($data->transaction, [
                'transaction_type' => $type,
                'account' => $data->ledger->accounts()->findOrFail($data->account_id),
                'category' => $this->resolveCategory($data),
                'payee' => $this->resolvePayee($data),
                'amount' => $data->amount,
                'description' => $data->description,
                'notes' => $data->notes,
                'transaction_date' => $data->transaction_date,
            ]);
        } elseif (! $wasTransfer && $isTransfer) {
            [$transaction] = $this->transactionService->convertSingleToTransfer($data->transaction, $data->ledger, [
                'from_account' => $data->ledger->accounts()->findOrFail($data->account_id),
                'to_account' => $data->ledger->accounts()->findOrFail($data->to_account_id),
                'amount' => $data->amount,
                'description' => $data->description,
                'notes' => $data->notes,
                'transaction_date' => $data->transaction_date,
            ]);
        } elseif ($isTransfer) {
            $transaction = $this->transactionService->update($data->transaction, [
                'account' => $data->ledger->accounts()->findOrFail($data->account_id),
                'to_account' => $data->ledger->accounts()->findOrFail($data->to_account_id),
                'amount' => $data->amount,
                'description' => $data->description,
                'notes' => $data->notes,
                'transaction_date' => $data->transaction_date,
            ]);
        } else {
            $transaction = $this->transactionService->update($data->transaction, [
                'transaction_type' => $type,
                'account' => $data->ledger->accounts()->findOrFail($data->account_id),
                'category' => $this->resolveCategory($data),
                'payee' => $this->resolvePayee($data),
                'amount' => $data->amount,
                'description' => $data->description,
                'notes' => $data->notes,
                'transaction_date' => $data->transaction_date,
                'splits' => $data->splits,
            ]);
        }

        $transaction->tags()->sync($data->tag_ids ?? []);

        ($this->checkBudgetThresholds)($data->ledger, $data->category_id);

        return $this->loadForOutput($transaction);
    }

    private function resolveCategory(UpdateTransactionData $data): ?Category
    {
        if ($data->category_id === null) {
            return null;
        }

        return $data->ledger->categories()->findOrFail($data->category_id);
    }

    private function resolvePayee(UpdateTransactionData $data): ?Payee
    {
        if ($data->payee_id !== null) {
            return $data->ledger->payees()->findOrFail($data->payee_id);
        }

        $newPayeeName = trim((string) $data->new_payee_name);

        if ($newPayeeName === '') {
            return null;
        }

        /** @var Payee $payee */
        $payee = $data->ledger->payees()->create(['name' => $newPayeeName]);

        return $payee;
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
