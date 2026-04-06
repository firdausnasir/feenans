<?php

namespace App\Actions\Transactions\Queries;

use App\Models\Account;
use App\Models\Ledger;

class ListTransactionAccountsQuery
{
    /**
     * @return array<int, array{id: int, ledger_id: int, name: string, current_balance: string, color: ?string, include_in_totals: bool}>
     */
    public function __invoke(Ledger $ledger): array
    {
        return $ledger->accounts()
            ->visible()
            ->select(['id', 'ledger_id', 'name', 'initial_balance', 'color', 'include_in_totals'])
            ->withCurrentBalance()
            ->orderBy('position')
            ->orderBy('name')
            ->get()
            ->map(fn (Account $account) => [
                'id' => $account->id,
                'ledger_id' => $account->ledger_id,
                'name' => $account->name,
                'current_balance' => $account->current_balance,
                'color' => $account->color,
                'include_in_totals' => (bool) $account->include_in_totals,
            ])
            ->all();
    }
}
