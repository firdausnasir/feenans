<?php

namespace App\Console\Commands;

use App\Models\Bill;
use App\Models\User;
use App\Notifications\BillDueReminder;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Collection;

class CheckBillReminders extends Command
{
    protected $signature = 'bills:check-reminders';

    protected $description = 'Send notifications for upcoming, due, and overdue bills';

    public function handle(): int
    {
        $today = CarbonImmutable::today();

        $upcomingBills = $this->getUpcomingBills($today);
        $dueTodayBills = $this->getDueTodayBills($today);
        $overdueBills = $this->getOverdueBills($today);

        $notifiedCount = $this->notifyUsers($today, $upcomingBills, $dueTodayBills, $overdueBills);

        $this->info("Sent {$notifiedCount} bill summary notification(s).");

        return Command::SUCCESS;
    }

    /**
     * @return Collection<int, Bill>
     */
    private function getUpcomingBills(CarbonImmutable $today): Collection
    {
        return Bill::query()
            ->active()
            ->whereDate('next_due_date', '>', $today)
            ->whereDate('next_due_date', '<=', $today->addDays(3))
            ->with('ledger.user')
            ->get();
    }

    /**
     * @return Collection<int, Bill>
     */
    private function getDueTodayBills(CarbonImmutable $today): Collection
    {
        return Bill::query()
            ->active()
            ->whereDate('next_due_date', $today)
            ->with('ledger.user')
            ->get();
    }

    /**
     * @return Collection<int, Bill>
     */
    private function getOverdueBills(CarbonImmutable $today): Collection
    {
        return Bill::query()
            ->active()
            ->whereDate('next_due_date', '<', $today)
            ->with('ledger.user')
            ->get();
    }

    /**
     * @param  Collection<int, Bill>  $upcomingBills
     * @param  Collection<int, Bill>  $dueTodayBills
     * @param  Collection<int, Bill>  $overdueBills
     */
    private function notifyUsers(CarbonImmutable $today, Collection $upcomingBills, Collection $dueTodayBills, Collection $overdueBills): int
    {
        /** @var Collection<int, array{upcoming: Collection<int, Bill>, due_today: Collection<int, Bill>, overdue: Collection<int, Bill>}> $userBills */
        $userBills = collect();

        foreach ($upcomingBills as $bill) {
            $user = $bill->ledger?->user;

            if (! $user instanceof User) {
                continue;
            }

            if (! $userBills->has($user->id)) {
                $userBills[$user->id] = [
                    'user' => $user,
                    'upcoming' => collect(),
                    'due_today' => collect(),
                    'overdue' => collect(),
                ];
            }

            $userBills[$user->id]['upcoming']->push($bill);
        }

        foreach ($dueTodayBills as $bill) {
            $user = $bill->ledger?->user;

            if (! $user instanceof User) {
                continue;
            }

            if (! $userBills->has($user->id)) {
                $userBills[$user->id] = [
                    'user' => $user,
                    'upcoming' => collect(),
                    'due_today' => collect(),
                    'overdue' => collect(),
                ];
            }

            $userBills[$user->id]['due_today']->push($bill);
        }

        foreach ($overdueBills as $bill) {
            $user = $bill->ledger?->user;

            if (! $user instanceof User) {
                continue;
            }

            if (! $userBills->has($user->id)) {
                $userBills[$user->id] = [
                    'user' => $user,
                    'upcoming' => collect(),
                    'due_today' => collect(),
                    'overdue' => collect(),
                ];
            }

            $userBills[$user->id]['overdue']->push($bill);
        }

        $count = 0;

        foreach ($userBills as $data) {
            /** @var User $user */
            $user = $data['user'];
            /** @var Collection<int, Bill> $upcoming */
            $upcoming = $data['upcoming'];
            /** @var Collection<int, Bill> $dueToday */
            $dueToday = $data['due_today'];
            /** @var Collection<int, Bill> $overdue */
            $overdue = $data['overdue'];

            if ($this->hasReceivedSummaryToday($user, $today)) {
                continue;
            }

            $user->notify(new BillDueReminder($upcoming, $dueToday, $overdue));
            $count++;
        }

        return $count;
    }

    private function hasReceivedSummaryToday(User $user, CarbonImmutable $today): bool
    {
        return $user->unreadNotifications()
            ->whereDate('created_at', $today)
            ->get()
            ->contains(function (DatabaseNotification $notification): bool {
                return ($notification->data['type'] ?? null) === 'bill_summary_reminder';
            });
    }
}
