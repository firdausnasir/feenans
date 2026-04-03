<?php

namespace App\Http\Controllers\Ledger;

use App\Actions\Reports\Queries\GetReportPdfPayloadQuery;
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
    ): Response {
        $page = $getSpendingReportPage($input->ledger, $input);

        return Inertia::render('ledgers/reports/index', [
            'dateRange' => $page['dateRange']->toArray(),
        ]);
    }

    public function financialHealth(
        Ledger $ledger,
        GetFinancialHealthPageData $input,
    ): Response {
        return Inertia::render('ledgers/reports/financial-health');
    }

    public function budgetPerformance(
        Ledger $ledger,
        GetBudgetPerformancePageData $input,
        BudgetPerformanceFiltersData $filters,
    ): Response {
        return Inertia::render('ledgers/reports/budget-performance');
    }

    public function cashFlow(
        Ledger $ledger,
        GetCashFlowPageData $input,
    ): Response {
        return Inertia::render('ledgers/reports/cash-flow');
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
