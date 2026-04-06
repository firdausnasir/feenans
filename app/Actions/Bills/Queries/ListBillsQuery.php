<?php

namespace App\Actions\Bills\Queries;

use App\Data\Bills\Output\Web\BillData;
use App\Models\Bill;
use App\Models\Ledger;
use Illuminate\Support\Collection;

class ListBillsQuery
{
    public function __construct(
        private readonly GetBillMissedCyclesQuery $getBillMissedCycles,
    ) {}

    /**
     * @return Collection<int, BillData>
     */
    public function __invoke(Ledger $ledger): Collection
    {
        return $ledger->bills()
            ->with([
                'account',
                'toAccount',
                'category',
                'payee',
                'transactions' => fn ($query) => $query
                    ->with('account')
                    ->latest('transaction_date')
                    ->orderByDesc('id')
                    ->limit(5),
            ])
            ->oldest('next_due_date')
            ->get()
            ->map(fn (Bill $bill) => BillData::fromModel($bill, ($this->getBillMissedCycles)($bill)));
    }
}
