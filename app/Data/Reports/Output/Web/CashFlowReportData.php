<?php

namespace App\Data\Reports\Output\Web;

use App\Data\Shared\Output\BaseOutputData;

class CashFlowReportData extends BaseOutputData
{
    /**
     * @param  list<array{
     *     date: string,
     *     income: float,
     *     expense: float,
     *     net: float,
     *     cumulative: float
     * }>  $daily_cash_flow
     * @param  list<array{
     *     id: int,
     *     name: string,
     *     amount: float,
     *     transaction_type: string,
     *     next_due_date: string,
     *     account_name: string|null
     * }>  $upcoming_bills
     */
    public function __construct(
        public readonly array $daily_cash_flow,
        public readonly array $upcoming_bills,
        public readonly string $period_label,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'daily_cash_flow' => $this->daily_cash_flow,
            'upcoming_bills' => $this->upcoming_bills,
            'period_label' => $this->period_label,
        ];
    }
}
