<?php

namespace App\Data\Bills\Output\Web;

use App\Data\Categories\Output\CategoryData;
use App\Data\Payees\Output\PayeeData;
use App\Data\Shared\Output\BaseOutputData;
use App\Models\Bill;
use App\Models\Transaction;

class BillData extends BaseOutputData
{
    /**
     * @param  array<int, array<string, mixed>>  $transactions
     */
    public function __construct(
        public int $id,
        public int $ledger_id,
        public int $account_id,
        public ?int $to_account_id,
        public ?int $category_id,
        public ?int $payee_id,
        public string $name,
        public string $transaction_type,
        public string $amount,
        public string $recurrence_type,
        public int $recurrence_interval,
        public ?int $recurrence_day,
        public string $next_due_date,
        public bool $auto_create,
        public ?string $end_type,
        public ?int $end_after_occurrences,
        public ?string $end_date,
        public int $occurrences_count,
        public bool $is_active,
        public ?array $account,
        public ?array $to_account,
        public ?array $category,
        public ?array $payee,
        public array $transactions,
        public int $missed_cycles,
        public ?string $created_at,
        public ?string $updated_at,
    ) {}

    public static function fromModel(Bill $bill, int $missedCycles = 0): self
    {
        return new self(
            id: $bill->id,
            ledger_id: $bill->ledger_id,
            account_id: $bill->account_id,
            to_account_id: $bill->to_account_id,
            category_id: $bill->category_id,
            payee_id: $bill->payee_id,
            name: $bill->name,
            transaction_type: $bill->transaction_type?->value ?? (string) $bill->transaction_type,
            amount: (string) $bill->amount,
            recurrence_type: $bill->recurrence_type?->value ?? (string) $bill->recurrence_type,
            recurrence_interval: (int) $bill->recurrence_interval,
            recurrence_day: $bill->recurrence_day,
            next_due_date: $bill->next_due_date?->toDateString() ?? '',
            auto_create: (bool) $bill->auto_create,
            end_type: $bill->end_type,
            end_after_occurrences: $bill->end_after_occurrences,
            end_date: $bill->end_date?->toDateString(),
            occurrences_count: (int) $bill->occurrences_count,
            is_active: (bool) $bill->is_active,
            account: $bill->relationLoaded('account') && $bill->account !== null
                ? BillAccountOptionData::fromModel($bill->account)->toArray()
                : null,
            to_account: $bill->relationLoaded('toAccount') && $bill->toAccount !== null
                ? BillAccountOptionData::fromModel($bill->toAccount)->toArray()
                : null,
            category: $bill->relationLoaded('category') && $bill->category !== null
                ? CategoryData::fromModel($bill->category)->toArray()
                : null,
            payee: $bill->relationLoaded('payee') && $bill->payee !== null
                ? PayeeData::fromModel($bill->payee)->toArray()
                : null,
            transactions: $bill->relationLoaded('transactions')
                ? $bill->transactions
                    ->map(fn (Transaction $transaction) => BillHistoryTransactionData::fromModel($transaction)->toArray())
                    ->values()
                    ->all()
                : [],
            missed_cycles: $missedCycles,
            created_at: $bill->created_at?->toIso8601String(),
            updated_at: $bill->updated_at?->toIso8601String(),
        );
    }
}
