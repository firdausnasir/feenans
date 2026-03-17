<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ReportFilterRequest;
use App\Models\Ledger;
use App\Services\ReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function __construct(private readonly ReportService $reportService) {}

    public function spending(ReportFilterRequest $request, Ledger $ledger): JsonResponse
    {
        $this->authorize('view', $ledger);

        $filters = [
            'date_from' => $request->input('date_from'),
            'date_to' => $request->input('date_to'),
            'account_id' => $request->input('account_id'),
        ];

        $data = $this->reportService->getSpendingReport($ledger, $filters);

        $compareStart = $request->input('compare_start');
        $compareEnd = $request->input('compare_end');

        if ($compareStart && $compareEnd) {
            $data['comparison'] = $this->reportService->buildComparison(
                $ledger,
                $data['date_range']['date_from'],
                $data['date_range']['date_to'],
                $compareStart,
                $compareEnd,
                $data['date_range']['account_id'],
            );
        }

        $accounts = $ledger->accounts()->orderBy('name')->get(['id', 'name']);
        $data['all_accounts'] = $accounts;

        return response()->json(['data' => $data]);
    }

    public function cashFlow(ReportFilterRequest $request, Ledger $ledger): JsonResponse
    {
        $this->authorize('view', $ledger);

        $filters = [
            'date_from' => $request->input('date_from'),
            'date_to' => $request->input('date_to'),
            'account_id' => $request->input('account_id'),
        ];

        $data = $this->reportService->getCashFlowReport($ledger, $filters);

        return response()->json(['data' => $data]);
    }

    public function budgetPerformance(Request $request, Ledger $ledger): JsonResponse
    {
        $this->authorize('view', $ledger);

        $filters = [
            'period' => $request->input('period'),
        ];

        $data = $this->reportService->getBudgetPerformanceReport($ledger, $filters);

        return response()->json(['data' => $data]);
    }

    public function financialHealth(Ledger $ledger): JsonResponse
    {
        $this->authorize('view', $ledger);

        $data = $this->reportService->getFinancialHealthReport($ledger);

        return response()->json(['data' => $data]);
    }
}
