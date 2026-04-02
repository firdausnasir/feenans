<?php

namespace App\Actions\Bills\Queries;

use App\Data\Bills\Output\Web\BillData;
use App\Models\Bill;
use App\Models\Ledger;
use Carbon\CarbonImmutable;

class ListUpcomingBillsQuery
{
    public function __construct(
        private readonly GetBillMissedCyclesQuery $getBillMissedCycles,
    ) {}

    /**
     * @return array{upcoming: array<int, array<string, mixed>>, due: array<int, array<string, mixed>>, missed: array<int, array<string, mixed>>}
     */
    public function __invoke(Ledger $ledger, int $days = 3): array
    {
        $today = CarbonImmutable::today();

        $upcoming = $ledger->bills()
            ->with('payee')
            ->active()
            ->where('next_due_date', '>', $today)
            ->where('next_due_date', '<=', $today->addDays($days))
            ->orderBy('next_due_date')
            ->get();

        $due = $ledger->bills()
            ->with('payee')
            ->active()
            ->due()
            ->orderBy('next_due_date')
            ->get();

        $missed = $ledger->bills()
            ->with('payee')
            ->active()
            ->missed()
            ->orderBy('next_due_date')
            ->get();

        return [
            'upcoming' => $upcoming->map(fn (Bill $bill) => BillData::fromModel($bill, ($this->getBillMissedCycles)($bill))->toArray())->values()->all(),
            'due' => $due->map(fn (Bill $bill) => BillData::fromModel($bill, ($this->getBillMissedCycles)($bill))->toArray())->values()->all(),
            'missed' => $missed->map(fn (Bill $bill) => BillData::fromModel($bill, ($this->getBillMissedCycles)($bill))->toArray())->values()->all(),
        ];
    }
}
