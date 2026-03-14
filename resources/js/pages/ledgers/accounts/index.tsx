import { Head, Link } from '@inertiajs/react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { formatAmount } from '@/lib/format';
import { dashboard as ledgerDashboard } from '@/routes/ledgers';
import {
    create,
    index as accountsIndex,
    show as accountShow,
} from '@/routes/ledgers/accounts';
import type { Account, AccountType, BreadcrumbItem, Ledger } from '@/types';

export default function AccountsIndex({
    ledger,
    accounts,
    accountTypes,
    netWorth,
}: {
    ledger: Ledger;
    accounts: Account[];
    accountTypes: AccountType[];
    netWorth: { assets: number; liabilities: number; net: number };
}) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: ledger.name, href: ledgerDashboard.url(ledger.id) },
        { title: 'Accounts', href: accountsIndex.url(ledger.id) },
    ];

    // Group accounts by account type, preserving accountTypes order
    const grouped = accountTypes
        .map((type) => ({
            type,
            accounts: accounts.filter((a) => a.account_type_id === type.id),
        }))
        .filter((group) => group.accounts.length > 0);

    // Accounts with no matching type (safety net)
    const ungrouped = accounts.filter(
        (a) => !accountTypes.find((t) => t.id === a.account_type_id),
    );

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`${ledger.name} accounts`} />

            <div className="flex h-full flex-1 flex-col gap-8 p-4">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">
                            Accounts
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Track balances across all ledger accounts.
                        </p>
                    </div>

                    <Button asChild>
                        <Link href={create.url(ledger.id)}>New Account</Link>
                    </Button>
                </div>

                <div className="grid gap-4 md:grid-cols-3">
                    <Card className="py-4">
                        <CardContent>
                            <p className="text-sm text-muted-foreground">
                                Total Assets
                            </p>
                            <p
                                className={`mt-1 text-2xl font-semibold tabular-nums ${
                                    netWorth.assets > 0
                                        ? 'text-green-600'
                                        : 'text-foreground'
                                }`}
                            >
                                {formatAmount(netWorth.assets)}
                            </p>
                        </CardContent>
                    </Card>

                    <Card className="py-4">
                        <CardContent>
                            <p className="text-sm text-muted-foreground">
                                Total Liabilities
                            </p>
                            <p
                                className={`mt-1 text-2xl font-semibold tabular-nums ${
                                    netWorth.liabilities < 0
                                        ? 'text-red-500'
                                        : 'text-foreground'
                                }`}
                            >
                                {formatAmount(netWorth.liabilities)}
                            </p>
                        </CardContent>
                    </Card>

                    <Card className="py-4">
                        <CardContent>
                            <p className="text-sm text-muted-foreground">
                                Net Worth
                            </p>
                            <p
                                className={`mt-1 text-2xl font-semibold tabular-nums ${
                                    netWorth.net >= 0
                                        ? 'text-green-600'
                                        : 'text-red-500'
                                }`}
                            >
                                {formatAmount(netWorth.net)}
                            </p>
                        </CardContent>
                    </Card>
                </div>

                {accounts.length === 0 && (
                    <p className="text-sm text-muted-foreground">
                        No accounts yet. Create one to get started.
                    </p>
                )}

                {grouped.map(({ type, accounts: typeAccounts }) => {
                    const color = type.color ?? '#6b7280';

                    return (
                        <section key={type.id}>
                            <div className="mb-3 flex items-center gap-2">
                                <span
                                    className="size-3 rounded-full"
                                    style={{ backgroundColor: color }}
                                />
                                <h2 className="text-sm font-semibold tracking-wide text-muted-foreground uppercase">
                                    {type.name}
                                </h2>
                            </div>

                            <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                                {typeAccounts.map((account) => {
                                    const balance = parseFloat(
                                        String(
                                            account.current_balance ??
                                                account.initial_balance ??
                                                '0',
                                        ),
                                    );
                                    const isNegative = balance < 0;

                                    return (
                                        <Link
                                            key={account.id}
                                            href={accountShow.url({
                                                ledger: ledger.id,
                                                account: account.id,
                                            })}
                                            className="block"
                                        >
                                            <Card className="group py-4 transition-colors hover:bg-muted/30">
                                                <CardContent>
                                                    <div className="flex items-start justify-between gap-2">
                                                        <p className="text-base font-semibold group-hover:underline">
                                                            {account.name}
                                                        </p>
                                                        {account.statement_day !==
                                                            null && (
                                                            <Badge variant="secondary">
                                                                Day{' '}
                                                                {
                                                                    account.statement_day
                                                                }
                                                            </Badge>
                                                        )}
                                                    </div>

                                                    <p
                                                        className={`mt-3 text-xl font-semibold tabular-nums ${
                                                            isNegative
                                                                ? 'text-red-500'
                                                                : 'text-foreground'
                                                        }`}
                                                    >
                                                        {formatAmount(balance)}
                                                    </p>

                                                    <div className="mt-3 flex items-center gap-1.5">
                                                        <span
                                                            className="size-2 rounded-full"
                                                            style={{
                                                                backgroundColor:
                                                                    color,
                                                            }}
                                                        />
                                                        <span className="text-xs text-muted-foreground">
                                                            {type.name}
                                                        </span>
                                                    </div>
                                                </CardContent>
                                            </Card>
                                        </Link>
                                    );
                                })}
                            </div>
                        </section>
                    );
                })}

                {ungrouped.length > 0 && (
                    <section>
                        <h2 className="mb-3 text-sm font-semibold tracking-wide text-muted-foreground uppercase">
                            Other
                        </h2>
                        <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                            {ungrouped.map((account) => {
                                const balance = parseFloat(
                                    String(
                                        account.current_balance ??
                                            account.initial_balance ??
                                            '0',
                                    ),
                                );
                                const isNegative = balance < 0;

                                return (
                                    <Link
                                        key={account.id}
                                        href={accountShow.url({
                                            ledger: ledger.id,
                                            account: account.id,
                                        })}
                                        className="block"
                                    >
                                        <Card className="py-4">
                                            <CardContent>
                                                <p className="text-base font-semibold">
                                                    {account.name}
                                                </p>
                                                <p
                                                    className={`mt-3 text-xl font-semibold tabular-nums ${
                                                        isNegative
                                                            ? 'text-red-500'
                                                            : 'text-foreground'
                                                    }`}
                                                >
                                                    {formatAmount(balance)}
                                                </p>
                                            </CardContent>
                                        </Card>
                                    </Link>
                                );
                            })}
                        </div>
                    </section>
                )}
            </div>
        </AppLayout>
    );
}
