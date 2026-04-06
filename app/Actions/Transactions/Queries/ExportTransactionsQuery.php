<?php

namespace App\Actions\Transactions\Queries;

use App\Data\Transactions\Input\ExportTransactionsData;
use App\Data\Transactions\Output\TransactionExportRowData;
use App\Data\Transactions\Output\Web\TransactionFiltersData;
use App\Models\Ledger;
use Carbon\CarbonImmutable;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportTransactionsQuery
{
    public function __construct(
        private readonly NormalizeTransactionFiltersQuery $normalizeFilters,
        private readonly ApplyTransactionFiltersQuery $applyFilters,
    ) {}

    public function __invoke(Ledger $ledger, ExportTransactionsData $data): StreamedResponse
    {
        $filters = $this->withDefaultDateRange(
            $ledger,
            ($this->normalizeFilters)($data),
        );

        $query = $ledger->transactions()
            ->with(['account', 'category', 'payee'])
            ->orderByDesc('transaction_date')
            ->orderByDesc('id');

        ($this->applyFilters)($query, $filters);

        $filename = 'transactions-'.$filters->date_from.'-'.$filters->date_to.'.csv';

        return response()->streamDownload(function () use ($query): void {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, TransactionExportRowData::csvHeaders());

            $query->chunk(500, function ($transactions) use ($handle): void {
                foreach ($transactions as $transaction) {
                    $row = TransactionExportRowData::fromTransaction($transaction);
                    fputcsv($handle, $row->toCsvRow());
                }
            });

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    private function withDefaultDateRange(Ledger $ledger, TransactionFiltersData $filters): TransactionFiltersData
    {
        ['start' => $start, 'end' => $end] = $ledger->cycleBounds(CarbonImmutable::now());

        return new TransactionFiltersData(
            search: $filters->search,
            date_from: $filters->date_from !== '' ? $filters->date_from : $start->toDateString(),
            date_to: $filters->date_to !== '' ? $filters->date_to : $end->toDateString(),
            account_ids: $filters->account_ids,
            category_ids: $filters->category_ids,
            transaction_types: $filters->transaction_types,
            payee_ids: $filters->payee_ids,
            tag_ids: $filters->tag_ids,
            bill_id: $filters->bill_id,
            uncategorized: $filters->uncategorized,
        );
    }
}
