<?php

namespace App\Data\Bills\Output\Web;

use App\Data\Categories\Output\CategoryData;
use App\Data\Payees\Output\PayeeData;
use Closure;
use Illuminate\Support\Collection;

class BillPageData
{
    /**
     * @param  Collection<int, BillAccountOptionData>  $accounts
     * @param  Collection<int, CategoryData>  $categories
     * @param  Collection<int, PayeeData>  $payees
     * @param  Closure(): array<int, array<string, mixed>>|null  $billsFactory
     */
    public function __construct(
        public readonly Collection $accounts,
        public readonly Collection $categories,
        public readonly Collection $payees,
        private readonly ?Closure $billsFactory = null,
        public readonly ?BillData $bill = null,
    ) {}

    /**
     * @return array<int, array<string, mixed>>
     */
    public function bills(): array
    {
        return $this->billsFactory !== null
            ? ($this->billsFactory)()
            : [];
    }
}
