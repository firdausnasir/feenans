<?php

namespace App\Data\Accounts\Output;

use App\Data\Shared\Output\BaseOutputData;
use App\Enums\TransactionType;
use App\Models\Transaction;

class AccountExportRowData extends BaseOutputData
{
    public function __construct(
        public string $date,
        public string $description,
        public string $type,
        public string $category,
        public string $payee,
        public string $amount,
        public string $running_balance,
        public string $notes,
    ) {}

    public static function fromTransaction(Transaction $transaction, float $runningBalance): self
    {
        return new self(
            date: $transaction->transaction_date->toDateString(),
            description: $transaction->description ?? '',
            type: $transaction->transaction_type instanceof TransactionType
                ? $transaction->transaction_type->value
                : (string) $transaction->transaction_type,
            category: $transaction->category?->name ?? '',
            payee: $transaction->payee?->name ?? '',
            amount: number_format((float) $transaction->amount, 2, '.', ''),
            running_balance: number_format($runningBalance, 2, '.', ''),
            notes: $transaction->notes ?? '',
        );
    }

    /**
     * @return list<string>
     */
    public static function csvHeaders(): array
    {
        return ['Date', 'Description', 'Type', 'Category', 'Payee', 'Amount', 'Running Balance', 'Notes'];
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
            $this->category,
            $this->payee,
            $this->amount,
            $this->running_balance,
            $this->notes,
        ];
    }
}
