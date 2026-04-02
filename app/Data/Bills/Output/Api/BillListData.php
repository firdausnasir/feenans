<?php

namespace App\Data\Bills\Output\Api;

use App\Data\Shared\Output\BaseOutputData;
use Illuminate\Support\Collection;

class BillListData extends BaseOutputData
{
    /**
     * @param  Collection<int, BillData>  $data
     */
    public function __construct(public Collection $data) {}

    /**
     * @return array{data: array<int, array<string, mixed>>}
     */
    public function toArray(): array
    {
        return [
            'data' => $this->data->map(fn (BillData $bill) => $bill->toArray())->values()->all(),
        ];
    }
}
