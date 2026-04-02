<?php

namespace App\Http\Controllers\Api\V1\Ledger;

use App\Actions\Reports\Queries\GetBudgetPerformanceReportDataQuery;
use App\Actions\Reports\Queries\GetCashFlowReportDataQuery;
use App\Actions\Reports\Queries\GetFinancialHealthReportDataQuery;
use App\Actions\Reports\Queries\GetSpendingReportDataQuery;
use App\Data\Reports\Input\BudgetPerformanceFiltersData;
use App\Data\Reports\Input\GetCashFlowPageData;
use App\Data\Reports\Input\GetFinancialHealthPageData;
use App\Data\Reports\Input\ReportFiltersData;
use App\Data\Reports\Output\Api\BudgetPerformanceReportData as ApiBudgetPerformanceReportData;
use App\Data\Reports\Output\Api\CashFlowReportData as ApiCashFlowReportData;
use App\Data\Reports\Output\Api\FinancialHealthReportData as ApiFinancialHealthReportData;
use App\Data\Reports\Output\Api\SpendingReportData as ApiSpendingReportData;
use App\Http\Controllers\Controller;
use App\Models\Ledger;
use Illuminate\Http\JsonResponse;

class ReportController extends Controller
{
    public function index(
        Ledger $ledger,
        ReportFiltersData $input,
        GetSpendingReportDataQuery $getSpendingReportData,
    ): JsonResponse {
        $this->authorize('view', $ledger);

        $result = ApiSpendingReportData::fromWebResult(
            $getSpendingReportData($input->ledger, $input)
        );

        return response()->json([
            'data' => $result->toArray(),
        ], 200, [], JSON_PRESERVE_ZERO_FRACTION);
    }

    public function financialHealth(
        Ledger $ledger,
        GetFinancialHealthPageData $input,
        GetFinancialHealthReportDataQuery $getFinancialHealthReportData,
    ): JsonResponse {
        $this->authorize('view', $ledger);

        $result = ApiFinancialHealthReportData::fromWebResult(
            $getFinancialHealthReportData($input->ledger, $input)
        );

        return response()->json([
            'data' => $result->toArray(),
        ], 200, [], JSON_PRESERVE_ZERO_FRACTION);
    }

    public function budgetPerformance(
        Ledger $ledger,
        BudgetPerformanceFiltersData $input,
        GetBudgetPerformanceReportDataQuery $getBudgetPerformanceReportData,
    ): JsonResponse {
        $this->authorize('view', $ledger);

        $result = ApiBudgetPerformanceReportData::fromWebResult(
            $getBudgetPerformanceReportData($input->ledger, $input)
        );

        return response()->json([
            'data' => $result->toArray(),
        ], 200, [], JSON_PRESERVE_ZERO_FRACTION);
    }

    public function cashFlow(
        Ledger $ledger,
        GetCashFlowPageData $input,
        GetCashFlowReportDataQuery $getCashFlowReportData,
    ): JsonResponse {
        $this->authorize('view', $ledger);

        $result = ApiCashFlowReportData::fromWebResult(
            $getCashFlowReportData($input->ledger, $input)
        );

        return response()->json([
            'data' => $result->toArray(),
        ], 200, [], JSON_PRESERVE_ZERO_FRACTION);
    }
}
