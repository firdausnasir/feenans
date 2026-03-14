<?php

namespace App\Console\Commands;

use App\Models\Account;
use App\Models\Bill;
use App\Models\Budget;
use App\Models\Category;
use App\Models\Payee;
use App\Models\Tag;
use App\Models\Transaction;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;

class PurgeTrash extends Command
{
    protected $signature = 'trash:purge {--days=30 : Number of days after which trashed items are permanently deleted}';

    protected $description = 'Permanently delete soft-deleted items older than the specified number of days';

    public function handle(): int
    {
        $days = (int) $this->option('days');
        $cutoff = now()->subDays($days);
        $totalPurged = 0;

        /** @var array<int, class-string<Model>> $models */
        $models = [
            Transaction::class,
            Account::class,
            Bill::class,
            Budget::class,
            Category::class,
            Payee::class,
            Tag::class,
        ];

        foreach ($models as $model) {
            $count = $model::onlyTrashed()
                ->where('deleted_at', '<', $cutoff)
                ->count();

            if ($count > 0) {
                $model::onlyTrashed()
                    ->where('deleted_at', '<', $cutoff)
                    ->forceDelete();

                $modelName = class_basename($model);
                $this->info("Purged {$count} {$modelName} record(s).");
                $totalPurged += $count;
            }
        }

        if ($totalPurged === 0) {
            $this->info('No trashed items to purge.');
        } else {
            $this->info("Total purged: {$totalPurged} record(s).");
        }

        return Command::SUCCESS;
    }
}
