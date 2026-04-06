<?php

namespace App\Data\Reports\Output\Web;

use App\Data\Shared\Output\BaseOutputData;
use Spatie\TypeScriptTransformer\Attributes\LiteralTypeScriptType;

class BudgetPerformanceReportData extends BaseOutputData
{
    /**
     * @param  list<array{
     *     id: int,
     *     category_name: string,
     *     amount: float,
     *     spent: float,
     *     remaining: float,
     *     percentage: float,
     *     period: string,
     *     status: string
     * }>  $budget_stats
     */
    public function __construct(
        #[LiteralTypeScriptType("{
            id: number,
            category_name: string,
            amount: number,
            spent: number,
            remaining: number,
            percentage: number,
            period: string,
            status: 'good' | 'warning' | 'danger' | 'over',
        }[]")]
        public readonly array $budget_stats,
        public readonly string $period_label,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'budget_stats' => $this->budget_stats,
            'period_label' => $this->period_label,
        ];
    }
}
