import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
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
import AppLayout from '@/layouts/app-layout';
import { formatAbsAmount, formatAmount, formatDate } from '@/lib/format';
import { dashboard as ledgerDashboard } from '@/routes/ledgers';
import {
    destroy,
    edit as editRoute,
    index as accountsIndex,
    show as accountShow,
} from '@/routes/ledgers/accounts';
import type {
    Account,
    BreadcrumbItem,
    Ledger,
    Pagination,
    Transaction,
} from '@/types';

type MonthPoint = {
    month: string;
    balance: number;
};

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
                    formatter={(value: any) => [formatAmount(value), 'Balance']}
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

type StatementInfo = {
    statement_start: string;
    statement_end: string;
    statement_balance: number;
    current_start: string;
    current_end: string;
    current_spending: number;
    outstanding: number;
    payment_due_date: string;
};

export default function AccountShow({
    ledger,
    account,
    transactions,
    balance,
    monthlyBalances,
    statementInfo,
}: {
    ledger: Ledger;
    account: Account;
    transactions: Pagination<Transaction>;
    balance: number;
    monthlyBalances: MonthPoint[];
    statementInfo: StatementInfo | null;
}) {
    const [showDeleteDialog, setShowDeleteDialog] = useState(false);

    const breadcrumbs: BreadcrumbItem[] = [
        { title: ledger.name, href: ledgerDashboard.url(ledger.id) },
        { title: 'Accounts', href: accountsIndex.url(ledger.id) },
        {
            title: account.name,
            href: accountShow.url({ ledger: ledger.id, account: account.id }),
        },
    ];

    const isNegativeBalance = balance < 0;
    const initialBalance = parseFloat(account.initial_balance);

    function handleDelete() {
        router.delete(destroy.url({ ledger: ledger.id, account: account.id }), {
            onSuccess: () => {
                toast.success('Account deleted');
            },
        });
        setShowDeleteDialog(false);
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={account.name} />

            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6 lg:p-8">
                {/* Header */}
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
                        <Button variant="outline" size="sm" asChild>
                            <a
                                href={`/ledgers/${ledger.id}/accounts/${account.id}/export`}
                            >
                                Export CSV
                            </a>
                        </Button>
                        <Button variant="outline" size="sm" asChild>
                            <Link
                                href={editRoute.url({
                                    ledger: ledger.id,
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
                                    <DialogTitle>Delete account</DialogTitle>
                                    <DialogDescription>
                                        This will permanently delete{' '}
                                        <strong>{account.name}</strong> and all
                                        of its transactions. This action cannot
                                        be undone.
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
                                    >
                                        Delete account
                                    </Button>
                                </DialogFooter>
                            </DialogContent>
                        </Dialog>
                    </div>
                </div>

                {/* Info cards */}
                <div className="grid gap-4 sm:grid-cols-3">
                    <Card className="py-4">
                        <CardContent>
                            <p className="text-xs tracking-wide text-muted-foreground uppercase">
                                Current balance
                            </p>
                            <p
                                className={`mt-1 text-2xl font-semibold tabular-nums ${
                                    isNegativeBalance
                                        ? 'text-red-500'
                                        : 'text-foreground'
                                }`}
                            >
                                {formatAmount(balance)}
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

                {/* Statement info for credit accounts */}
                {statementInfo && (
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
                                            statementInfo.statement_start,
                                        )}{' '}
                                        &ndash;{' '}
                                        {formatDate(
                                            statementInfo.statement_end,
                                        )}
                                    </p>
                                </div>
                                <div className="text-right">
                                    <p className="text-xl font-semibold text-red-500 tabular-nums">
                                        {formatAmount(
                                            statementInfo.statement_balance,
                                        )}
                                    </p>
                                    <p className="text-xs text-muted-foreground">
                                        Due{' '}
                                        {formatDate(
                                            statementInfo.payment_due_date,
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
                                            statementInfo.current_start,
                                        )}{' '}
                                        &ndash;{' '}
                                        {formatDate(statementInfo.current_end)}
                                    </p>
                                </div>
                                <p className="text-lg font-semibold tabular-nums">
                                    {formatAmount(
                                        statementInfo.current_spending,
                                    )}
                                </p>
                            </div>

                            <div className="border-t" />

                            {/* Total outstanding */}
                            <div className="flex items-center justify-between">
                                <p className="text-xs tracking-wide text-muted-foreground uppercase">
                                    Total outstanding
                                </p>
                                <p className="text-lg font-bold text-red-500 tabular-nums">
                                    {formatAmount(statementInfo.outstanding)}
                                </p>
                            </div>
                        </CardContent>
                    </Card>
                )}

                {/* Balance trend chart */}
                {monthlyBalances.length >= 2 && (
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
                <Card>
                    <CardHeader className="flex flex-row items-center justify-between">
                        <CardTitle className="text-sm">Transactions</CardTitle>
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
                                    Transactions for this account will appear
                                    here.
                                </p>
                            </div>
                        ) : (
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
                                                            <span>·</span>
                                                            <span>
                                                                {
                                                                    t.category
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
                                                {formatAbsAmount(amount)}
                                            </p>
                                        </li>
                                    );
                                })}
                            </ul>
                        )}

                        {/* Pagination */}
                        {transactions.last_page > 1 && (
                            <div className="mt-4 flex items-center justify-between border-t border-border pt-3">
                                <span className="text-xs text-muted-foreground">
                                    Page {transactions.current_page} of{' '}
                                    {transactions.last_page}
                                </span>
                                <div className="flex gap-2">
                                    {transactions.prev_page_url && (
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            asChild
                                        >
                                            <Link
                                                href={
                                                    transactions.prev_page_url
                                                }
                                                preserveState
                                                preserveScroll
                                            >
                                                Previous
                                            </Link>
                                        </Button>
                                    )}
                                    {transactions.next_page_url && (
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            asChild
                                        >
                                            <Link
                                                href={
                                                    transactions.next_page_url
                                                }
                                                preserveState
                                                preserveScroll
                                            >
                                                Next
                                            </Link>
                                        </Button>
                                    )}
                                </div>
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
