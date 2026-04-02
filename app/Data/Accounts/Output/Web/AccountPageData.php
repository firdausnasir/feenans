<?php

namespace App\Data\Accounts\Output\Web;

use Closure;
use Illuminate\Support\Collection;

class AccountPageData
{
    /**
     * @param  Collection<int, AccountGroupData>  $groups
     * @param  Collection<int, mixed>  $accountTypes
     */
    public function __construct(
        public readonly Collection $groups,
        public readonly Collection $accountTypes,
        private readonly Closure $netWorthFactory,
    ) {}

    public function netWorth(): AccountNetWorthData
    {
        return ($this->netWorthFactory)();
    }
}
