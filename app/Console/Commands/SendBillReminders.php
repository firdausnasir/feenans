<?php

namespace App\Console\Commands;

use App\Models\Bill;
use App\Models\User;
use App\Notifications\BillDueReminder;
use App\Notifications\BillOverdue;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

class SendBillReminders extends Command
{
    protected $signature = 'bills:send-reminders';

    protected $description = 'Send bill due reminders and overdue notifications to users';

    public function handle(): int
    {
        $today = CarbonImmutable::today();
        $reminderDate = $today->addDays(3);

        // Bills due in 3 days (upcoming reminder)
        $upcomingBills = Bill::query()
            ->active()
            ->whereDate('next_due_date', $reminderDate)
            ->with('ledger.user')
            ->get();

        foreach ($upcomingBills as $bill) {
            $user = $bill->ledger?->user;
            if ($user instanceof User) {
                $user->notify(new BillDueReminder($bill));
            }
        }

        $this->info("Sent {$upcomingBills->count()} upcoming bill reminder(s).");

        // Overdue bills (past due, non-auto)
        $overdueBills = Bill::query()
            ->active()
            ->missed()
            ->with('ledger.user')
            ->get();

        foreach ($overdueBills as $bill) {
            $user = $bill->ledger?->user;
            if ($user instanceof User) {
                $user->notify(new BillOverdue($bill));
            }
        }

        $this->info("Sent {$overdueBills->count()} overdue bill notification(s).");

        return Command::SUCCESS;
    }
}
