<?php

namespace App\Actions\Transactions\UseCases;

use App\Actions\Transactions\Queries\SelectAllTransactionIdsQuery;
use App\Data\Transactions\Input\BulkDestroyTransactionsData;

class BulkDestroyTransactionsAction
{
    public function __construct(
        private readonly SelectAllTransactionIdsQuery $selectAllTransactionIds,
    ) {}

    /**
     * @return list<int>
     */
    public function __invoke(BulkDestroyTransactionsData $data): array
    {
        $ids = $data->apply_to_all_matching === true
            ? ($this->selectAllTransactionIds)($data->ledger, $data->filters ?? [])
            : array_map('intval', $data->ids ?? []);

        if ($data->apply_to_all_matching === true && $data->excluded_ids !== null) {
            $ids = array_values(array_diff($ids, array_map('intval', $data->excluded_ids)));
        }

        if ($ids === []) {
            return [];
        }

        $transferPairIds = $data->ledger->transactions()
            ->whereIn('id', $ids)
            ->whereNotNull('transfer_pair_id')
            ->pluck('transfer_pair_id')
            ->all();

        $pairedTransactionIds = $transferPairIds !== []
            ? $data->ledger->transactions()
                ->whereIn('transfer_pair_id', $transferPairIds)
                ->pluck('id')
                ->map(static fn (mixed $id): int => (int) $id)
                ->all()
            : [];

        $allIds = array_values(array_unique([
            ...$ids,
            ...$pairedTransactionIds,
        ]));

        $data->ledger->transactions()->whereIn('id', $allIds)->delete();

        return $allIds;
    }
}
