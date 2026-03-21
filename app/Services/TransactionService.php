<?php

namespace App\Services;

use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\Category;
use App\Models\Ledger;
use App\Models\Payee;
use App\Models\Transaction;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class TransactionService
{
    protected function normalizeAmount(TransactionType $transactionType, mixed $amount): float
    {
        $normalizedAmount = abs((float) $amount);

        return match ($transactionType) {
            TransactionType::Expense => -1 * $normalizedAmount,
            TransactionType::Income => $normalizedAmount,
            TransactionType::Transfer => (float) $amount,
        };
    }

    /**
     * @param  array<int, array{amount:mixed,category_id:mixed,description:mixed,payee_id?:mixed}>|null  $splits
     * @return array<int, array{amount:float,category_id:mixed,description:mixed,payee_id:mixed}>
     */
    protected function normalizeSplits(TransactionType $transactionType, ?array $splits): array
    {
        if (! is_array($splits)) {
            return [];
        }

        return array_map(function (array $split) use ($transactionType): array {
            return [
                'amount' => $this->normalizeAmount($transactionType, $split['amount'] ?? 0),
                'category_id' => $split['category_id'] ?? null,
                'payee_id' => $split['payee_id'] ?? null,
                'description' => $split['description'] ?? null,
            ];
        }, $splits);
    }

    public function store(array $attributes): Transaction
    {
        /** @var Ledger $ledger */
        $ledger = $attributes['ledger'];

        /** @var Account $account */
        $account = $attributes['account'];

        /** @var Category|null $category */
        $category = $attributes['category'] ?? null;

        /** @var Payee|null $payee */
        $payee = $attributes['payee'] ?? null;

        return DB::transaction(function () use ($account, $attributes, $category, $ledger, $payee): Transaction {
            /** @var TransactionType $transactionType */
            $transactionType = $attributes['transaction_type'];

            $transaction = $ledger->transactions()->create([
                'account_id' => $account->id,
                'category_id' => $category?->id,
                'payee_id' => $payee?->id,
                'transaction_type' => $transactionType,
                'amount' => $this->normalizeAmount($transactionType, $attributes['amount']),
                'description' => $attributes['description'] ?? null,
                'notes' => $attributes['notes'] ?? null,
                'transaction_date' => $attributes['transaction_date'],
                'transfer_pair_id' => $attributes['transfer_pair_id'] ?? null,
                'bill_id' => $attributes['bill_id'] ?? null,
            ]);

            $this->syncSplits($transaction, $this->normalizeSplits($transactionType, $attributes['splits'] ?? null));

            return $transaction->fresh(['splits']);
        });
    }

    /**
     * @return array{0: Transaction, 1: Transaction}
     */
    public function storeTransfer(Ledger $ledger, array $attributes): array
    {
        /** @var Account $fromAccount */
        $fromAccount = $attributes['from_account'];

        /** @var Account $toAccount */
        $toAccount = $attributes['to_account'];

        return DB::transaction(function () use ($attributes, $fromAccount, $ledger, $toAccount): array {
            $pairId = (string) Str::uuid();
            $amount = abs((float) $attributes['amount']);

            $outgoing = $this->store([
                'ledger' => $ledger,
                'account' => $fromAccount,
                'transaction_type' => TransactionType::Transfer,
                'amount' => -1 * $amount,
                'description' => $attributes['description'] ?? null,
                'notes' => $attributes['notes'] ?? null,
                'transaction_date' => $attributes['transaction_date'],
                'transfer_pair_id' => $pairId,
                'bill_id' => $attributes['bill_id'] ?? null,
            ]);

            $incoming = $this->store([
                'ledger' => $ledger,
                'account' => $toAccount,
                'transaction_type' => TransactionType::Transfer,
                'amount' => $amount,
                'description' => $attributes['description'] ?? null,
                'notes' => $attributes['notes'] ?? null,
                'transaction_date' => $attributes['transaction_date'],
                'transfer_pair_id' => $pairId,
                'bill_id' => $attributes['bill_id'] ?? null,
            ]);

            return [$outgoing, $incoming];
        });
    }

    public function update(Transaction $transaction, array $data): Transaction
    {
        if ($transaction->transfer_pair_id !== null) {
            return DB::transaction(function () use ($transaction, $data): Transaction {
                $amount = abs((float) $data['amount']);

                /** @var Account $fromAccount */
                $fromAccount = $data['account'];

                /** @var Account $toAccount */
                $toAccount = $data['to_account'];

                $pair = Transaction::query()
                    ->where('transfer_pair_id', $transaction->transfer_pair_id)
                    ->where('id', '!=', $transaction->id)
                    ->firstOrFail();

                // Determine which is the source (negative) and which is the destination (positive)
                if ((float) $transaction->amount < 0) {
                    $source = $transaction;
                    $destination = $pair;
                } else {
                    $source = $pair;
                    $destination = $transaction;
                }

                $source->update([
                    'account_id' => $fromAccount->id,
                    'amount' => -1 * $amount,
                    'transaction_date' => $data['transaction_date'],
                    'description' => $data['description'] ?? null,
                    'notes' => $data['notes'] ?? null,
                    'category_id' => null,
                    'payee_id' => $data['payee_id'] ?? null,
                ]);

                $destination->update([
                    'account_id' => $toAccount->id,
                    'amount' => $amount,
                    'transaction_date' => $data['transaction_date'],
                    'description' => $data['description'] ?? null,
                    'notes' => $data['notes'] ?? null,
                ]);

                return $transaction->fresh();
            });
        }

        /** @var Category|null $category */
        $category = $data['category'] ?? null;

        /** @var Payee|null $payee */
        $payee = $data['payee'] ?? null;

        /** @var Account $account */
        $account = $data['account'];

        return DB::transaction(function () use ($account, $category, $data, $payee, $transaction): Transaction {
            $transactionType = ($data['transaction_type'] ?? null) instanceof TransactionType
                ? $data['transaction_type']
                : $transaction->transaction_type;

            $transaction->update([
                'account_id' => $account->id,
                'category_id' => $category?->id,
                'payee_id' => $payee?->id,
                'amount' => $this->normalizeAmount($transactionType, $data['amount']),
                'description' => $data['description'] ?? null,
                'notes' => $data['notes'] ?? null,
                'transaction_date' => $data['transaction_date'],
            ]);

            $this->syncSplits($transaction, $this->normalizeSplits($transactionType, $data['splits'] ?? null));

            return $transaction->fresh(['splits']);
        });
    }

    public function convertTransferToSingle(Transaction $transaction, array $data): Transaction
    {
        return DB::transaction(function () use ($transaction, $data): Transaction {
            // Delete the paired transaction
            Transaction::query()
                ->where('transfer_pair_id', $transaction->transfer_pair_id)
                ->where('id', '!=', $transaction->id)
                ->delete();

            $amount = $data['transaction_type'] === TransactionType::Expense
                ? -abs((float) $data['amount'])
                : abs((float) $data['amount']);

            $transaction->update([
                'account_id' => $data['account']->id,
                'category_id' => $data['category']?->id,
                'payee_id' => $data['payee']?->id,
                'transaction_type' => $data['transaction_type'],
                'amount' => $amount,
                'description' => $data['description'] ?? null,
                'notes' => $data['notes'] ?? null,
                'transaction_date' => $data['transaction_date'],
                'transfer_pair_id' => null,
            ]);

            return $transaction->fresh();
        });
    }

    public function convertSingleToTransfer(Transaction $transaction, Ledger $ledger, array $data): array
    {
        return DB::transaction(function () use ($transaction, $ledger, $data): array {
            $pairId = (string) Str::uuid();
            $amount = abs((float) $data['amount']);

            // Update existing transaction as outgoing side
            $transaction->update([
                'account_id' => $data['from_account']->id,
                'category_id' => null,
                'payee_id' => null,
                'transaction_type' => TransactionType::Transfer,
                'amount' => -1 * $amount,
                'description' => $data['description'] ?? null,
                'notes' => $data['notes'] ?? null,
                'transaction_date' => $data['transaction_date'],
                'transfer_pair_id' => $pairId,
            ]);

            // Create the incoming paired transaction
            $incoming = $this->store([
                'ledger' => $ledger,
                'account' => $data['to_account'],
                'transaction_type' => TransactionType::Transfer,
                'amount' => $amount,
                'description' => $data['description'] ?? null,
                'notes' => $data['notes'] ?? null,
                'transaction_date' => $data['transaction_date'],
                'transfer_pair_id' => $pairId,
            ]);

            return [$transaction->fresh(), $incoming];
        });
    }

    public function delete(Transaction $transaction): void
    {
        if ($transaction->transfer_pair_id !== null) {
            DB::transaction(function () use ($transaction): void {
                Transaction::query()
                    ->where('transfer_pair_id', $transaction->transfer_pair_id)
                    ->get()
                    ->each(function (Transaction $pairedTransaction): void {
                        $pairedTransaction->delete();
                    });
            });

            return;
        }

        $transaction->delete();
    }

    public function forceDelete(Transaction $transaction): void
    {
        if ($transaction->transfer_pair_id !== null) {
            DB::transaction(function () use ($transaction): void {
                Transaction::query()
                    ->where('transfer_pair_id', $transaction->transfer_pair_id)
                    ->get()
                    ->each(function (Transaction $pairedTransaction): void {
                        $pairedTransaction->splits()->delete();
                        $this->deleteAttachments($pairedTransaction);
                        $pairedTransaction->delete();
                    });
            });

            return;
        }

        $transaction->splits()->delete();
        $this->deleteAttachments($transaction);
        $transaction->delete();
    }

    /**
     * Delete all attachment files from disk and remove records.
     */
    private function deleteAttachments(Transaction $transaction): void
    {
        foreach ($transaction->attachments as $attachment) {
            Storage::delete($attachment->path);
            $attachment->delete();
        }
    }

    /**
     * @param  array<int, array{amount:mixed,category_id:mixed,description:mixed,payee_id?:mixed}>|null  $splits
     */
    protected function syncSplits(Transaction $transaction, ?array $splits): void
    {
        $transaction->splits()->delete();

        if (! is_array($splits) || $splits === []) {
            return;
        }

        $transaction->splits()->createMany(array_map(function (array $split): array {
            return [
                'category_id' => $split['category_id'] ?? null,
                'payee_id' => $split['payee_id'] ?? null,
                'amount' => $split['amount'],
                'description' => $split['description'] ?? null,
            ];
        }, $splits));
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public function statementCycleBounds(Account $account, CarbonImmutable $date): array
    {
        $statementDay = $account->statement_day ?? 1;

        if ($date->day <= $statementDay) {
            $cycleEnd = $date->setDay(min($statementDay, $date->daysInMonth));
        } else {
            $nextMonth = $date->addMonthNoOverflow();
            $cycleEnd = $nextMonth->setDay(min($statementDay, $nextMonth->daysInMonth));
        }

        $previousCycleEnd = $cycleEnd->subMonthNoOverflow();
        $cycleStart = $previousCycleEnd->addDay();

        return [$cycleStart, $cycleEnd];
    }
}
