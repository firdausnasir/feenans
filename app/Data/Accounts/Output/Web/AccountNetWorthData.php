<?php

namespace App\Data\Accounts\Output\Web;

use App\Data\Shared\Output\BaseOutputData;

class AccountNetWorthData extends BaseOutputData
{
    /**
     * @param  array<int, array{month: string, net: float}>  $trend
     */
    public function __construct(
        public float $assets,
        public float $liabilities,
        public float $net,
        public array $trend,
    ) {}
}
