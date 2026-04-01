<?php

namespace App\Data\Payees\Output;

use App\Data\Shared\Output\BaseOutputData;
use App\Models\Payee;

class PayeeData extends BaseOutputData
{
    public function __construct(
        public int $id,
        public int $ledger_id,
        public string $name,
        public ?bool $is_sample,
        public ?int $transactions_count,
        public ?string $created_at,
        public ?string $updated_at,
    ) {}

    public static function fromModel(Payee $payee): self
    {
        return new self(
            id: $payee->id,
            ledger_id: $payee->ledger_id,
            name: $payee->name,
            is_sample: $payee->is_sample,
            transactions_count: $payee->transactions_count,
            created_at: $payee->created_at?->toIso8601String(),
            updated_at: $payee->updated_at?->toIso8601String(),
        );
    }
}
