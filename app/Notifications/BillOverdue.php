<?php

namespace App\Notifications;

use App\Models\Bill;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BillOverdue extends Notification implements ShouldQueue
{
    use FormatsBillCurrency, Queueable;

    public function __construct(public readonly Bill $bill) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $this->bill->loadMissing('ledger');

        $daysOverdue = $this->bill->next_due_date->diffInDays(CarbonImmutable::today());

        return (new MailMessage)
            ->subject("Overdue: {$this->bill->name}")
            ->greeting("Hi {$notifiable->name},")
            ->line("Your bill **{$this->bill->name}** was due on {$this->bill->next_due_date->format('d M Y')} and is now {$daysOverdue} day(s) overdue.")
            ->line('Amount: '.$this->formatBillAmount($this->bill))
            ->action('View Bills', url('/'))
            ->line('Please log in to record the payment.');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'bill_overdue',
            'bill_id' => $this->bill->id,
            'bill_name' => $this->bill->name,
            'due_date' => $this->bill->next_due_date->toDateString(),
            'amount' => (float) $this->bill->amount,
        ];
    }
}
