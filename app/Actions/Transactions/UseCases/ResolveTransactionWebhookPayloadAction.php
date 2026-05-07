<?php

namespace App\Actions\Transactions\UseCases;

use App\Data\Transactions\Input\TransactionWebhookPayloadData;
use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\Ledger;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;
use Throwable;

class ResolveTransactionWebhookPayloadAction
{
    /**
     * @param  array{amount:string,type?:string|null,date?:string|null,ledger_id?:int|null,account:string,description:string}  $payload
     */
    public function __invoke(User $user, array $payload, ?int $tokenLedgerId = null): TransactionWebhookPayloadData
    {
        $ledgerId = $this->resolveLedgerId($payload['ledger_id'] ?? null, $tokenLedgerId);
        $ledger = $this->resolveLedger($user, $ledgerId);
        $account = $this->resolveAccount($ledger, $payload['account']);

        $this->ensureAccountAllowed($user, $ledger, $account);

        $transactionType = $this->resolveTransactionType($payload['type'] ?? null);

        return new TransactionWebhookPayloadData(
            user_id: $user->id,
            ledger_id: $ledger->id,
            account_id: $account->id,
            account_name: $account->name,
            transaction_type: $transactionType->value,
            amount: $this->parseAmount($payload['amount']),
            transaction_date: $this->parseDate($user, $payload['date'] ?? null),
            description: $payload['description'],
        );
    }

    private function resolveLedgerId(mixed $payloadLedgerId, ?int $tokenLedgerId): int
    {
        if ($tokenLedgerId !== null) {
            if ($payloadLedgerId !== null && (int) $payloadLedgerId !== $tokenLedgerId) {
                throw ValidationException::withMessages([
                    'ledger_id' => 'The selected ledger does not match this webhook token.',
                ]);
            }

            return $tokenLedgerId;
        }

        if ($payloadLedgerId !== null) {
            return (int) $payloadLedgerId;
        }

        throw ValidationException::withMessages([
            'ledger_id' => 'The ledger id is required when the webhook token is not ledger-scoped.',
        ]);
    }

    private function resolveLedger(User $user, int $ledgerId): Ledger
    {
        $ledger = $user->ledgers()
            ->whereKey($ledgerId)
            ->first();

        if (! $ledger instanceof Ledger) {
            throw ValidationException::withMessages([
                'ledger_id' => 'The selected ledger is invalid.',
            ]);
        }

        return $ledger;
    }

    private function resolveAccount(Ledger $ledger, string $accountName): Account
    {
        $account = $ledger->accounts()
            ->where('name', trim($accountName))
            ->first();

        if (! $account instanceof Account) {
            throw ValidationException::withMessages([
                'account' => 'The selected account is invalid for this ledger.',
            ]);
        }

        return $account;
    }

    private function ensureAccountAllowed(User $user, Ledger $ledger, Account $account): void
    {
        if ($user->isPremium()) {
            return;
        }

        $allowedAccountIds = $ledger->accounts()
            ->orderBy('position')
            ->orderBy('id')
            ->limit(7)
            ->pluck('id');

        if ($allowedAccountIds->contains($account->id)) {
            return;
        }

        throw ValidationException::withMessages([
            'account' => 'This account is not available on the free plan.',
        ]);
    }

    private function resolveTransactionType(?string $type): TransactionType
    {
        if ($type === null || trim($type) === '') {
            return TransactionType::Expense;
        }

        return TransactionType::from($type);
    }

    private function parseAmount(string $amount): float
    {
        $numeric = preg_replace('/[^\d.,-]/', '', trim($amount)) ?? '';

        if ($numeric === '' || $numeric === '-' || $numeric === '.' || $numeric === ',') {
            throw ValidationException::withMessages([
                'amount' => 'The amount must contain a valid numeric value.',
            ]);
        }

        if (str_contains($numeric, ',') && str_contains($numeric, '.')) {
            $numeric = str_replace(',', '', $numeric);
        } elseif (preg_match('/,\d{1,2}$/', $numeric) === 1) {
            $numeric = str_replace(',', '.', str_replace('.', '', $numeric));
        } else {
            $numeric = str_replace(',', '', $numeric);
        }

        if (! is_numeric($numeric)) {
            throw ValidationException::withMessages([
                'amount' => 'The amount must contain a valid numeric value.',
            ]);
        }

        $parsed = round(abs((float) $numeric), 2);

        if ($parsed <= 0.0) {
            throw ValidationException::withMessages([
                'amount' => 'The amount must be greater than zero.',
            ]);
        }

        return $parsed;
    }

    private function parseDate(User $user, ?string $date): string
    {
        $timezone = $user->timezone ?: config('app.timezone');

        if ($date === null || trim($date) === '') {
            return CarbonImmutable::now($timezone)->toDateString();
        }

        try {
            return CarbonImmutable::parse($date, $timezone)->toDateString();
        } catch (Throwable) {
            throw ValidationException::withMessages([
                'date' => 'The date must be a parseable date value.',
            ]);
        }
    }
}
