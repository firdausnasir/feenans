<?php

namespace App\Actions\Transactions\Queries;

use App\Data\Transactions\Input\GetTransactionIndexData;
use App\Data\Transactions\Output\Web\TransactionIndexPageData;
use App\Models\Ledger;

class GetTransactionIndexPageQuery
{
    public function __construct(
        private readonly NormalizeTransactionFiltersQuery $normalizeFilters,
        private readonly ListTransactionAccountsQuery $listAccounts,
        private readonly ListTransactionCategoriesQuery $listCategories,
        private readonly ListTransactionPayeesQuery $listPayees,
        private readonly ListTransactionTagsQuery $listTags,
        private readonly ListTransactionsQuery $listTransactions,
    ) {}

    public function __invoke(Ledger $ledger, GetTransactionIndexData $input): TransactionIndexPageData
    {
        $filters = ($this->normalizeFilters)($input);

        return new TransactionIndexPageData(
            filters: $filters,
            accounts: ($this->listAccounts)($ledger),
            categories: ($this->listCategories)($ledger),
            payees: ($this->listPayees)($ledger),
            tags: ($this->listTags)($ledger),
            transactionsFactory: fn () => ($this->listTransactions)($ledger, $filters, $input->page, $input->per_page),
        );
    }
}
