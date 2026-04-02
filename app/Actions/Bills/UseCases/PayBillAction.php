<?php

namespace App\Actions\Bills\UseCases;

use App\Data\Bills\Input\PayBillData;
use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\Bill;
use App\Models\Transaction;
use App\Services\TransactionService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class PayBillAction
{
    public function __construct(private readonly TransactionService $transactionService) {}

    public function __invoke(PayBillData $data): Transaction
    {
        return DB::transaction(function () use ($data): Transaction {
            $type = $data->bill->transaction_type;

            if ($type === TransactionType::Transfer) {
                return $this->payTransferBill($data);
            }

            $rawAmount = abs((float) ($data->amount ?? $data->bill->amount));
            $amount = $type === TransactionType::Income ? $rawAmount : -$rawAmount;

            $transaction = $data->ledger->transactions()->create([
                'account_id' => $data->account_id ?? $data->bill->account_id,
                'category_id' => $data->category_id ?? $data->bill->category_id,
                'payee_id' => $data->payee_id ?? $data->bill->payee_id,
                'transaction_type' => $type,
                'amount' => $amount,
                'description' => $data->bill->name,
                'notes' => null,
                'transaction_date' => $data->date ?? CarbonImmutable::today(),
                'transfer_pair_id' => null,
                'bill_id' => $data->bill->id,
            ]);

            $this->advanceToNextDue($data->bill);
            $this->syncBillStateAfterPayment($data->bill);

            return $transaction;
        });
    }

    private function payTransferBill(PayBillData $data): Transaction
    {
        $toAccountId = $data->to_account_id ?? $data->bill->to_account_id;

        /** @var Account $fromAccount */
        $fromAccount = Account::query()->findOrFail($data->account_id ?? $data->bill->account_id);

        /** @var Account $toAccount */
        $toAccount = Account::query()->findOrFail($toAccountId);

        [$outgoing] = $this->transactionService->storeTransfer($data->ledger, [
            'from_account' => $fromAccount,
            'to_account' => $toAccount,
            'amount' => $data->amount ?? $data->bill->amount,
            'description' => $data->bill->name,
            'notes' => null,
            'transaction_date' => $data->date ?? CarbonImmutable::today(),
            'bill_id' => $data->bill->id,
        ]);

        $this->advanceToNextDue($data->bill);
        $this->syncBillStateAfterPayment($data->bill);

        return $outgoing;
    }

    private function advanceToNextDue(Bill $bill): void
    {
        $bill->update([
            'next_due_date' => $bill->nextDueDateAfter($bill->next_due_date),
        ]);
    }

    private function syncBillStateAfterPayment(Bill $bill): void
    {
        $bill->increment('occurrences_count');
        $bill->refresh();

        if ($bill->hasReachedEnd()) {
            $bill->update(['is_active' => false]);
        }
    }
}
