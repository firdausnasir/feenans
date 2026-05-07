<?php

namespace App\Enums;

enum ApiTokenAbility: string
{
    case All = '*';
    case TransactionWebhook = 'transactions:webhook';

    private const TransactionWebhookLedgerPattern = '/^transactions:webhook:ledger:(\d+)$/';

    public function label(): string
    {
        return match ($this) {
            self::All => 'Full access',
            self::TransactionWebhook => 'Transaction webhook',
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(
            fn (self $ability): string => $ability->value,
            self::cases(),
        );
    }

    public static function transactionWebhookForLedger(int $ledgerId): string
    {
        return self::TransactionWebhook->value.':ledger:'.$ledgerId;
    }

    public static function isValid(string $ability): bool
    {
        return in_array($ability, self::values(), true)
            || preg_match(self::TransactionWebhookLedgerPattern, $ability) === 1;
    }

    /**
     * @param  list<string>|null  $abilities
     */
    public static function ledgerIdFromWebhookAbilities(?array $abilities): ?int
    {
        foreach ($abilities ?? [] as $ability) {
            if (! is_string($ability)) {
                continue;
            }

            if (preg_match(self::TransactionWebhookLedgerPattern, $ability, $matches) !== 1) {
                continue;
            }

            return (int) $matches[1];
        }

        return null;
    }
}
