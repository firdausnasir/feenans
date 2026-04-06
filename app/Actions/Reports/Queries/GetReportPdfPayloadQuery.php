<?php

namespace App\Actions\Reports\Queries;

use App\Data\Reports\Input\ExportReportPdfData;
use App\Models\Ledger;
use App\Services\ReportService;
use Carbon\CarbonImmutable;

class GetReportPdfPayloadQuery
{
    public function __construct(
        private readonly ReportService $reportService,
    ) {}

    /**
     * @return array{filename: string, view_data: array<string, mixed>}
     */
    public function __invoke(Ledger $ledger, ExportReportPdfData $input): array
    {
        if (filled($input->date_from) && filled($input->date_to)) {
            $dateFrom = CarbonImmutable::parse($input->date_from);
            $dateTo = CarbonImmutable::parse($input->date_to);
        } else {
            $month = filled($input->month)
                ? $input->month
                : CarbonImmutable::today()->format('Y-m');

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
            ->whereIn('transaction_type', ['income', 'expense'])
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

        return [
            'filename' => 'report-'.$ledger->name.'-'.$dateFromStr.'-to-'.$dateToStr.'.pdf',
            'view_data' => [
                'ledgerName' => $ledger->name,
                'monthLabel' => $dateFrom->format('d M Y').' – '.$dateTo->format('d M Y'),
                'incomeTotal' => round($incomeTotal, 2),
                'expenseTotal' => round($expenseTotal, 2),
                'netTotal' => $netTotal,
                'transactionCount' => $transactionCount,
                'categoryBreakdown' => $categoryBreakdown,
                'generatedAt' => CarbonImmutable::now()->format('d M Y, H:i'),
            ],
        ];
    }
}
