<?php

namespace App\Data\Reports\Output\Web;

use App\Data\Shared\Output\BaseOutputData;

class SpendingReportData extends BaseOutputData
{
    /**
     * @param  array<int, array<string, mixed>>  $monthly_trends
     * @param  array{items: array<int, array<string, mixed>>, parents: array<int, array<string, mixed>>}  $category_breakdown
     * @param  array<int, array<string, mixed>>  $payee_breakdown
     * @param  array{items: array<int, array<string, mixed>>, parents: array<int, array<string, mixed>>}  $income_category_breakdown
     * @param  array<int, array<string, mixed>>  $income_payee_breakdown
     * @param  array<int, array<string, mixed>>  $spending_heatmap
     * @param  array<string, mixed>  $summary
     * @param  array<string, mixed>|null  $comparison
     */
    public function __construct(
        public readonly array $monthly_trends,
        public readonly array $category_breakdown,
        public readonly array $payee_breakdown,
        public readonly array $income_category_breakdown,
        public readonly array $income_payee_breakdown,
        public readonly array $spending_heatmap,
        public readonly array $summary,
        public readonly ReportDateRangeData $date_range,
        public readonly ?array $comparison = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $payload = [
            'monthly_trends' => $this->monthly_trends,
            'category_breakdown' => $this->category_breakdown,
            'payee_breakdown' => $this->payee_breakdown,
            'income_category_breakdown' => $this->income_category_breakdown,
            'income_payee_breakdown' => $this->income_payee_breakdown,
            'spending_heatmap' => $this->spending_heatmap,
            'summary' => $this->summary,
            'date_range' => $this->date_range->toArray(),
        ];

        if ($this->comparison !== null) {
            $payload['comparison'] = $this->comparison;
        }

        return $payload;
    }
}
