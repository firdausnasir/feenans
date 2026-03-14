<?php

namespace App\Notifications;

use App\Models\Bill;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BillDueReminder extends Notification implements ShouldQueue
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
        return (new MailMessage)
            ->subject("Reminder: {$this->bill->name} is due soon")
            ->greeting("Hi {$notifiable->name},")
            ->line("Your bill **{$this->bill->name}** is due on {$this->bill->next_due_date->format('d M Y')}.")
            ->line('Amount: ' . number_format((float) $this->bill->amount, 2))
            ->action('View Bills', url('/'))
            ->line('Log in to mark it as paid.');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'bill_due_reminder',
            'bill_id' => $this->bill->id,
            'bill_name' => $this->bill->name,
            'due_date' => $this->bill->next_due_date->toDateString(),
            'amount' => (float) $this->bill->amount,
        ];
    }
}
