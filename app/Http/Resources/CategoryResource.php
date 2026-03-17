<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CategoryResource extends JsonResource
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
            'parent_id' => $this->parent_id,
            'name' => $this->name,
            'transaction_type' => $this->transaction_type,
            'color' => $this->color,
            'icon' => $this->icon,
            'position' => $this->position,
            'transactions_count' => $this->when(
                $this->transactions_count !== null,
                $this->transactions_count
            ),
            'children' => CategoryResource::collection($this->whenLoaded('children')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
