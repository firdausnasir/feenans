<?php

namespace App\Data\Transactions\Output\Api;

use App\Data\Shared\Output\BaseOutputData;
use Illuminate\Support\Collection;

class TransactionListData extends BaseOutputData
{
    /**
     * @param  Collection<int, TransactionData>  $data
     */
    public function __construct(
        public readonly Collection $data,
    ) {}

    /**
     * @return array{data: array<int, array<string, mixed>>}
     */
    public function toArray(): array
    {
        return [
            'data' => $this->data->map(fn (TransactionData $transaction) => $transaction->toArray())->values()->all(),
        ];
    }
}
