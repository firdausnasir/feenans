<?php

namespace App\Actions\Accounts\Queries;

use App\Data\Accounts\Output\Web\AccountData;
use App\Data\Accounts\Output\Web\AccountGroupData;
use App\Models\Account;
use App\Models\Ledger;
use Illuminate\Support\Collection;

class ListAccountsByTotalsQuery
{
    /**
     * @return Collection<int, AccountGroupData>
     */
    public function __invoke(Ledger $ledger): Collection
    {
        $accounts = $ledger->accounts()
            ->with('accountType')
            ->withCurrentBalance()
            ->visible()
            ->orderBy('position')
            ->orderBy('name')
            ->get();

        $groups = [
            ['key' => 'included', 'label' => 'Included in totals', 'filter' => true],
            ['key' => 'excluded', 'label' => 'Savings', 'filter' => false],
        ];

        return collect($groups)
            ->map(function (array $group) use ($accounts): ?AccountGroupData {
                $filtered = $accounts->where('include_in_totals', $group['filter'])->values();

                if ($filtered->isEmpty()) {
                    return null;
                }

                return new AccountGroupData(
                    group: $group['key'],
                    label: $group['label'],
                    accounts: $filtered->map(fn (Account $account) => AccountData::fromModel($account)),
                    total_balance: number_format(
                        $filtered->sum(fn (Account $account) => $account->currentBalanceAmount()),
                        2,
                        '.',
                        '',
                    ),
                );
            })
            ->filter()
            ->values();
    }
}
