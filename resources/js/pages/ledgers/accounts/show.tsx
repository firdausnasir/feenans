import { Head, Link, router, usePage } from '@inertiajs/react';
import { useCallback, useState } from 'react';
import {
    Area,
    AreaChart,
    CartesianGrid,
    ResponsiveContainer,
    Tooltip,
    XAxis,
    YAxis,
} from 'recharts';
import { toast } from 'sonner';
import { AddTransactionModal } from '@/components/add-transaction-modal';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Skeleton } from '@/components/ui/skeleton';
import { useApiQuery } from '@/hooks/use-api-query';
import AppLayout from '@/layouts/app-layout';
import { api } from '@/lib/api-client';
import { formatAbsAmount, formatAmount, formatDate } from '@/lib/format';
import type { Account, BreadcrumbItem, Pagination, Transaction } from '@/types';
import { dashboard as ledgerDashboard } from '@/routes/ledgers';
import {
    edit as editRoute,
    index as accountsIndex,
    show as accountShow,
} from '@/routes/ledgers/accounts';
import { index as transactionsIndex } from '@/routes/ledgers/transactions';

type MonthPoint = {
    month: string;
    balance: number;
};

type StatementInfo = {
    statement_start: string | null;
    statement_end: string | null;
    statement_balance: number | null;
    current_start: string | null;
    current_end: string | null;
    current_spending: number | null;
    outstanding: number | null;
    payment_due_date: string | null;
};

type ApiAccount = Omit<Account, 'accountType'> & {
    account_type?: {
        id: number;
        name: string;
        color: string | null;
        is_credit: boolean;
    };
};

function normalizeAccount(apiAccount: ApiAccount): Account {
    const { account_type, ...rest } = apiAccount;

    return {
        ...rest,
        accountType: account_type
            ? {
                  id: account_type.id,
                  ledger_id: rest.ledger_id,
                  name: account_type.name,
                  color: account_type.color,
                  position: 0,
                  is_credit: account_type.is_credit,
              }
            : undefined,
    };
}

function BalanceTrendChart({
    data,
    color,
}: {
    data: MonthPoint[];
    color: string;
}) {
    if (data.length < 2) {
        return null;
    }

    return (
        <ResponsiveContainer width="100%" height={240}>
            <AreaChart
                data={data}
                margin={{ top: 8, right: 8, left: 8, bottom: 0 }}
            >
                <defs>
                    <linearGradient
                        id="balanceGradient"
                        x1="0"
                        y1="0"
                        x2="0"
                        y2="1"
                    >
                        <stop offset="5%" stopColor={color} stopOpacity={0.3} />
                        <stop offset="95%" stopColor={color} stopOpacity={0} />
                    </linearGradient>
                </defs>
                <CartesianGrid
                    strokeDasharray="3 3"
                    className="stroke-border"
                />
                <XAxis
                    dataKey="month"
                    tick={{ fontSize: 12 }}
                    className="fill-muted-foreground"
                    tickLine={false}
                    axisLine={false}
                />
                <YAxis
                    tick={{ fontSize: 12 }}
                    className="fill-muted-foreground"
                    tickLine={false}
                    axisLine={false}
                    tickFormatter={(value: number) =>
                        value >= 1000 || value <= -1000
                            ? `${(value / 1000).toFixed(0)}k`
                            : value.toFixed(0)
                    }
                    width={48}
                />
                <Tooltip
                    formatter={(value: number) => [
                        formatAmount(value),
                        'Balance',
                    ]}
                    contentStyle={{
                        borderRadius: '8px',
                        border: '1px solid var(--border)',
                        backgroundColor: 'var(--popover)',
                        color: 'var(--popover-foreground)',
                        fontSize: '13px',
                    }}
                />
                <Area
                    type="monotone"
                    dataKey="balance"
                    stroke={color}
                    strokeWidth={2}
                    fill="url(#balanceGradient)"
                />
            </AreaChart>
        </ResponsiveContainer>
    );
}

function statementAmountColor(value: number): string {
    if (value === 0) {
        return 'text-foreground';
    }

    if (value > 0) {
        return 'text-green-600';
    }

    return 'text-red-500';
}

