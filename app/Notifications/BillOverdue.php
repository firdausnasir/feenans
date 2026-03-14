<?php

namespace App\Notifications;

use App\Models\Bill;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BillOverdue extends Notification implements ShouldQueue
{
    use Queueable;

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
        $daysOverdue = $this->bill->next_due_date->diffInDays(now());

        return (new MailMessage)
            ->subject("Overdue: {$this->bill->name}")
            ->greeting("Hi {$notifiable->name},")
            ->line("Your bill **{$this->bill->name}** was due on {$this->bill->next_due_date->format('d M Y')} and is now {$daysOverdue} day(s) overdue.")
            ->line('Amount: '.number_format((float) $this->bill->amount, 2))
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
