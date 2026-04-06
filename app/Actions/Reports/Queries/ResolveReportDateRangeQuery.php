<?php

namespace App\Actions\Reports\Queries;

use App\Data\Reports\Input\ReportFiltersData;
use App\Data\Reports\Output\Web\ReportDateRangeData;
use App\Models\Ledger;
use Carbon\CarbonImmutable;

class ResolveReportDateRangeQuery
{
    public function __invoke(
        Ledger $ledger,
        ReportFiltersData $input,
        ?CarbonImmutable $today = null,
    ): ReportDateRangeData {
        $today ??= CarbonImmutable::today();

        $currentCycle = $ledger->cycleBounds($today);
        $dateFrom = filled($input->date_from)
            ? $input->date_from
            : $currentCycle['start']->toDateString();
        $dateTo = filled($input->date_to)
            ? $input->date_to
            : $currentCycle['end']->toDateString();
        $accountId = filled($input->account_id) ? $input->account_id : null;

        return new ReportDateRangeData(
            date_from: $dateFrom,
            date_to: $dateTo,
            preset: $this->detectPreset($ledger, $dateFrom, $dateTo, $today),
            account_id: $accountId,
        );
    }

    private function detectPreset(
        Ledger $ledger,
        string $dateFrom,
        string $dateTo,
        CarbonImmutable $today,
    ): string {
        $currentCycle = $ledger->cycleBounds($today);

        if ($dateFrom === $currentCycle['start']->toDateString() && $dateTo === $currentCycle['end']->toDateString()) {
            return 'this_month';
        }

        $lastMonthCycle = $ledger->cycleBounds($currentCycle['start']->subDay());
        if ($dateFrom === $lastMonthCycle['start']->toDateString() && $dateTo === $lastMonthCycle['end']->toDateString()) {
            return 'last_month';
        }

        $threeMonthsBack = $currentCycle['start'];
        for ($i = 0; $i < 3; $i++) {
            $threeMonthsBack = $ledger->cycleBounds($threeMonthsBack->subDay())['start'];
        }
        if ($dateFrom === $threeMonthsBack->toDateString() && $dateTo === $currentCycle['end']->toDateString()) {
            return 'last_3_months';
        }

        $sixMonthsBack = $currentCycle['start'];
        for ($i = 0; $i < 6; $i++) {
            $sixMonthsBack = $ledger->cycleBounds($sixMonthsBack->subDay())['start'];
        }
        if ($dateFrom === $sixMonthsBack->toDateString() && $dateTo === $currentCycle['end']->toDateString()) {
            return 'last_6_months';
        }

        $janFirst = CarbonImmutable::create($today->year, 1, 1);
        $janCycle = $ledger->cycleBounds($janFirst);
        if ($dateFrom === $janCycle['start']->toDateString() && $dateTo === $currentCycle['end']->toDateString()) {
            return 'this_year';
        }

        return 'custom';
    }
}
