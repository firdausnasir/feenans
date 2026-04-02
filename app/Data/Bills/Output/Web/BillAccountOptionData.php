<?php

namespace App\Data\Bills\Output\Web;

use App\Data\Shared\Output\BaseOutputData;
use App\Models\Account;

class BillAccountOptionData extends BaseOutputData
{
    public function __construct(
        public int $id,
        public int $ledger_id,
        public string $name,
        public ?string $color,
    ) {}

    public static function fromModel(Account $account): self
    {
        return new self(
            id: $account->id,
            ledger_id: $account->ledger_id,
            name: $account->name,
            color: $account->color,
        );
    }
}
