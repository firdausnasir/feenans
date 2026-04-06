<?php

namespace App\Actions\Transactions\Queries;

use App\Data\Transactions\Output\Web\TransactionData;
use App\Data\Transactions\Output\Web\TransactionFiltersData;
use App\Models\Ledger;
use App\Models\Transaction;
use Illuminate\Pagination\Paginator;

class ListTransactionsQuery
{
    public function __construct(
        private readonly ApplyTransactionFiltersQuery $applyFilters,
        private readonly LoadTransferPairRelationsQuery $loadTransferPairRelations,
    ) {}

    public function __invoke(Ledger $ledger, TransactionFiltersData $filters, int $page, int $perPage): Paginator
    {
        $query = $ledger->transactions()
            ->with([
                'account',
                'category',
                'payee',
                'tags',
                'splits.category',
                'splits.payee',
            ])
            ->withCount('splits')
            ->withCount('attachments')
            ->orderByDesc('transaction_date')
            ->orderByDesc('id');

        ($this->applyFilters)($query, $filters);

        $transactions = $query->simplePaginate($perPage, ['*'], 'page', $page);

        ($this->loadTransferPairRelations)($ledger, $transactions);
        $transactions->setCollection(
            $transactions->getCollection()
                ->map(fn (Transaction $transaction) => TransactionData::fromModel($transaction)->toArray())
        );

        return $transactions;
    }
}
