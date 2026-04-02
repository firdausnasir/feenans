<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BudgetResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // Shared-core budget queries may return plain arrays.
        if (is_array($this->resource)) {
            return $this->resource;
        }

        return [
            'id' => $this->id,
            'ledger_id' => $this->ledger_id,
            'category_id' => $this->category_id,
            'amount' => $this->amount,
            'period' => $this->period,
            'start_date' => $this->start_date?->toISOString(),
            'end_date' => $this->end_date?->toISOString(),
            'rollover' => (bool) $this->rollover,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            // Enriched stats are attached by shared-core budget queries, not model attributes.
            'category_name' => $this->when(isset($this->category_name), $this->category_name),
            'category_color' => $this->when(isset($this->category_color), $this->category_color),
            'spent' => $this->when(isset($this->spent), $this->spent),
            'remaining' => $this->when(isset($this->remaining), $this->remaining),
            'percentage' => $this->when(isset($this->percentage), $this->percentage),
            'status' => $this->when(isset($this->status), $this->status),
            'period_start' => $this->when(isset($this->period_start), $this->period_start),
            'period_end' => $this->when(isset($this->period_end), $this->period_end),
        ];
    }
}
