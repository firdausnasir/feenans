import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import {
    AlertTriangle,
    Bell,
    Calendar,
    ChevronLeft,
    ChevronRight,
    CreditCard,
    Pencil,
    TrendingDown,
    TrendingUp,
    Wallet,
} from 'lucide-react';
import {
    Area,
    AreaChart,
    Bar,
    BarChart,
    CartesianGrid,
    XAxis,
    YAxis,
} from 'recharts';

import { AddTransactionModal } from '@/components/add-transaction-modal';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import {
    ChartContainer,
    ChartTooltip,
    ChartTooltipContent,
    type ChartConfig,
} from '@/components/ui/chart';
import AppLayout from '@/layouts/app-layout';
import { formatAbsAmount, formatAmount, formatDate } from '@/lib/format';
import { dashboard } from '@/routes/ledgers';
import { show as accountShow } from '@/routes/ledgers/accounts';
import { pay } from '@/routes/ledgers/bills';
import { index as reportsIndex } from '@/routes/ledgers/reports';
import {
    edit as transactionEdit,
    index as transactionsIndex,
} from '@/routes/ledgers/transactions';
import type {
    Account,
    AccountType,
    Bill,
    BreadcrumbItem,
    Category,
    Ledger,
    Payee,
    Tag,
    Transaction,
} from '@/types';

type Summary = { income: number; expense: number; net: number };
type AccountGroup = {
    type: AccountType;
    accounts: (Account & { balance: number })[];
};
type UpcomingBills = { upcoming: Bill[]; due: Bill[]; missed: Bill[] };
type DailyTrend = { date: string; expense: number; income: number };
type TopCategory = { name: string; color: string | null; total: number };
type CycleDates = { start: string; end: string };

const CHART_COLORS = [
    'var(--color-chart-1)',
    'var(--color-chart-2)',
    'var(--color-chart-3)',
    'var(--color-chart-4)',
    'var(--color-chart-5)',
];

const expenseChartConfig: ChartConfig = {
    expense: { label: 'Expense', color: 'var(--color-chart-1)' },
    income: { label: 'Income', color: 'var(--color-chart-3)' },
};

function formatChartDate(dateStr: string): string {
    const d = new Date(dateStr + 'T00:00:00');
    return d.toLocaleDateString('en-MY', { month: 'short', day: 'numeric' });
}

