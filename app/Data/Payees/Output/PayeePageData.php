<?php

namespace App\Data\Payees\Output;

use App\Data\Shared\Output\BaseOutputData;
use Illuminate\Support\Collection;

class PayeePageData extends BaseOutputData
{
    /**
     * @param  Collection<int, PayeeData>  $payees
     */
    public function __construct(public Collection $payees) {}

    /**
     * @return array{payees: array<int, array<string, mixed>>}
     */
    public function toInertiaProps(): array
    {
        return [
            'payees' => $this->payees->map(fn (PayeeData $payee) => $payee->toArray())->values()->all(),
        ];
    }
}
