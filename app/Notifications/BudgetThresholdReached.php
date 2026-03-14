<?php

namespace App\Notifications;

use App\Models\Budget;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class BudgetThresholdReached extends Notification
{
    use Queueable;

    public function __construct(
        public readonly Budget $budget,
        public readonly float $percentage,
        public readonly float $spent,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'budget_threshold',
            'budget_id' => $this->budget->id,
            'budget_name' => $this->budget->category?->name ?? 'Overall',
            'percentage' => $this->percentage,
            'spent' => $this->spent,
            'limit' => (float) $this->budget->amount,
        ];
    }
}