export default function AccountShow({ accountId }: { accountId: number }) {
    const { currentLedger: ledger } = usePage().props;
    const base = `/api/v1/ledgers/${ledger!.id}`;

    const [showDeleteDialog, setShowDeleteDialog] = useState(false);
    const [deleting, setDeleting] = useState(false);

    const {
        data: accountResponse,
        loading: accountLoading,
        refetch: refetchAccount,
    } = useApiQuery<{ data: ApiAccount }>(`${base}/accounts/${accountId}`);

    const {
        data: transactionsResponse,
        loading: transactionsLoading,
        refetch: refetchTransactions,
    } = useApiQuery<Pagination<Transaction>>(
        `${base}/accounts/${accountId}/transactions`,
        { params: { per_page: 20 } },
    );

    const {
        data: statementResponse,
        loading: statementLoading,
        refetch: refetchStatement,
    } = useApiQuery<{ data: StatementInfo }>(
        `${base}/accounts/${accountId}/statement`,
    );

    const {
        data: monthlyResponse,
        loading: monthlyLoading,
        refetch: refetchMonthly,
    } = useApiQuery<{ data: MonthPoint[] }>(
        `${base}/accounts/${accountId}/monthly-balances`,
    );

    const refetchAll = useCallback(() => {
        refetchAccount();
        refetchTransactions();
        refetchStatement();
        refetchMonthly();
    }, [refetchAccount, refetchTransactions, refetchStatement, refetchMonthly]);

    const account = accountResponse
        ? normalizeAccount(accountResponse.data)
        : null;
    const transactions = transactionsResponse ?? null;
    const statementInfo = statementResponse?.data ?? null;
    const monthlyBalances = monthlyResponse?.data ?? [];

    const balance = account
        ? parseFloat(String(account.current_balance ?? '0'))
        : 0;
    const initialBalance = account ? parseFloat(account.initial_balance) : 0;

    const hasStatement =
        statementInfo !== null && statementInfo.statement_start !== null;

    const breadcrumbs: BreadcrumbItem[] = account
        ? [
              {
                  title: ledger!.name,
                  href: ledgerDashboard.url(ledger!.id),
              },
              {
                  title: 'Accounts',
                  href: accountsIndex.url(ledger!.id),
              },
              {
                  title: account.name,
                  href: accountShow.url({
                      ledger: ledger!.id,
                      account: account.id,
                  }),
              },
          ]
        : [
              {
                  title: ledger!.name,
                  href: ledgerDashboard.url(ledger!.id),
              },
              {
                  title: 'Accounts',
                  href: accountsIndex.url(ledger!.id),
              },
          ];

    function handleDelete() {
        setDeleting(true);
        api.delete(`${base}/accounts/${accountId}`)
            .then(() => {
                toast.success('Account deleted');
                router.visit(accountsIndex.url(ledger!.id));
            })
            .catch(() => {
                setDeleting(false);
                toast.error('Failed to delete account');
            });
        setShowDeleteDialog(false);
    }

    function viewAllTransactionsUrl(): string {
        const txBase = transactionsIndex.url(ledger!.id);
        const params = new URLSearchParams();
        params.append('account_ids[]', String(accountId));

        return `${txBase}?${params.toString()}`;
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={account?.name ?? 'Account'} />

            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6 lg:p-8">
                {/* Header */}
                {accountLoading || !account ? (
                    <div className="flex items-start justify-between gap-4">
                        <div>
                            <Skeleton className="mb-2 h-7 w-48" />
                            <Skeleton className="h-4 w-24" />
                        </div>
                        <div className="flex gap-2">
                            <Skeleton className="h-8 w-20" />
                            <Skeleton className="h-8 w-20" />
                            <Skeleton className="h-8 w-16" />
                            <Skeleton className="h-8 w-16" />
                        </div>
                    </div>
                ) : (
                    <div className="flex items-start justify-between gap-4">
                        <div>
                            <div className="flex items-center gap-2">
                                {account.accountType?.color && (
                                    <span
                                        className="size-3 rounded-full"
                                        style={{
                                            backgroundColor:
                                                account.accountType.color,
                                        }}
                                    />
                                )}
                                <h1 className="text-2xl font-semibold tracking-tight">
                                    {account.name}
                                </h1>
                            </div>
                            {account.accountType && (
                                <p className="mt-1 text-sm text-muted-foreground">
                                    {account.accountType.name}
                                </p>
                            )}
                        </div>

                        <div className="flex shrink-0 items-center gap-2">
                            <AddTransactionModal
                                ledger={{
                                    id: ledger!.id,
                                    name: ledger!.name,
                                    currency_code: ledger!.currency_code,
                                    cycle_start_day: ledger!.cycle_start_day,
                                    uses_seeded_categories: false,
                                }}
                                defaultAccountId={account.id}
                                onModalClosed={refetchAll}
                            />
                            <Button variant="outline" size="sm" asChild>
                                <a
                                    href={`/ledgers/${ledger!.id}/accounts/${account.id}/export`}
                                >
                                    Export CSV
                                </a>
                            </Button>
                            <Button variant="outline" size="sm" asChild>
                                <Link
                                    href={editRoute.url({
                                        ledger: ledger!.id,
                                        account: account.id,
                                    })}
                                >
                                    Edit
                                </Link>
                            </Button>

                            <Dialog
                                open={showDeleteDialog}
                                onOpenChange={setShowDeleteDialog}
                            >
                                <DialogTrigger asChild>
                                    <Button variant="destructive" size="sm">
                                        Delete
                                    </Button>
                                </DialogTrigger>
                                <DialogContent>
                                    <DialogHeader>
                                        <DialogTitle>
                                            Delete account
                                        </DialogTitle>
                                        <DialogDescription>
                                            This will permanently delete{' '}
                                            <strong>{account.name}</strong> and
                                            all of its transactions. This action
                                            cannot be undone.
                                        </DialogDescription>
                                    </DialogHeader>
                                    <DialogFooter>
                                        <Button
                                            variant="outline"
                                            onClick={() =>
                                                setShowDeleteDialog(false)
                                            }
                                        >
                                            Cancel
                                        </Button>
                                        <Button
                                            variant="destructive"
                                            onClick={handleDelete}
                                            disabled={deleting}
                                        >
                                            Delete account
                                        </Button>
                                    </DialogFooter>
                                </DialogContent>
                            </Dialog>
                        </div>
                    </div>
                )}

                {/* Info cards */}
                {accountLoading || !account ? (
                    <div className="grid gap-4 sm:grid-cols-3">
                        {[1, 2, 3].map((i) => (
                            <Card key={i} className="py-4">
                                <CardContent>
                                    <Skeleton className="mb-2 h-3 w-24" />
                                    <Skeleton className="h-8 w-28" />
                                </CardContent>
                            </Card>
                        ))}
                    </div>
                ) : (
                    <div className="grid gap-4 sm:grid-cols-3">
                        <Card className="py-4">
                            <CardContent>
                                <p className="text-xs tracking-wide text-muted-foreground uppercase">
                                    Current balance
                                </p>
                                <p
                                    className={`mt-1 text-2xl font-semibold tabular-nums ${
                                        balance > 0
                                            ? 'text-green-600'
                                            : balance < 0
                                              ? 'text-red-500'
                                              : 'text-foreground'
                                    }`}
                                >
                                    {formatAbsAmount(balance)}
                                </p>
                            </CardContent>
                        </Card>

                        <Card className="py-4">
                            <CardContent>
                                <p className="text-xs tracking-wide text-muted-foreground uppercase">
                                    Initial balance
                                </p>
                                <p className="mt-1 text-2xl font-semibold tabular-nums">
                                    {formatAmount(initialBalance)}
                                </p>
                            </CardContent>
                        </Card>

                        {account.statement_day !== null && (
                            <Card className="py-4">
                                <CardContent>
                                    <p className="text-xs tracking-wide text-muted-foreground uppercase">
                                        Statement day
                                    </p>
                                    <p className="mt-1 text-2xl font-semibold tabular-nums">
                                        Day {account.statement_day}
                                    </p>
                                </CardContent>
                            </Card>
                        )}
                    </div>
                )}

                {/* Statement info for credit accounts */}
                {statementLoading && (
                    <Card className="py-4">
                        <CardContent className="space-y-4">
                            <Skeleton className="h-16 w-full" />
                            <Skeleton className="h-12 w-full" />
                            <Skeleton className="h-12 w-full" />
                        </CardContent>
                    </Card>
                )}
                {!statementLoading && hasStatement && statementInfo && (
                    <Card className="py-4">
                        <CardContent className="space-y-4">
                            {/* Statement balance (previous closed cycle) */}
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-xs tracking-wide text-muted-foreground uppercase">
                                        Statement balance
                                    </p>
                                    <p className="mt-1 text-sm text-muted-foreground">
                                        {formatDate(
                                            statementInfo.statement_start!,
                                        )}{' '}
                                        &ndash;{' '}
                                        {formatDate(
                                            statementInfo.statement_end!,
                                        )}
                                    </p>
                                </div>
                                <div className="text-right">
                                    <p
                                        className={`text-xl font-semibold tabular-nums ${statementAmountColor(-(statementInfo.statement_balance ?? 0))}`}
                                    >
                                        {formatAbsAmount(
                                            statementInfo.statement_balance ??
                                                0,
                                        )}
                                    </p>
                                    <p className="text-xs text-muted-foreground">
                                        Due{' '}
                                        {formatDate(
                                            statementInfo.payment_due_date!,
                                        )}
                                    </p>
                                </div>
                            </div>

                            <div className="border-t" />

                            {/* Current cycle spending */}
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-xs tracking-wide text-muted-foreground uppercase">
                                        Current spending
                                    </p>
                                    <p className="mt-1 text-sm text-muted-foreground">
                                        {formatDate(
                                            statementInfo.current_start!,
                                        )}{' '}
                                        &ndash;{' '}
                                        {formatDate(statementInfo.current_end!)}
                                    </p>
                                </div>
                                <p
                                    className={`text-lg font-semibold tabular-nums ${statementAmountColor(-(statementInfo.current_spending ?? 0))}`}
                                >
                                    {formatAbsAmount(
                                        statementInfo.current_spending ?? 0,
                                    )}
                                </p>
                            </div>

                            <div className="border-t" />

                            {/* Total outstanding */}
                            <div className="flex items-center justify-between">
                                <p className="text-xs tracking-wide text-muted-foreground uppercase">
                                    Total outstanding
                                </p>
                                <p
                                    className={`text-lg font-bold tabular-nums ${statementAmountColor(-(statementInfo.outstanding ?? 0))}`}
                                >
                                    {formatAbsAmount(
                                        statementInfo.outstanding ?? 0,
                                    )}
                                </p>
                            </div>
                        </CardContent>
                    </Card>
                )}

                {/* Balance trend chart */}
                {monthlyLoading && (
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-sm">
                                Balance trend (last 6 months)
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <Skeleton className="h-60 w-full" />
                        </CardContent>
                    </Card>
                )}
                {!monthlyLoading && monthlyBalances.length >= 2 && account && (
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-sm">
                                Balance trend (last 6 months)
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="min-w-0 overflow-hidden">
                            <BalanceTrendChart
                                data={monthlyBalances}
                                color={account.color ?? '#6B7280'}
                            />
                        </CardContent>
                    </Card>
                )}

                {/* Transaction list */}
                {transactionsLoading || !transactions ? (
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-sm">
                                Recent transactions
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            {[1, 2, 3, 4, 5].map((i) => (
                                <div
                                    key={i}
                                    className="flex items-center justify-between"
                                >
                                    <div>
                                        <Skeleton className="mb-1 h-4 w-40" />
                                        <Skeleton className="h-3 w-24" />
                                    </div>
                                    <Skeleton className="h-4 w-16" />
                                </div>
                            ))}
                        </CardContent>
                    </Card>
                ) : (
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between">
                            <CardTitle className="text-sm">
                                Recent transactions
                            </CardTitle>
                            <span className="text-xs text-muted-foreground">
                                {transactions.total} total
                            </span>
                        </CardHeader>
                        <CardContent>
                            {transactions.data.length === 0 ? (
                                <div className="flex flex-col items-center gap-2 py-8 text-center">
                                    <p className="text-sm font-medium">
                                        No transactions yet
                                    </p>
                                    <p className="text-xs text-muted-foreground">
                                        Transactions for this account will
                                        appear here.
                                    </p>
                                </div>
                            ) : (
                                <>
                                    <ul className="divide-y divide-border">
                                        {transactions.data.map((t) => {
                                            const amount = parseFloat(t.amount);
                                            const isExpense = amount < 0;

                                            return (
                                                <li
                                                    key={t.id}
                                                    className="flex items-center justify-between gap-4 py-3"
                                                >
                                                    <div className="min-w-0">
                                                        <p className="truncate text-sm font-medium">
                                                            {t.description ??
                                                                t.payee?.name ??
                                                                'No description'}
                                                        </p>
                                                        <div className="mt-0.5 flex items-center gap-2 text-xs text-muted-foreground">
                                                            <span>
                                                                {formatDate(
                                                                    t.transaction_date,
                                                                )}
                                                            </span>
                                                            {t.category && (
                                                                <>
                                                                    <span>
                                                                        ·
                                                                    </span>
                                                                    <span>
                                                                        {
                                                                            t
                                                                                .category
                                                                                .name
                                                                        }
                                                                    </span>
                                                                </>
                                                            )}
                                                        </div>
                                                    </div>
                                                    <p
                                                        className={`shrink-0 text-sm font-semibold tabular-nums ${
                                                            isExpense
                                                                ? 'text-red-500'
                                                                : 'text-green-600'
                                                        }`}
                                                    >
                                                        {isExpense ? '-' : '+'}
                                                        {formatAbsAmount(
                                                            amount,
                                                        )}
                                                    </p>
                                                </li>
                                            );
                                        })}
                                    </ul>

                                    {transactions.total >
                                        transactions.data.length && (
                                        <div className="mt-4 flex justify-center border-t border-border pt-3">
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                asChild
                                            >
                                                <Link
                                                    href={viewAllTransactionsUrl()}
                                                >
                                                    View all{' '}
                                                    {transactions.total}{' '}
                                                    transactions
                                                </Link>
                                            </Button>
                                        </div>
                                    )}
                                </>
                            )}
                        </CardContent>
                    </Card>
                )}
            </div>
        </AppLayout>
    );
}
