<?php

namespace App\Http\Controllers\Ledger;

use App\Enums\TransactionType;
use App\Http\Controllers\Controller;
use App\Models\Ledger;
use App\Services\ReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class ReportController extends Controller
{
    public function __construct(private readonly ReportService $reportService) {}

    public function index(Request $request, Ledger $ledger): Response
    {
        $this->authorize('view', $ledger);

        $filters = [
            'date_from' => $request->input('date_from'),
            'date_to' => $request->input('date_to'),
            'account_id' => $request->input('account_id'),
        ];

        // Compute date range cheaply for the filter panel
        $today = CarbonImmutable::today();
        $currentCycle = $ledger->cycleBounds($today);
        $dateFrom = $filters['date_from'] ?? $currentCycle['start']->toDateString();
        $dateTo = $filters['date_to'] ?? $currentCycle['end']->toDateString();
        $accountId = $filters['account_id'] ?? null;

        $preset = $this->reportService->detectPreset($ledger, $dateFrom, $dateTo, $today);

        $dateRange = [
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'preset' => $preset,
            'account_id' => $accountId,
        ];

        $compareStart = $request->input('compare_start');
        $compareEnd = $request->input('compare_end');

        return Inertia::render('ledgers/reports/index', [
            'dateRange' => $dateRange,
            'allAccounts' => fn () => $ledger->accounts()->orderBy('name')->get(['id', 'name']),
            'report' => Inertia::defer(function () use ($ledger, $filters, $dateRange, $compareStart, $compareEnd) {
                $report = $this->reportService->getSpendingReport($ledger, $filters);

                if ($compareStart && $compareEnd) {
                    $report['comparison'] = $this->reportService->buildComparison(
                        $ledger,
                        $dateRange['date_from'],
                        $dateRange['date_to'],
                        $compareStart,
                        $compareEnd,
                        $dateRange['account_id'],
                    );
                }

                return $report;
            }),
        ]);
    }

    public function financialHealth(Request $request, Ledger $ledger): Response
    {
        $this->authorize('view', $ledger);

        return Inertia::render('ledgers/reports/financial-health', [
            'health' => Inertia::defer(fn () => $this->reportService->getFinancialHealthReport($ledger)),
        ]);
    }

    public function budgetPerformance(Request $request, Ledger $ledger): Response
    {
        $this->authorize('view', $ledger);

        $filters = ['period' => $request->input('period')];

        return Inertia::render('ledgers/reports/budget-performance', [
            'performance' => Inertia::defer(fn () => $this->reportService->getBudgetPerformanceReport($ledger, $filters)),
        ]);
    }

    public function cashFlow(Request $request, Ledger $ledger): Response
    {
        $this->authorize('view', $ledger);

        $filters = [
            'date_from' => $request->input('date_from'),
            'date_to' => $request->input('date_to'),
            'account_id' => $request->input('account_id'),
        ];

        return Inertia::render('ledgers/reports/cash-flow', [
            'cashFlow' => Inertia::defer(fn () => $this->reportService->getCashFlowReport($ledger, $filters)),
        ]);
    }

    public function exportPdf(Request $request, Ledger $ledger): HttpResponse
    {
        $this->authorize('view', $ledger);

        if ($request->filled('date_from') && $request->filled('date_to')) {
            $dateFrom = CarbonImmutable::parse($request->query('date_from'));
            $dateTo = CarbonImmutable::parse($request->query('date_to'));
        } else {
            $month = $request->query('month', CarbonImmutable::today()->format('Y-m'));
            $parsedMonth = CarbonImmutable::createFromFormat('Y-m', $month);
            $dateFrom = $parsedMonth->startOfMonth();
            $dateTo = $parsedMonth->endOfMonth();
        }

        $dateFromStr = $dateFrom->toDateString();
        $dateToStr = $dateTo->toDateString();

        $expenseTotals = $this->reportService->periodExpenseTotals($ledger, $dateFromStr, $dateToStr);
        $incomeTotal = $this->reportService->periodIncomeTotals($ledger, $dateFromStr, $dateToStr);
        $expenseTotal = $expenseTotals['total'];
        $netTotal = round($incomeTotal - $expenseTotal, 2);

        $transactionCount = $ledger->transactions()
            ->whereIn('transaction_type', [TransactionType::Income->value, TransactionType::Expense->value])
            ->whereBetween('transaction_date', [$dateFromStr, $dateToStr])
            ->count();

        $categoryBreakdown = [];

        foreach ($expenseTotals['byCategory'] as $name => $total) {
            $percentage = $expenseTotal > 0
                ? round(($total / $expenseTotal) * 100, 1)
                : 0.0;

            $categoryBreakdown[] = [
                'name' => $name,
                'total' => round($total, 2),
                'percentage' => $percentage,
            ];
        }

        usort($categoryBreakdown, fn ($a, $b) => $b['total'] <=> $a['total']);

        $periodLabel = $dateFrom->format('d M Y').' – '.$dateTo->format('d M Y');

        $pdf = Pdf::loadView('reports.monthly-pdf', [
            'ledgerName' => $ledger->name,
            'monthLabel' => $periodLabel,
            'incomeTotal' => round($incomeTotal, 2),
            'expenseTotal' => round($expenseTotal, 2),
            'netTotal' => $netTotal,
            'transactionCount' => $transactionCount,
            'categoryBreakdown' => $categoryBreakdown,
            'generatedAt' => CarbonImmutable::now()->format('d M Y, H:i'),
        ]);

        $filename = 'report-'.$ledger->name.'-'.$dateFromStr.'-to-'.$dateToStr.'.pdf';

        return $pdf->download($filename);
    }
}
