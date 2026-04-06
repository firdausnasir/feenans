<?php

namespace App\Actions\Transactions\Queries;

use App\Data\Transactions\Output\Web\TransactionFiltersData;
use App\Enums\TransactionType;

class ApplyTransactionFiltersQuery
{
    public function __invoke(mixed $query, TransactionFiltersData $filters): void
    {
        if ($filters->date_from !== '') {
            $query->where('transaction_date', '>=', $filters->date_from);
        }

        if ($filters->date_to !== '') {
            $query->where('transaction_date', '<=', $filters->date_to);
        }

        if ($filters->account_ids !== []) {
            $query->whereIn('account_id', $filters->account_ids);
        }

        if ($filters->category_ids !== []) {
            $query->whereIn('category_id', $filters->category_ids);
        }

        if ($filters->transaction_types !== []) {
            $query->whereIn('transaction_type', $filters->transaction_types);
        }

        if ($filters->payee_ids !== []) {
            $query->whereIn('payee_id', $filters->payee_ids);
        }

        if ($filters->tag_ids !== []) {
            $query->whereHas('tags', fn ($tagQuery) => $tagQuery->whereIn('tags.id', $filters->tag_ids));
        }

        if ($filters->search !== null && $filters->search !== '') {
            $search = $filters->search;
            $query->where(fn ($transactionQuery) => $transactionQuery
                ->where('description', 'like', "%{$search}%")
                ->orWhere('notes', 'like', "%{$search}%"));
        }

        if ($filters->bill_id !== null && $filters->bill_id !== '') {
            $query->where('bill_id', $filters->bill_id);
        }

        if ($filters->uncategorized === '1') {
            $query->whereNull('category_id')
                ->where('transaction_type', '!=', TransactionType::Transfer);
        }
    }
}