export default function LedgerDashboard({
    ledger,
    summary,
    accounts,
    flatAccounts,
    upcomingBills,
    recentTransactions,
    categories,
    payees,
    tags,
    dailyExpenseTrend,
    cycleDates,
    cycleOffset,
    topCategories,
}: {
    ledger: Ledger;
    summary: Summary;
    accounts: AccountGroup[];
    flatAccounts: Account[];
    upcomingBills: UpcomingBills;
    recentTransactions: Transaction[];
    categories: Category[];
    payees: Payee[];
    tags: Tag[];
    dailyExpenseTrend: DailyTrend[];
    cycleDates: CycleDates;
    cycleOffset: number;
    topCategories: TopCategory[];
}) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: ledger.name, href: dashboard.url(ledger.id) },
    ];

    const hasAnyBills =
        upcomingBills.due.length > 0 ||
        upcomingBills.upcoming.length > 0 ||
        upcomingBills.missed.length > 0;

    const hasUrgentBills =
        upcomingBills.missed.length > 0 || upcomingBills.due.length > 0;

    function handlePayBill(bill: Bill, amount?: string) {
        router.post(
            pay.url({ ledger: ledger.id, bill: bill.id }),
            amount ? { amount } : {},
            { preserveScroll: true, onSuccess: () => {} },
        );
    }

    function navigateCycle(offset: number) {
        const url = dashboard.url(ledger.id);
        const query = offset === 0 ? {} : { cycle_offset: offset };
        router.get(url, query, { preserveState: true });
    }

    const categoryChartConfig: ChartConfig = Object.fromEntries(
        topCategories.map((cat, index) => [
            cat.name,
            {
                label: cat.name,
                color: cat.color ?? CHART_COLORS[index % CHART_COLORS.length],
            },
        ]),
    );

    const categoryChartData = topCategories.map((cat, index) => ({
        name: cat.name,
        total: cat.total,
        fill: cat.color ?? CHART_COLORS[index % CHART_COLORS.length],
    }));

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={ledger.name} />

            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">
                            {ledger.name}
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Track balances, spending, and recent activity in one
                            place.
                        </p>
                    </div>
                    <AddTransactionModal
                        ledger={ledger}
                        accounts={flatAccounts}
                        categories={categories}
                        payees={payees}
                        tags={tags}
                    />
                </div>

                {/* Summary Cards - always 3 columns */}
                <div>
                    <div className="grid grid-cols-3 gap-3">
                        <Link
                            href={transactionsIndex.url(ledger.id, {
                                query: {
                                    type: 'income',
                                    date_from: cycleDates.start,
                                    date_to: cycleDates.end,
                                },
                            })}
                            className="block"
                        >
                            <SummaryCard
                                label="Income"
                                value={summary.income}
                                icon={
                                    <TrendingUp className="size-4 text-green-600 dark:text-green-400" />
                                }
                                colorClass="text-green-600 dark:text-green-400"
                            />
                        </Link>
                        <Link
                            href={transactionsIndex.url(ledger.id, {
                                query: {
                                    type: 'expense',
                                    date_from: cycleDates.start,
                                    date_to: cycleDates.end,
                                },
                            })}
                            className="block"
                        >
                            <SummaryCard
                                label="Expense"
                                value={summary.expense}
                                icon={
                                    <TrendingDown className="size-4 text-red-600 dark:text-red-400" />
                                }
                                colorClass="text-red-600 dark:text-red-400"
                            />
                        </Link>
                        <Link
                            href={reportsIndex.url(ledger.id)}
                            className="block"
                        >
                            <SummaryCard
                                label="Net"
                                value={summary.net}
                                icon={
                                    <Wallet
                                        className={`size-4 ${summary.net >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'}`}
                                    />
                                }
                                colorClass={
                                    summary.net >= 0
                                        ? 'text-green-600 dark:text-green-400'
                                        : 'text-red-600 dark:text-red-400'
                                }
                            />
                        </Link>
                    </div>
                    <div className="mt-2 flex items-center gap-2">
                        <div className="flex items-center gap-1">
                            <Button
                                variant="ghost"
                                size="icon"
                                className="size-6"
                                onClick={() => navigateCycle(cycleOffset - 1)}
                            >
                                <ChevronLeft className="size-4" />
                            </Button>
                            <p className="text-xs text-muted-foreground">
                                <Calendar className="mr-1 inline size-3" />
                                Cycle: {formatDate(
                                    cycleDates.start,
                                )} &ndash; {formatDate(cycleDates.end)}
                            </p>
                            <Button
                                variant="ghost"
                                size="icon"
                                className="size-6"
                                onClick={() => navigateCycle(cycleOffset + 1)}
                            >
                                <ChevronRight className="size-4" />
                            </Button>
                        </div>
                        {cycleOffset !== 0 && (
                            <Button
                                variant="outline"
                                size="sm"
                                className="h-6 text-xs"
                                onClick={() => navigateCycle(0)}
                            >
                                Current Cycle
                            </Button>
                        )}
                    </div>
                </div>

                {/* Bills + Expense Trend */}
                <div className="grid gap-6 lg:auto-rows-fr lg:grid-cols-2">
                    {/* Upcoming Bills */}
                    <Card className="lg:h-[32rem] lg:min-h-0">
                        <CardHeader>
                            <div className="flex items-center gap-2">
                                <Bell className="size-4 text-muted-foreground" />
                                <CardTitle>Upcoming Bills</CardTitle>
                            </div>
                            {hasUrgentBills && (
                                <div className="mt-2 flex items-center gap-2 rounded-md bg-destructive/10 px-3 py-2 text-sm text-destructive">
                                    <AlertTriangle className="size-4 shrink-0" />
                                    <span>
                                        {upcomingBills.missed.length > 0 &&
                                            `${upcomingBills.missed.length} missed`}
                                        {upcomingBills.missed.length > 0 &&
                                            upcomingBills.due.length > 0 &&
                                            ', '}
                                        {upcomingBills.due.length > 0 &&
                                            `${upcomingBills.due.length} due today`}
                                    </span>
                                </div>
                            )}
                        </CardHeader>
                        <CardContent className="flex-1 lg:min-h-0">
                            <div className="space-y-4 lg:h-full lg:overflow-y-auto lg:pr-1">
                                {!hasAnyBills && (
                                    <p className="text-sm text-muted-foreground">
                                        No upcoming bills.
                                    </p>
                                )}

                                {upcomingBills.missed.length > 0 && (
                                    <BillSection
                                        label="Missed"
                                        variant="destructive"
                                        bills={upcomingBills.missed}
                                        onPay={handlePayBill}
                                    />
                                )}

                                {upcomingBills.due.length > 0 && (
                                    <BillSection
                                        label="Due Today"
                                        variant="secondary"
                                        bills={upcomingBills.due}
                                        onPay={handlePayBill}
                                    />
                                )}

                                {upcomingBills.upcoming.length > 0 && (
                                    <BillSection
                                        label="Upcoming"
                                        variant="outline"
                                        bills={upcomingBills.upcoming}
                                        onPay={handlePayBill}
                                    />
                                )}
                            </div>
                        </CardContent>
                    </Card>

                    {/* Expense & Income Trend */}
                    <Card className="lg:h-[32rem] lg:min-h-0">
                        <CardHeader>
                            <CardTitle>Expense & Income Trend</CardTitle>
                            <CardDescription>
                                Daily expenses and income this cycle
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="flex-1 lg:min-h-0">
                            {dailyExpenseTrend.length === 0 ? (
                                <p className="text-sm text-muted-foreground">
                                    No expense data this cycle.
                                </p>
                            ) : (
                                <div className="lg:h-full lg:overflow-y-auto lg:pr-1">
                                    <ChartContainer
                                        config={expenseChartConfig}
                                        className="h-[220px] w-full lg:h-full lg:min-h-[18rem]"
                                    >
                                        <AreaChart
                                            data={dailyExpenseTrend}
                                            accessibilityLayer
                                        >
                                            <CartesianGrid
                                                strokeDasharray="3 3"
                                                vertical={false}
                                            />
                                            <XAxis
                                                dataKey="date"
                                                tickLine={false}
                                                axisLine={false}
                                                tickFormatter={formatChartDate}
                                                fontSize={12}
                                            />
                                            <YAxis
                                                tickLine={false}
                                                axisLine={false}
                                                fontSize={12}
                                                width={60}
                                            />
                                            <ChartTooltip
                                                content={
                                                    <ChartTooltipContent />
                                                }
                                            />
                                            <defs>
                                                <linearGradient
                                                    id="expenseGradient"
                                                    x1="0"
                                                    y1="0"
                                                    x2="0"
                                                    y2="1"
                                                >
                                                    <stop
                                                        offset="5%"
                                                        stopColor="var(--color-chart-1)"
                                                        stopOpacity={0.3}
                                                    />
                                                    <stop
                                                        offset="95%"
                                                        stopColor="var(--color-chart-1)"
                                                        stopOpacity={0}
                                                    />
                                                </linearGradient>
                                                <linearGradient
                                                    id="incomeGradient"
                                                    x1="0"
                                                    y1="0"
                                                    x2="0"
                                                    y2="1"
                                                >
                                                    <stop
                                                        offset="5%"
                                                        stopColor="var(--color-chart-3)"
                                                        stopOpacity={0.3}
                                                    />
                                                    <stop
                                                        offset="95%"
                                                        stopColor="var(--color-chart-3)"
                                                        stopOpacity={0}
                                                    />
                                                </linearGradient>
                                            </defs>
                                            <Area
                                                dataKey="income"
                                                type="monotone"
                                                stroke="var(--color-chart-3)"
                                                fill="url(#incomeGradient)"
                                                strokeWidth={2}
                                            />
                                            <Area
                                                dataKey="expense"
                                                type="monotone"
                                                stroke="var(--color-chart-1)"
                                                fill="url(#expenseGradient)"
                                                strokeWidth={2}
                                            />
                                        </AreaChart>
                                    </ChartContainer>
                                </div>
                            )}
                        </CardContent>
                    </Card>
                </div>

                {/* Accounts + Top Categories */}
                <div className="grid gap-6 lg:auto-rows-fr lg:grid-cols-2">
                    {/* Accounts */}
                    <Card className="lg:h-[28rem] lg:min-h-0">
                        <CardHeader>
                            <div className="flex items-center gap-2">
                                <CreditCard className="size-4 text-muted-foreground" />
                                <CardTitle>Accounts</CardTitle>
                            </div>
                        </CardHeader>
                        <CardContent className="flex-1 lg:min-h-0">
                            <div className="space-y-5 lg:h-full lg:overflow-y-auto lg:pr-1">
                                {accounts.map((group) => (
                                    <div key={group.type.id}>
                                        <div className="mb-2 flex items-center gap-2">
                                            {group.type.color && (
                                                <span
                                                    className="inline-block size-2.5 rounded-full"
                                                    style={{
                                                        backgroundColor:
                                                            group.type.color,
                                                    }}
                                                />
                                            )}
                                            <span className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                                                {group.type.name}
                                            </span>
                                        </div>
                                        <div className="space-y-1">
                                            {group.accounts.map((account) => (
                                                <Link
                                                    key={account.id}
                                                    href={accountShow.url({
                                                        ledger: ledger.id,
                                                        account: account.id,
                                                    })}
                                                    className="flex items-center justify-between rounded-lg px-3 py-2.5 transition-colors hover:bg-muted/50"
                                                >
                                                    <span className="text-sm">
                                                        {account.name}
                                                    </span>
                                                    <span className="text-sm font-medium">
                                                        {formatAmount(
                                                            account.balance,
                                                        )}
                                                    </span>
                                                </Link>
                                            ))}
                                        </div>
                                    </div>
                                ))}
                                {accounts.length === 0 && (
                                    <p className="text-sm text-muted-foreground">
                                        No accounts yet.
                                    </p>
                                )}
                            </div>
                        </CardContent>
                    </Card>

                    {/* Top Expense Categories */}
                    <Card className="lg:h-[28rem] lg:min-h-0">
                        <CardHeader>
                            <CardTitle>Top Expense Categories</CardTitle>
                            <CardDescription>
                                Highest spending this cycle
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="flex-1 lg:min-h-0">
                            {topCategories.length === 0 ? (
                                <p className="text-sm text-muted-foreground">
                                    No expenses this cycle.
                                </p>
                            ) : (
                                <div className="lg:h-full lg:overflow-y-auto lg:pr-1">
                                    <ChartContainer
                                        config={categoryChartConfig}
                                        className="h-[220px] w-full lg:h-full lg:min-h-[18rem]"
                                    >
                                        <BarChart
                                            data={categoryChartData}
                                            layout="vertical"
                                            accessibilityLayer
                                        >
                                            <CartesianGrid
                                                strokeDasharray="3 3"
                                                horizontal={false}
                                            />
                                            <YAxis
                                                dataKey="name"
                                                type="category"
                                                tickLine={false}
                                                axisLine={false}
                                                fontSize={12}
                                                width={100}
                                            />
                                            <XAxis
                                                type="number"
                                                tickLine={false}
                                                axisLine={false}
                                                fontSize={12}
                                            />
                                            <ChartTooltip
                                                content={
                                                    <ChartTooltipContent />
                                                }
                                            />
                                            <Bar
                                                dataKey="total"
                                                radius={[0, 4, 4, 0]}
                                            />
                                        </BarChart>
                                    </ChartContainer>
                                </div>
                            )}
                        </CardContent>
                    </Card>
                </div>

                {/* Recent Transactions - full width */}
                <Card>
                    <CardHeader>
                        <CardTitle>Recent Transactions</CardTitle>
                    </CardHeader>
                    <CardContent>
                        {recentTransactions.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                No recent transactions.
                            </p>
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="w-full text-sm">
                                    <thead>
                                        <tr className="border-b text-left text-xs text-muted-foreground">
                                            <th className="pr-4 pb-2 font-medium">
                                                Date
                                            </th>
                                            <th className="pr-4 pb-2 font-medium">
                                                Description
                                            </th>
                                            <th className="hidden pr-4 pb-2 font-medium sm:table-cell">
                                                Account
                                            </th>
                                            <th className="hidden pr-4 pb-2 font-medium md:table-cell">
                                                Category
                                            </th>
                                            <th className="pb-2 text-right font-medium">
                                                Amount
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {recentTransactions.map(
                                            (transaction) => {
                                                const amount = parseFloat(
                                                    transaction.amount,
                                                );
                                                const isTransfer =
                                                    transaction.transaction_type ===
                                                    'transfer';
                                                const amountClass = isTransfer
                                                    ? 'text-blue-600 dark:text-blue-400'
                                                    : amount >= 0
                                                      ? 'text-green-600 dark:text-green-400'
                                                      : 'text-red-600 dark:text-red-400';

                                                return (
                                                    <tr
                                                        key={transaction.id}
                                                        className="cursor-pointer border-b last:border-0 hover:bg-muted/50"
                                                        onClick={() =>
                                                            router.visit(
                                                                transactionEdit.url(
                                                                    {
                                                                        ledger: ledger.id,
                                                                        transaction:
                                                                            transaction.id,
                                                                    },
                                                                ),
                                                            )
                                                        }
                                                    >
                                                        <td className="py-2.5 pr-4 whitespace-nowrap">
                                                            {formatDate(
                                                                transaction.transaction_date,
                                                            )}
                                                        </td>
                                                        <td className="py-2.5 pr-4">
                                                            <div className="flex items-center gap-2">
                                                                <span className="truncate">
                                                                    {transaction.description ??
                                                                        'Transaction'}
                                                                </span>
                                                                <Badge
                                                                    variant="outline"
                                                                    className="hidden text-[10px] lg:inline-flex"
                                                                >
                                                                    {
                                                                        transaction.transaction_type
                                                                    }
                                                                </Badge>
                                                            </div>
                                                        </td>
                                                        <td className="hidden py-2.5 pr-4 sm:table-cell">
                                                            {transaction.account
                                                                ?.name ?? '-'}
                                                        </td>
                                                        <td className="hidden py-2.5 pr-4 md:table-cell">
                                                            {transaction
                                                                .category
                                                                ?.name ?? '-'}
                                                        </td>
                                                        <td
                                                            className={`py-2.5 text-right font-medium whitespace-nowrap ${amountClass}`}
                                                        >
                                                            {formatAbsAmount(
                                                                amount,
                                                            )}
                                                        </td>
                                                    </tr>
                                                );
                                            },
                                        )}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}

