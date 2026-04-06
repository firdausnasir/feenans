<?php

namespace App\Actions\Bills\UseCases;

use App\Actions\Bills\Queries\GetBillMissedCyclesQuery;
use App\Data\Bills\Input\PayBillData;
use App\Models\Bill;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ProcessAutoBillsAction
{
    private const AUTO_PROCESS_LOCK = 'bills:process-auto';

    private const AUTO_PROCESS_LOCK_SECONDS = 300;

    public function __construct(
        private readonly GetBillMissedCyclesQuery $getBillMissedCycles,
        private readonly PayBillAction $payBill,
    ) {}

    public function __invoke(): void
    {
        $today = CarbonImmutable::today();

        Cache::lock(self::AUTO_PROCESS_LOCK, self::AUTO_PROCESS_LOCK_SECONDS)->get(function () use ($today): void {
            $billIds = Bill::query()
                ->active()
                ->where('auto_create', true)
                ->where('next_due_date', '<=', $today)
                ->pluck('id');

            foreach ($billIds as $billId) {
                $this->processLockedAutoBill((int) $billId, $today);
            }
        });
    }

    private function processLockedAutoBill(int $billId, CarbonImmutable $today): void
    {
        DB::transaction(function () use ($billId, $today): void {
            /** @var Bill|null $bill */
            $bill = Bill::query()
                ->with('account', 'ledger')
                ->lockForUpdate()
                ->find($billId);

            if (! $bill instanceof Bill
                || ! $bill->is_active
                || ! $bill->auto_create
                || $bill->next_due_date->gt($today)) {
                return;
            }

            $missed = ($this->getBillMissedCycles)($bill);
            $cycles = max(1, $missed);
            $dueDate = CarbonImmutable::parse($bill->next_due_date->toDateString());

            for ($i = 0; $i < $cycles; $i++) {
                ($this->payBill)(new PayBillData(
                    ledger: $bill->ledger,
                    bill: $bill,
                    date: $dueDate->toDateString(),
                ));

                $dueDate = CarbonImmutable::parse($bill->next_due_date->toDateString());

                if ($bill->hasReachedEnd()) {
                    break;
                }
            }
        });
    }
}
