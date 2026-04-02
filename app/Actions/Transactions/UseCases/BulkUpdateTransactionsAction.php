<?php

namespace App\Actions\Transactions\UseCases;

use App\Actions\Transactions\Queries\SelectAllTransactionIdsQuery;
use App\Data\Transactions\Input\BulkUpdateTransactionsData;
use App\Enums\TransactionType;

class BulkUpdateTransactionsAction
{
    public function __construct(
        private readonly SelectAllTransactionIdsQuery $selectAllTransactionIds,
    ) {}

    /**
     * @return list<int>
     */
    public function __invoke(BulkUpdateTransactionsData $data): array
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

        $field = match ($data->action) {
            'change_category' => 'category_id',
            'change_account' => 'account_id',
            'change_payee' => 'payee_id',
        };

        $data->ledger->transactions()
            ->whereIn('id', $ids)
            ->where('transaction_type', '!=', TransactionType::Transfer)
            ->update([$field => $data->value]);

        return $ids;
    }
}
