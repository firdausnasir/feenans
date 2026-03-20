<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TransactionResource extends JsonResource
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
            'category_id' => $this->category_id,
            'payee_id' => $this->payee_id,
            'bill_id' => $this->bill_id,
            'transaction_type' => $this->transaction_type,
            'amount' => $this->amount,
            'description' => $this->description,
            'notes' => $this->notes,
            'transaction_date' => $this->transaction_date?->toDateString(),
            'transfer_pair_id' => $this->transfer_pair_id,
            'is_split' => $this->splits_count > 0 || ($this->relationLoaded('splits') && $this->splits->isNotEmpty()),
            'attachments_count' => $this->whenCounted('attachments'),
            'account' => new AccountResource($this->whenLoaded('account')),
            'category' => new CategoryResource($this->whenLoaded('category')),
            'payee' => new PayeeResource($this->whenLoaded('payee')),
            'tags' => TagResource::collection($this->whenLoaded('tags')),
            'splits' => TransactionSplitResource::collection($this->whenLoaded('splits')),
            'attachments' => AttachmentResource::collection($this->whenLoaded('attachments')),
            'transfer_pair' => new TransactionResource($this->whenLoaded('transferPair')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
