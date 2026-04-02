<?php

namespace App\Data\Budgets\Output;

use App\Data\Shared\Output\BaseOutputData;
use App\Models\Budget;
use Carbon\CarbonImmutable;

class BudgetData extends BaseOutputData
{
    public function __construct(
        public int $id,
        public int $ledger_id,
        public ?int $category_id,
        public float $amount,
        public string $period,
        public ?string $start_date,
        public ?string $end_date,
        public bool $is_active,
        public bool $rollover,
        public string $category_name,
        public ?string $category_color,
        public float $spent,
        public int|float $remaining,
        public int|float $percentage,
        public string $status,
        public string $period_start,
        public string $period_end,
        public ?string $created_at,
        public ?string $updated_at,
    ) {}

    public static function fromModel(
        Budget $budget,
        float $spent,
        CarbonImmutable $periodStart,
        CarbonImmutable $periodEnd,
    ): self {
        $allocated = (float) $budget->amount;
        $remaining = $allocated - $spent;
        $remaining = $remaining > 0 ? round($remaining, 2) : 0;
        $percentage = $allocated > 0 ? min(100, round(($spent / $allocated) * 100, 1)) : 0;

        return new self(
            id: $budget->id,
            ledger_id: $budget->ledger_id,
            category_id: $budget->category_id,
            amount: $allocated,
            period: $budget->period,
            start_date: $budget->start_date?->toDateString(),
            end_date: $budget->end_date?->toDateString(),
            is_active: (bool) $budget->is_active,
            rollover: (bool) $budget->rollover,
            category_name: $budget->category?->name ?? 'Overall',
            category_color: $budget->category?->color,
            spent: $spent,
            remaining: $remaining,
            percentage: $percentage,
            status: $percentage >= 100 ? 'over' : ($percentage >= 90 ? 'danger' : ($percentage >= 75 ? 'warning' : 'good')),
            period_start: $periodStart->toDateString(),
            period_end: $periodEnd->toDateString(),
            created_at: $budget->created_at?->toIso8601String(),
            updated_at: $budget->updated_at?->toIso8601String(),
        );
    }
}
