<?php

namespace App\Http\Controllers\Ledger;

use App\Actions\Reports\Queries\GetBudgetPerformancePageQuery;
use App\Actions\Reports\Queries\GetBudgetPerformanceReportDataQuery;
use App\Actions\Reports\Queries\GetCashFlowPageQuery;
use App\Actions\Reports\Queries\GetCashFlowReportDataQuery;
use App\Actions\Reports\Queries\GetFinancialHealthPageQuery;
use App\Actions\Reports\Queries\GetFinancialHealthReportDataQuery;
use App\Actions\Reports\Queries\GetReportPdfPayloadQuery;
use App\Actions\Reports\Queries\GetSpendingReportDataQuery;
use App\Actions\Reports\Queries\GetSpendingReportPageQuery;
use App\Data\Reports\Input\BudgetPerformanceFiltersData;
use App\Data\Reports\Input\ExportReportPdfData;
use App\Data\Reports\Input\GetBudgetPerformancePageData;
use App\Data\Reports\Input\GetCashFlowPageData;
use App\Data\Reports\Input\GetFinancialHealthPageData;
use App\Data\Reports\Input\ReportFiltersData;
use App\Http\Controllers\Controller;
use App\Models\Ledger;
use Barryvdh\DomPDF\Facade\Pdf;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class ReportController extends Controller
{
    public function index(
        Ledger $ledger,
        ReportFiltersData $input,
        GetSpendingReportPageQuery $getSpendingReportPage,
        GetSpendingReportDataQuery $getSpendingReportData,
    ): Response {
        $resolvedPage = null;
        $resolvePage = function () use ($input, $getSpendingReportPage, &$resolvedPage): array {
            return $resolvedPage ??= $getSpendingReportPage($input->ledger, $input);
        };

        return Inertia::render('ledgers/reports/index', [
            'dateRange' => fn () => $resolvePage()['dateRange']->toArray(),
            'report' => Inertia::defer(fn () => $getSpendingReportData($input->ledger, $input)->toArray()),
        ]);
    }

    public function financialHealth(
        Ledger $ledger,
        GetFinancialHealthPageData $input,
        GetFinancialHealthPageQuery $getFinancialHealthPage,
        GetFinancialHealthReportDataQuery $getFinancialHealthReportData,
    ): Response {
        $getFinancialHealthPage($input->ledger, $input);

        return Inertia::render('ledgers/reports/financial-health', [
            'health' => Inertia::defer(fn () => $getFinancialHealthReportData($input->ledger, $input)->toArray()),
        ]);
    }

    public function budgetPerformance(
        Ledger $ledger,
        GetBudgetPerformancePageData $input,
        BudgetPerformanceFiltersData $filters,
        GetBudgetPerformancePageQuery $getBudgetPerformancePage,
        GetBudgetPerformanceReportDataQuery $getBudgetPerformanceReportData,
    ): Response {
        $getBudgetPerformancePage($input->ledger, $input);

        return Inertia::render('ledgers/reports/budget-performance', [
            'performance' => Inertia::defer(fn () => $getBudgetPerformanceReportData($filters->ledger, $filters)->toArray()),
        ]);
    }

    public function cashFlow(
        Ledger $ledger,
        GetCashFlowPageData $input,
        GetCashFlowPageQuery $getCashFlowPage,
        GetCashFlowReportDataQuery $getCashFlowReportData,
    ): Response {
        $getCashFlowPage($input->ledger, $input);

        return Inertia::render('ledgers/reports/cash-flow', [
            'cashFlow' => Inertia::defer(fn () => $getCashFlowReportData($input->ledger, $input)->toArray()),
        ]);
    }

    public function exportPdf(
        Ledger $ledger,
        ExportReportPdfData $input,
        GetReportPdfPayloadQuery $getReportPdfPayload,
    ): HttpResponse {
        $payload = $getReportPdfPayload($input->ledger, $input);

        return Pdf::loadView('reports.monthly-pdf', $payload['view_data'])
            ->download($payload['filename']);
    }
}
