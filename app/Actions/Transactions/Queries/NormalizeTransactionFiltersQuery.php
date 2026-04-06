<?php

namespace App\Actions\Transactions\Queries;

use App\Data\Transactions\Input\GetTransactionIndexData;
use App\Data\Transactions\Output\Web\TransactionFiltersData;
use Illuminate\Support\Arr;

class NormalizeTransactionFiltersQuery
{
    /**
     * @param  GetTransactionIndexData|array<string, mixed>  $input
     */
    public function __invoke(GetTransactionIndexData|array $input): TransactionFiltersData
    {
        $search = $this->resolveValue($input, 'search');
        $billId = $this->resolveValue($input, 'bill_id');
        $uncategorized = $this->resolveValue($input, 'uncategorized');

        return new TransactionFiltersData(
            search: $search === null ? null : (string) $search,
            date_from: (string) ($this->resolveValue($input, 'date_from') ?? ''),
            date_to: (string) ($this->resolveValue($input, 'date_to') ?? ''),
            account_ids: $this->resolveArrayFilterFromValue($this->resolveValue($input, 'account_ids')) ?? [],
            category_ids: $this->resolveArrayFilterFromValue($this->resolveValue($input, 'category_ids')) ?? [],
            transaction_types: $this->resolveArrayFilterFromValue($this->resolveValue($input, 'transaction_types')) ?? [],
            payee_ids: $this->resolveArrayFilterFromValue($this->resolveValue($input, 'payee_ids')) ?? [],
            tag_ids: $this->resolveArrayFilterFromValue($this->resolveValue($input, 'tag_ids')) ?? [],
            bill_id: $billId === null ? null : (string) $billId,
            uncategorized: $uncategorized === null ? null : (string) $uncategorized,
        );
    }

    /**
     * @param  GetTransactionIndexData|array<string, mixed>  $input
     */
    private function resolveValue(GetTransactionIndexData|array $input, string $key): mixed
    {
        if (is_array($input)) {
            return $input[$key] ?? null;
        }

        return $input->{$key};
    }

    /**
     * Resolve an array filter from a raw request value.
     * Supports both `key[]` (standard array) and `key` (comma-separated or single value) formats.
     *
     * @return string[]|null
     */
    private function resolveArrayFilterFromValue(mixed $value): ?array
    {
        if (is_string($value) && str_contains($value, ',')) {
            $value = explode(',', $value);
        }

        if ($value === null || $value === '' || $value === []) {
            return null;
        }

        if (is_array($value)) {
            $filtered = array_filter(
                Arr::flatten($value),
                fn ($v) => $v !== null && $v !== '',
            );

            return $filtered !== []
                ? array_map(static fn ($item) => (string) $item, array_values($filtered))
                : null;
        }

        return [(string) $value];
    }
}
