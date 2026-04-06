<?php

namespace App\Actions\Accounts\Queries;

use App\Data\Accounts\Output\Web\AccountData;
use App\Models\Account;
use App\Models\Ledger;

class ListAccountsByTypeQuery
{
    /**
     * @return array<int, array{type: array<string, mixed>, accounts: array<int, array<string, mixed>>, total_balance: string}>
     */
    public function __invoke(Ledger $ledger): array
    {
        $accounts = $ledger->accounts()
            ->with('accountType')
            ->withCurrentBalance()
            ->visible()
            ->orderBy('position')
            ->orderBy('name')
            ->get();

        $accountTypes = $ledger->accountTypes()->orderBy('position')->get();

        return $accountTypes
            ->map(function ($type) use ($accounts): ?array {
                $typeAccounts = $accounts->where('account_type_id', $type->id)->values();

                if ($typeAccounts->isEmpty()) {
                    return null;
                }

                return [
                    'type' => [
                        'id' => $type->id,
                        'name' => $type->name,
                        'color' => $type->color,
                        'is_credit' => $type->is_credit,
                    ],
                    'accounts' => $typeAccounts
                        ->map(fn (Account $account) => AccountData::fromModel($account)->toArray())
                        ->values()
                        ->all(),
                    'total_balance' => number_format(
                        $typeAccounts->sum(fn (Account $account) => $account->currentBalanceAmount()),
                        2,
                        '.',
                        '',
                    ),
                ];
            })
            ->filter()
            ->values()
            ->all();
    }
}
