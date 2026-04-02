<?php

namespace App\Data\Bills\Output\Web;

use App\Data\Shared\Output\BaseOutputData;
use App\Models\Transaction;

class BillHistoryTransactionData extends BaseOutputData
{
    public function __construct(
        public int $id,
        public int $ledger_id,
        public int $account_id,
        public ?int $category_id,
        public ?int $payee_id,
        public ?int $bill_id,
        public string $transaction_type,
        public string $amount,
        public ?string $description,
        public ?string $notes,
        public string $transaction_date,
        public ?string $transfer_pair_id,
        public ?array $account,
        public ?string $created_at,
        public ?string $updated_at,
    ) {}

    public static function fromModel(Transaction $transaction): self
    {
        return new self(
            id: $transaction->id,
            ledger_id: $transaction->ledger_id,
            account_id: $transaction->account_id,
            category_id: $transaction->category_id,
            payee_id: $transaction->payee_id,
            bill_id: $transaction->bill_id,
            transaction_type: $transaction->transaction_type?->value ?? (string) $transaction->transaction_type,
            amount: (string) $transaction->amount,
            description: $transaction->description,
            notes: $transaction->notes,
            transaction_date: $transaction->transaction_date?->toDateString() ?? '',
            transfer_pair_id: $transaction->transfer_pair_id,
            account: $transaction->relationLoaded('account') && $transaction->account !== null
                ? BillAccountOptionData::fromModel($transaction->account)->toArray()
                : null,
            created_at: $transaction->created_at?->toIso8601String(),
            updated_at: $transaction->updated_at?->toIso8601String(),
        );
    }
}
