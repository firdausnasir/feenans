<?php

namespace App\Data\Accounts\Output\Web;

use App\Data\Shared\Output\BaseOutputData;
use App\Models\Account;

class AccountData extends BaseOutputData
{
    public function __construct(
        public int $id,
        public int $ledger_id,
        public int $account_type_id,
        public string $name,
        public string $initial_balance,
        public string $current_balance,
        public ?int $statement_day,
        public ?string $color,
        public bool $is_hidden,
        public ?int $position,
        public ?int $payment_due_day,
        public bool $include_in_totals,
        public ?array $account_type,
        public ?string $created_at,
        public ?string $updated_at,
    ) {}

    public static function fromModel(Account $account): self
    {
        return new self(
            id: $account->id,
            ledger_id: $account->ledger_id,
            account_type_id: $account->account_type_id,
            name: $account->name,
            initial_balance: $account->initial_balance,
            current_balance: $account->current_balance,
            statement_day: $account->statement_day,
            color: $account->color,
            is_hidden: (bool) $account->is_hidden,
            position: $account->position,
            payment_due_day: $account->payment_due_day,
            include_in_totals: (bool) $account->include_in_totals,
            account_type: $account->relationLoaded('accountType') && $account->accountType !== null
                ? [
                    'id' => $account->accountType->id,
                    'name' => $account->accountType->name,
                    'color' => $account->accountType->color,
                    'is_credit' => $account->accountType->is_credit,
                ]
                : null,
            created_at: $account->created_at?->toIso8601String(),
            updated_at: $account->updated_at?->toIso8601String(),
        );
    }
}
