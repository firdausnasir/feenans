<?php

namespace App\Actions\Transactions\Queries;

use App\Models\Ledger;
use App\Models\Transaction;
use Illuminate\Pagination\Paginator;

class LoadTransferPairRelationsQuery
{
    /**
     * Backfill the transferPair relation on visible transfer transactions.
     * When only one side of a transfer is on the current paginator page,
     * this query fetches the counterpart from the same ledger.
     *
     * @param  Paginator<Transaction>  $transactions
     */
    public function __invoke(Ledger $ledger, Paginator $transactions): void
    {
        $visibleTransfers = $transactions->getCollection()
            ->filter(fn (Transaction $transaction) => $transaction->transfer_pair_id !== null)
            ->values();

        if ($visibleTransfers->isEmpty()) {
            return;
        }

        $visibleTransferIds = $visibleTransfers->pluck('id')->all();
        $visibleTransfersByPairId = $visibleTransfers->groupBy('transfer_pair_id');
        $pairIds = $visibleTransfers
            ->pluck('transfer_pair_id')
            ->filter()
            ->unique()
            ->values()
            ->all();

        $counterpartsByPairId = Transaction::query()
            ->where('ledger_id', $ledger->id)
            ->whereIn('transfer_pair_id', $pairIds)
            ->whereNotIn('id', $visibleTransferIds)
            ->with('account')
            ->get()
            ->groupBy('transfer_pair_id');

        foreach ($visibleTransfers as $transaction) {
            $counterpart = $visibleTransfersByPairId
                ->get($transaction->transfer_pair_id)?->firstWhere('id', '!=', $transaction->id);

            if (! $counterpart instanceof Transaction) {
                $counterpart = $counterpartsByPairId
                    ->get($transaction->transfer_pair_id)?->first();
            }

            if ($counterpart instanceof Transaction) {
                $transaction->setRelation('transferPair', $counterpart);
            }
        }
    }
}
