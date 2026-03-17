<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TransactionSplitResource extends JsonResource
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
            'transaction_id' => $this->transaction_id,
            'category_id' => $this->category_id,
            'payee_id' => $this->payee_id,
            'amount' => $this->amount,
            'description' => $this->description,
            'category' => new CategoryResource($this->whenLoaded('category')),
            'payee' => new PayeeResource($this->whenLoaded('payee')),
        ];
    }
}
