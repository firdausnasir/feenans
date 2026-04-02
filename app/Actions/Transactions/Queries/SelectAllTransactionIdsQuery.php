<?php

namespace App\Actions\Transactions\Queries;

use App\Models\Ledger;

class SelectAllTransactionIdsQuery
{
    public function __construct(
        private readonly NormalizeTransactionFiltersQuery $normalizeFilters,
        private readonly ApplyTransactionFiltersQuery $applyFilters,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return list<int>
     */
    public function __invoke(Ledger $ledger, array $filters): array
    {
        $query = $ledger->transactions()
            ->orderByDesc('transaction_date')
            ->orderByDesc('id');

        ($this->applyFilters)($query, ($this->normalizeFilters)($filters));

        return $query->pluck('id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->values()
            ->all();
    }
}
