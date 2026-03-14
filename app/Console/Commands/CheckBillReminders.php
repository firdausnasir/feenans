<?php

namespace App\Console\Commands;

use App\Models\Bill;
use App\Models\User;
use App\Notifications\BillDueReminder;
use App\Notifications\BillOverdue;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Notifications\DatabaseNotification;

class CheckBillReminders extends Command
{
    protected $signature = 'bills:check-reminders';

    protected $description = 'Send notifications for upcoming, due, and overdue bills';

    public function handle(): int
    {
        $today = CarbonImmutable::today();

        $upcomingCount = $this->notifyUpcoming($today);
        $this->info("Sent {$upcomingCount} upcoming bill reminder(s).");

        $dueCount = $this->notifyDueToday($today);
        $this->info("Sent {$dueCount} due-today bill reminder(s).");

        $overdueCount = $this->notifyOverdue($today);
        $this->info("Sent {$overdueCount} overdue bill notification(s).");

        return Command::SUCCESS;
    }

    private function notifyUpcoming(CarbonImmutable $today): int
    {
        $bills = Bill::query()
            ->active()
            ->whereDate('next_due_date', '>', $today)
            ->whereDate('next_due_date', '<=', $today->addDays(3))
            ->with('ledger.user')
            ->get();

        $count = 0;

        foreach ($bills as $bill) {
            $user = $bill->ledger?->user;

            if (! $user instanceof User) {
                continue;
            }

            if ($this->hasRecentUnreadNotification($user, $bill->id, 'bill_due_reminder', $today)) {
                continue;
            }

            $user->notify(new BillDueReminder($bill));
            $count++;
        }

        return $count;
    }

    private function notifyDueToday(CarbonImmutable $today): int
    {
        $bills = Bill::query()
            ->active()
            ->whereDate('next_due_date', $today)
            ->with('ledger.user')
            ->get();

        $count = 0;

        foreach ($bills as $bill) {
            $user = $bill->ledger?->user;

            if (! $user instanceof User) {
                continue;
            }

            if ($this->hasRecentUnreadNotification($user, $bill->id, 'bill_due_reminder', $today)) {
                continue;
            }

            $user->notify(new BillDueReminder($bill));
            $count++;
        }

        return $count;
    }

    private function notifyOverdue(CarbonImmutable $today): int
    {
        $bills = Bill::query()
            ->active()
            ->whereDate('next_due_date', '<', $today)
            ->with('ledger.user')
            ->get();

        $count = 0;

        foreach ($bills as $bill) {
            $user = $bill->ledger?->user;

            if (! $user instanceof User) {
                continue;
            }

            if ($this->hasRecentUnreadNotification($user, $bill->id, 'bill_overdue', $today)) {
                continue;
            }

            $user->notify(new BillOverdue($bill));
            $count++;
        }

        return $count;
    }

    private function hasRecentUnreadNotification(User $user, int $billId, string $type, CarbonImmutable $today): bool
    {
        return $user->unreadNotifications()
            ->whereDate('created_at', $today)
            ->get()
            ->contains(function (DatabaseNotification $notification) use ($billId, $type): bool {
                $data = $notification->data;

                return ($data['type'] ?? null) === $type
                    && ($data['bill_id'] ?? null) === $billId;
            });
    }
}
