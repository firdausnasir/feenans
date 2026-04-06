<?php

namespace App\Data\Transactions\Output;

use App\Data\Shared\Output\BaseOutputData;
use App\Enums\TransactionType;
use App\Models\Transaction;

class TransactionExportRowData extends BaseOutputData
{
    public function __construct(
        public string $date,
        public string $description,
        public string $type,
        public string $account,
        public string $category,
        public string $payee,
        public string $amount,
        public string $notes,
    ) {}

    public static function fromTransaction(Transaction $transaction): self
    {
        return new self(
            date: $transaction->transaction_date->toDateString(),
            description: $transaction->description ?? '',
            type: $transaction->transaction_type instanceof TransactionType
                ? $transaction->transaction_type->value
                : (string) $transaction->transaction_type,
            account: $transaction->account?->name ?? '',
            category: $transaction->category?->name ?? '',
            payee: $transaction->payee?->name ?? '',
            amount: number_format((float) $transaction->amount, 2, '.', ''),
            notes: $transaction->notes ?? '',
        );
    }

    /**
     * @return list<string>
     */
    public static function csvHeaders(): array
    {
        return ['Date', 'Description', 'Type', 'Account', 'Category', 'Payee', 'Amount', 'Notes'];
    }

    /**
     * @return list<string>
     */
    public function toCsvRow(): array
    {
        return [
            $this->date,
            $this->description,
            $this->type,
            $this->account,
            $this->category,
            $this->payee,
            $this->amount,
            $this->notes,
        ];
    }
}
