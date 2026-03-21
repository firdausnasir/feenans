<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BillResource extends JsonResource
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
            'account_id' => $this->account_id,
            'to_account_id' => $this->to_account_id,
            'category_id' => $this->category_id,
            'payee_id' => $this->payee_id,
            'name' => $this->name,
            'transaction_type' => $this->transaction_type,
            'amount' => $this->amount,
            'recurrence_type' => $this->recurrence_type,
            'recurrence_interval' => $this->recurrence_interval,
            'recurrence_day' => $this->recurrence_day,
            'next_due_date' => $this->next_due_date?->toDateString(),
            'auto_create' => $this->auto_create,
            'end_type' => $this->end_type,
            'end_after_occurrences' => $this->end_after_occurrences,
            'end_date' => $this->end_date?->toDateString(),
            'occurrences_count' => $this->occurrences_count,
            'is_active' => $this->is_active,
            'account' => new AccountResource($this->whenLoaded('account')),
            'to_account' => new AccountResource($this->whenLoaded('toAccount')),
            'category' => new CategoryResource($this->whenLoaded('category')),
            'payee' => new PayeeResource($this->whenLoaded('payee')),
            'transactions' => TransactionResource::collection($this->whenLoaded('transactions')),
            'missed_cycles' => $this->when($this->missed_cycles !== null, $this->missed_cycles),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
