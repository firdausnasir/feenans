<?php

namespace App\Services;

use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\Bill;
use App\Models\Ledger;
use App\Models\Transaction;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class BillService
{
    private const AUTO_PROCESS_LOCK = 'bills:process-auto';

    private const AUTO_PROCESS_LOCK_SECONDS = 300;

    public function __construct(private readonly TransactionService $transactionService) {}

    public function store(Ledger $ledger, array $data): Bill
    {
        return $ledger->bills()->create([
            'account_id' => $data['account_id'],
            'to_account_id' => $data['to_account_id'] ?? null,
            'category_id' => $data['category_id'] ?? null,
            'payee_id' => $data['payee_id'] ?? null,
            'name' => $data['name'],
            'transaction_type' => $data['transaction_type'] ?? TransactionType::Expense->value,
            'amount' => $data['amount'],
            'recurrence_type' => $data['recurrence_type'],
            'recurrence_interval' => $data['recurrence_interval'] ?? 1,
            'recurrence_day' => $data['recurrence_day'] ?? null,
            'next_due_date' => $data['next_due_date'],
            'auto_create' => $data['auto_create'],
            'end_type' => $data['end_type'] ?? null,
            'end_date' => $data['end_date'] ?? null,
            'end_after_occurrences' => $data['end_after_occurrences'] ?? null,
        ]);
    }

    public function update(Bill $bill, array $data): Bill
    {
        $bill->update([
            'account_id' => $data['account_id'] ?? $bill->account_id,
            'to_account_id' => array_key_exists('to_account_id', $data) ? $data['to_account_id'] : $bill->to_account_id,
            'category_id' => array_key_exists('category_id', $data) ? $data['category_id'] : $bill->category_id,
            'payee_id' => array_key_exists('payee_id', $data) ? $data['payee_id'] : $bill->payee_id,
            'name' => $data['name'] ?? $bill->name,
            'transaction_type' => $data['transaction_type'] ?? $bill->transaction_type,
            'amount' => $data['amount'] ?? $bill->amount,
            'recurrence_type' => $data['recurrence_type'] ?? $bill->recurrence_type,
            'recurrence_interval' => $data['recurrence_interval'] ?? $bill->recurrence_interval,
            'recurrence_day' => array_key_exists('recurrence_day', $data) ? $data['recurrence_day'] : $bill->recurrence_day,
            'next_due_date' => $data['next_due_date'] ?? $bill->next_due_date,
            'auto_create' => $data['auto_create'] ?? $bill->auto_create,
            'end_type' => array_key_exists('end_type', $data) ? $data['end_type'] : $bill->end_type,
            'end_date' => array_key_exists('end_date', $data) ? $data['end_date'] : $bill->end_date,
            'end_after_occurrences' => array_key_exists('end_after_occurrences', $data) ? $data['end_after_occurrences'] : $bill->end_after_occurrences,
        ]);

        return $bill->fresh();
    }

    public function payBill(Bill $bill, ?array $overrides = null): Transaction
    {
        return DB::transaction(function () use ($bill, $overrides): Transaction {
            $type = $bill->transaction_type;

            if ($type === TransactionType::Transfer) {
                $toAccountId = $overrides['to_account_id'] ?? $bill->to_account_id;

                /** @var Account $fromAccount */
                $fromAccount = Account::query()->findOrFail($overrides['account_id'] ?? $bill->account_id);

                /** @var Account $toAccount */
                $toAccount = Account::query()->findOrFail($toAccountId);

                [$outgoing] = $this->transactionService->storeTransfer($bill->ledger, [
                    'from_account' => $fromAccount,
                    'to_account' => $toAccount,
                    'amount' => $overrides['amount'] ?? $bill->amount,
                    'description' => $overrides['description'] ?? $bill->name,
                    'notes' => null,
                    'transaction_date' => $overrides['date'] ?? CarbonImmutable::today(),
                    'bill_id' => $bill->id,
                ]);

                $this->advanceToNextDue($bill);

                $bill->increment('occurrences_count');
                $bill->refresh();

                if ($bill->hasReachedEnd()) {
                    $bill->update(['is_active' => false]);
                }

                return $outgoing;
            }

            $rawAmount = abs((float) ($overrides['amount'] ?? $bill->amount));
            $amount = $type === TransactionType::Income ? $rawAmount : -$rawAmount;

            $transaction = $bill->ledger->transactions()->create([
                'account_id' => $overrides['account_id'] ?? $bill->account_id,
                'category_id' => $overrides['category_id'] ?? $bill->category_id,
                'payee_id' => $overrides['payee_id'] ?? $bill->payee_id,
                'transaction_type' => $type,
                'amount' => $amount,
                'description' => $overrides['description'] ?? $bill->name,
                'notes' => null,
                'transaction_date' => $overrides['date'] ?? CarbonImmutable::today(),
                'transfer_pair_id' => null,
                'bill_id' => $bill->id,
            ]);

            $this->advanceToNextDue($bill);

            $bill->increment('occurrences_count');
            $bill->refresh();

            if ($bill->hasReachedEnd()) {
                $bill->update(['is_active' => false]);
            }

            return $transaction;
        });
    }

    public function advanceToNextDue(Bill $bill): void
    {
        $next = $bill->nextDueDateAfter($bill->next_due_date);
        $bill->update(['next_due_date' => $next]);
    }

    public function computeMissedCycles(Bill $bill): int
    {
        $today = CarbonImmutable::today();

        if ($bill->next_due_date->gte($today)) {
            return 0;
        }

        $current = $bill->next_due_date;
        $count = 0;

        while ($current->lt($today)) {
            $current = $bill->nextDueDateAfter($current);
            $count++;
        }

        return $count;
    }

    public function processAutoBills(): void
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
        // payBill() uses its own DB::transaction; Laravel handles nested transactions
        // via savepoints, so nesting here is safe and intentional.
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

            $missed = $this->computeMissedCycles($bill);
            $cycles = max(1, $missed);

            $dueDate = CarbonImmutable::parse($bill->next_due_date->toDateString());

            for ($i = 0; $i < $cycles; $i++) {
                $this->payBill($bill, ['date' => $dueDate]);
                $bill->refresh();
                $dueDate = $bill->next_due_date;

                if ($bill->hasReachedEnd()) {
                    break;
                }
            }
        });
    }

    /**
     * @return array{upcoming: Collection, due: Collection, missed: Collection}
     */
    public function getUpcomingBills(Ledger $ledger, int $days = 30): array
    {
        $today = CarbonImmutable::today();

        $upcoming = $ledger->bills()
            ->with(['payee'])
            ->active()
            ->where('next_due_date', '>', $today)
            ->where('next_due_date', '<=', $today->addDays($days))
            ->orderBy('next_due_date')
            ->get();

        $due = $ledger->bills()
            ->with(['payee'])
            ->active()
            ->due()
            ->orderBy('next_due_date')
            ->get();

        $missed = $ledger->bills()
            ->with(['payee'])
            ->active()
            ->missed()
            ->orderBy('next_due_date')
            ->get();

        return compact('upcoming', 'due', 'missed');
    }
}
