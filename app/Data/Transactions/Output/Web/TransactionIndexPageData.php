<?php

namespace App\Data\Transactions\Output\Web;

use Closure;
use Illuminate\Support\Collection;

class TransactionIndexPageData
{
    /**
     * @param  array<int, array<string, mixed>>  $accounts
     * @param  Collection<int, mixed>  $categories
     * @param  Collection<int, mixed>  $payees
     * @param  Collection<int, mixed>  $tags
     */
    public function __construct(
        public readonly TransactionFiltersData $filters,
        public readonly array $accounts,
        public readonly Collection $categories,
        public readonly Collection $payees,
        public readonly Collection $tags,
        private readonly Closure $transactionsFactory,
    ) {}

    public function transactions(): mixed
    {
        return ($this->transactionsFactory)();
    }
}
