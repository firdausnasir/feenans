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

        $this->addBillsToUserGroups($userBills, $upcomingBills, 'upcoming');
        $this->addBillsToUserGroups($userBills, $dueTodayBills, 'due_today');
        $this->addBillsToUserGroups($userBills, $overdueBills, 'overdue');

        $usersWithSummaryToday = $this->getUsersWithSummaryToday($userBills->keys(), $today);

        $count = 0;

        foreach ($userBills as $userId => $data) {
            /** @var User $user */
            $user = $data['user'];
            /** @var Collection<int, Bill> $upcoming */
            $upcoming = $data['upcoming'];
            /** @var Collection<int, Bill> $dueToday */
            $dueToday = $data['due_today'];
            /** @var Collection<int, Bill> $overdue */
            $overdue = $data['overdue'];

            if ($usersWithSummaryToday[(int) $userId] ?? false) {
                continue;
            }

            $user->notify(new BillDueReminder($upcoming, $dueToday, $overdue));
            $usersWithSummaryToday[(int) $userId] = true;
            $count++;
        }

        return $count;
    }

    /**
     * @param  Collection<int, array{user: User, upcoming: Collection<int, Bill>, due_today: Collection<int, Bill>, overdue: Collection<int, Bill>}>  $userBills
     * @param  Collection<int, Bill>  $bills
     * @param  'upcoming'|'due_today'|'overdue'  $bucket
     */
    private function addBillsToUserGroups(Collection $userBills, Collection $bills, string $bucket): void
    {
        foreach ($bills as $bill) {
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

            $userBills[$user->id][$bucket]->push($bill);
        }
    }

    /**
     * @param  Collection<int, int|string>  $userIds
     * @return array<int, bool>
     */
    private function getUsersWithSummaryToday(Collection $userIds, CarbonImmutable $today): array
    {
        if ($userIds->isEmpty()) {
            return [];
        }

        $usersWithSummaryToday = [];

        DatabaseNotification::query()
            ->where('notifiable_type', User::class)
            ->whereIn('notifiable_id', $userIds->all())
            ->whereNull('read_at')
            ->where('created_at', '>=', $today->startOfDay())
            ->where('created_at', '<=', $today->endOfDay())
            ->get()
            ->each(function (DatabaseNotification $notification) use (&$usersWithSummaryToday): void {
                if (($notification->data['type'] ?? null) !== 'bill_summary_reminder') {
                    return;
                }

                $usersWithSummaryToday[(int) $notification->notifiable_id] = true;
            });

        return $usersWithSummaryToday;
    }
}
