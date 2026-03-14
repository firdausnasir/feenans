<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AccountResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'ledger_id' => $this->ledger_id,
            'account_type_id' => $this->account_type_id,
            'name' => $this->name,
            'initial_balance' => $this->initial_balance,
            'current_balance' => $this->current_balance,
            'statement_day' => $this->statement_day,
            'include_in_totals' => $this->include_in_totals,
            'account_type' => $this->whenLoaded('accountType', fn () => [
                'id' => $this->accountType->id,
                'name' => $this->accountType->name,
                'color' => $this->accountType->color,
                'is_credit' => $this->accountType->is_credit,
            ]),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