function SummaryCard({
    label,
    value,
    icon,
    colorClass,
}: {
    label: string;
    value: number;
    icon: React.ReactNode;
    colorClass: string;
}) {
    return (
        <Card>
            <CardContent className="p-4">
                <div className="flex items-center gap-2">
                    {icon}
                    <span className="text-xs font-medium text-muted-foreground">
                        {label}
                    </span>
                </div>
                <p
                    className={`mt-2 text-lg font-bold sm:text-xl lg:text-2xl ${colorClass}`}
                >
                    {formatAmount(value)}
                </p>
            </CardContent>
        </Card>
    );
}

function BillSection({
    label,
    variant,
    bills,
    onPay,
}: {
    label: string;
    variant: 'destructive' | 'secondary' | 'outline';
    bills: Bill[];
    onPay: (bill: Bill, amount?: string) => void;
}) {
    return (
        <div>
            <div className="mb-2">
                <Badge variant={variant}>{label}</Badge>
            </div>
            <div className="space-y-1">
                {bills.map((bill) => (
                    <BillRow key={bill.id} bill={bill} onPay={onPay} />
                ))}
            </div>
        </div>
    );
}

function BillRow({
    bill,
    onPay,
}: {
    bill: Bill;
    onPay: (bill: Bill, amount?: string) => void;
}) {
    const [isEditingAmount, setIsEditingAmount] = useState(false);
    const [amount, setAmount] = useState(String(bill.amount));

    return (
        <div className="flex items-center justify-between rounded-lg px-3 py-2.5 transition-colors hover:bg-muted/50">
            <div className="min-w-0 flex-1">
                <p className="truncate text-sm font-medium">{bill.name}</p>
                <div className="mt-1 flex flex-wrap items-center gap-2 text-xs text-muted-foreground">
                    {isEditingAmount ? (
                        <Input
                            type="number"
                            step="0.01"
                            value={amount}
                            onChange={(
                                event: React.ChangeEvent<HTMLInputElement>,
                            ) => setAmount(event.target.value)}
                            className="h-8 w-28"
                        />
                    ) : (
                        <span>{formatAmount(bill.amount)}</span>
                    )}
                    <span>&middot;</span>
                    <span>{formatDate(bill.next_due_date)}</span>
                </div>
            </div>
            <div className="ml-3 flex shrink-0 items-center gap-2">
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    className="size-8"
                    onClick={() => setIsEditingAmount((current) => !current)}
                >
                    <Pencil className="size-4" />
                </Button>
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    onClick={() =>
                        onPay(bill, isEditingAmount ? amount : undefined)
                    }
                    className="shrink-0"
                >
                    Pay
                </Button>
            </div>
        </div>
    );
}
