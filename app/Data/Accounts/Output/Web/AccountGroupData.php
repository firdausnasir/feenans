<?php

namespace App\Data\Accounts\Output\Web;

use App\Data\Shared\Output\BaseOutputData;
use Illuminate\Support\Collection;

class AccountGroupData extends BaseOutputData
{
    /**
     * @param  Collection<int, AccountData>  $accounts
     */
    public function __construct(
        public string $group,
        public string $label,
        public Collection $accounts,
        public string $total_balance,
    ) {}

    /**
     * @return array{group: string, label: string, accounts: array<int, array<string, mixed>>, total_balance: string}
     */
    public function toArray(): array
    {
        return [
            'group' => $this->group,
            'label' => $this->label,
            'accounts' => $this->accounts->map(fn (AccountData $a) => $a->toArray())->values()->all(),
            'total_balance' => $this->total_balance,
        ];
    }
}
