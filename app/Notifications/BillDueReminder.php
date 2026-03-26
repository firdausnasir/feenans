<?php

namespace App\Notifications;

use App\Models\Bill;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;

class BillDueReminder extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  Collection<int, Bill>  $upcomingBills
     * @param  Collection<int, Bill>  $dueTodayBills
     * @param  Collection<int, Bill>  $overdueBills
     */
    public function __construct(
        public readonly Collection $upcomingBills,
        public readonly Collection $dueTodayBills,
        public readonly Collection $overdueBills
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $totalBills = $this->upcomingBills->count() + $this->dueTodayBills->count() + $this->overdueBills->count();

        $mailMessage = (new MailMessage)
            ->subject("You have {$totalBills} bill(s) requiring attention")
            ->greeting("Hi {$notifiable->name},");

        if ($this->overdueBills->isNotEmpty()) {
            $mailMessage->line('**Overdue Bills:**');
            foreach ($this->overdueBills as $bill) {
                $daysOverdue = $bill->next_due_date->diffInDays(now());
                $mailMessage->line("• {$bill->name} - Due: {$bill->next_due_date->format('d M Y')} ({$daysOverdue} days overdue) - Amount: $".number_format((float) $bill->amount, 2));
            }
            $mailMessage->line('');
        }

        if ($this->dueTodayBills->isNotEmpty()) {
            $mailMessage->line('**Due Today:**');
            foreach ($this->dueTodayBills as $bill) {
                $mailMessage->line("• {$bill->name} - Amount: $".number_format((float) $bill->amount, 2));
            }
            $mailMessage->line('');
        }

        if ($this->upcomingBills->isNotEmpty()) {
            $mailMessage->line('**Upcoming Bills (Next 3 Days):**');
            foreach ($this->upcomingBills as $bill) {
                $mailMessage->line("• {$bill->name} - Due: {$bill->next_due_date->format('d M Y')} - Amount: $".number_format((float) $bill->amount, 2));
            }
            $mailMessage->line('');
        }

        $mailMessage->action('View All Bills', url('/'))
            ->line('Log in to mark bills as paid.');

        return $mailMessage;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'bill_summary_reminder',
            'upcoming_count' => $this->upcomingBills->count(),
            'due_today_count' => $this->dueTodayBills->count(),
            'overdue_count' => $this->overdueBills->count(),
            'total_bills' => $this->upcomingBills->count() + $this->dueTodayBills->count() + $this->overdueBills->count(),
        ];
    }
}
