<?php

namespace App\Data\Transactions\Output\Web;

use Illuminate\Support\Collection;

class TransactionEditPageData
{
    /**
     * @param  array<int, array<string, mixed>>  $accounts
     * @param  array<int, mixed>  $categories
     * @param  Collection<int, mixed>  $payees
     * @param  Collection<int, mixed>  $tags
     */
    public function __construct(
        public readonly TransactionData $transaction,
        public readonly array $accounts,
        public readonly array $categories,
        public readonly Collection $payees,
        public readonly Collection $tags,
    ) {}
}
