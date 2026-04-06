<?php

namespace App\Data\Reports\Output\Web;

use App\Data\Shared\Output\BaseOutputData;

class FinancialHealthReportData extends BaseOutputData
{
    /**
     * @param  list<array{
     *     month: string,
     *     assets: float,
     *     liabilities: float,
     *     net_worth: float
     * }>  $net_worth_history
     * @param  list<array{
     *     month: string,
     *     income: float,
     *     expense: float,
     *     savings: float,
     *     rate: float
     * }>  $savings_rate_history
     * @param  array{
     *     assets: float,
     *     liabilities: float,
     *     net_worth: float,
     *     debt_to_asset_ratio: float
     * }  $current_snapshot
     */
    public function __construct(
        public readonly array $net_worth_history,
        public readonly array $savings_rate_history,
        public readonly array $current_snapshot,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'net_worth_history' => $this->net_worth_history,
            'savings_rate_history' => $this->savings_rate_history,
            'current_snapshot' => $this->current_snapshot,
        ];
    }
}
