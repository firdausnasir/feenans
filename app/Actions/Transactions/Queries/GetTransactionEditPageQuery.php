<?php

namespace App\Actions\Transactions\Queries;

use App\Data\Transactions\Output\Web\TransactionData;
use App\Data\Transactions\Output\Web\TransactionEditPageData;
use App\Models\Category;
use App\Models\Ledger;
use App\Models\Transaction;
use Illuminate\Support\Collection;

class GetTransactionEditPageQuery
{
    public function __construct(
        private readonly ListTransactionAccountsQuery $listAccounts,
        private readonly ListTransactionCategoriesQuery $listCategories,
        private readonly ListTransactionPayeesQuery $listPayees,
        private readonly ListTransactionTagsQuery $listTags,
    ) {}

    public function __invoke(Ledger $ledger, Transaction $transaction): TransactionEditPageData
    {
        $categories = ($this->listCategories)($ledger);

        $transaction->load([
            'account',
            'category',
            'payee',
            'tags',
            'splits.category',
            'splits.payee',
            'attachments.transaction',
            'transferPair.account',
            'transferPair.category',
            'transferPair.payee',
        ])->loadCount('splits');

        return new TransactionEditPageData(
            transaction: TransactionData::fromModel($transaction),
            accounts: ($this->listAccounts)($ledger),
            categories: $this->flattenCategories($categories),
            payees: ($this->listPayees)($ledger),
            tags: ($this->listTags)($ledger),
        );
    }

    /**
     * @param  Collection<int, Category>  $categories
     * @return array<int, Category>
     */
    private function flattenCategories(Collection $categories): array
    {
        $flatCategories = [];

        $parentCategories = $categories->whereNull('parent_id')->values();

        foreach ($parentCategories as $parent) {
            $flatCategories[] = $parent;

            foreach ($categories->where('parent_id', $parent->id)->values() as $child) {
                $flatCategories[] = $child;
            }
        }

        return $flatCategories;
    }
}
