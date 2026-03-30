import { Deferred, Head, Link, router, usePage } from '@inertiajs/react';
import {
    AlertTriangle,
    Bell,
    Calendar,
    ChevronLeft,
    ChevronRight,
    CreditCard,
    DatabaseZap,
    X,
} from 'lucide-react';
import { useState } from 'react';
import {
    Area,
    AreaChart,
    Bar,
    BarChart,
    CartesianGrid,
    XAxis,
    YAxis,
} from 'recharts';
import { toast } from 'sonner';

import { PayBillDialog } from '@/components/pay-bill-dialog';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import type { ChartConfig } from '@/components/ui/chart';
import {
    ChartContainer,
    ChartTooltip,
    ChartTooltipContent,
} from '@/components/ui/chart';
import { Progress } from '@/components/ui/progress';
import { Skeleton } from '@/components/ui/skeleton';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { usePrivacyMode } from '@/contexts/privacy-mode-context';
import AppLayout from '@/layouts/app-layout';
import { formatAbsAmount, formatDate } from '@/lib/format';
import { dashboard } from '@/routes/ledgers';
import { index as accountsIndex } from '@/routes/ledgers/accounts';
import { index as budgetsIndex } from '@/routes/ledgers/budgets';
import { store as storeSampleData } from '@/routes/ledgers/sample-data';
import {
    edit as transactionEdit,
    index as transactionsIndex,
} from '@/routes/ledgers/transactions';
import type {
    Account,
    AccountType,
    Bill,
    BreadcrumbItem,
    BudgetStat,
    Transaction,
} from '@/types';

type Summary = {
    income: number;
    expense: number;
    net: number;
    prev_income: number;
    prev_expense: number;
};

type AccountGroup = {
    type: Pick<AccountType, 'id' | 'name' | 'color' | 'is_credit'>;
    accounts: Account[];
    total_balance?: string;
};

type UpcomingBills = {
    upcoming: Bill[];
    due: Bill[];
    missed: Bill[];
};

type DailyTrend = { date: string; expense: number; income: number };

type TopCategory = {
    id: number | null;
    name: string;
    color: string | null;
    total: number;
    percentage: number;
};

type CycleResponse = {
    cycle_start: string;
    cycle_end: string;
    prev_cycle_start: string;
    prev_cycle_end: string;
    offset: number;
};

type DashboardProps = {
    cycle: CycleResponse;
    summary: Summary;
    accounts: AccountGroup[];
    dailyTrend?: DailyTrend[];
    topCategories?: TopCategory[];
    recentTransactions?: Transaction[];
    uncategorizedCount?: number;
    upcomingBills?: UpcomingBills;
    topBudgets?: BudgetStat[];
};

const CHART_COLORS = [
    'var(--color-chart-1)',
    'var(--color-chart-2)',
    'var(--color-chart-3)',
    'var(--color-chart-4)',
    'var(--color-chart-5)',
];

const expenseChartConfig: ChartConfig = {
    expense: { label: 'Expense', color: 'oklch(0.637 0.237 25.331)' },
};

function formatChartDate(dateStr: string): string {
    const d = new Date(dateStr + 'T00:00:00');

    return d.toLocaleDateString('en-MY', { month: 'short', day: 'numeric' });
}

function amountColor(value: number): string {
    return value < 0 ? 'text-red-500 dark:text-red-400' : 'text-foreground';
}

function relativeDate(dateStr: string): string {
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    const target = new Date(dateStr + 'T00:00:00');
    const diffMs = target.getTime() - today.getTime();
    const diffDays = Math.round(diffMs / 86400000);

    if (diffDays < -1) {
        return `${Math.abs(diffDays)} days ago`;
    }

    if (diffDays === -1) {
        return 'Yesterday';
    }

    if (diffDays === 0) {
        return 'Today';
    }

    if (diffDays === 1) {
        return 'Tomorrow';
    }

    return `In ${diffDays} days`;
}

export default function LedgerDashboard() {
    const { currentLedger: ledger } = usePage().props;
    const {
        cycle,
        summary,
        accounts,
        dailyTrend,
        topCategories,
        recentTransactions,
        uncategorizedCount,
        upcomingBills,
        topBudgets,
    } = usePage<DashboardProps>().props;

    const breadcrumbs: BreadcrumbItem[] = [
        { title: ledger!.name, href: dashboard.url(ledger!.id) },
        { title: 'Dashboard', href: dashboard.url(ledger!.id) },
    ];

    const [payingBill, setPayingBill] = useState<Bill | null>(null);
    const [uncategorizedDismissed, setUncategorizedDismissed] = useState(false);
    const { privacyMode } = usePrivacyMode();
    const [isLoadingSampleData, setIsLoadingSampleData] = useState(false);

    // Derived values with safe defaults for deferred props
    const accountGroups = accounts ?? [];
    const dailyExpenseTrend = dailyTrend ?? [];
    const topCats = topCategories ?? [];
    const recentTxs = recentTransactions ?? [];
    const uncatCount = uncategorizedCount ?? 0;
    const bills: UpcomingBills = {
        upcoming: upcomingBills?.upcoming ?? [],
        due: upcomingBills?.due ?? [],
        missed: upcomingBills?.missed ?? [],
    };
    const budgets = topBudgets ?? [];
    const flatAccounts = accountGroups.flatMap((g) => g.accounts);
    const cycleDates = { start: cycle.cycle_start, end: cycle.cycle_end };

    const hasAnyBills =
        bills.due.length > 0 ||
        bills.upcoming.length > 0 ||
        bills.missed.length > 0;

    const isEmpty = accountGroups.length === 0;

    function navigateCycle(newOffset: number) {
        router.get(
            dashboard.url(ledger!.id),
            { offset: newOffset },
            {
                only: [
                    'cycle',
                    'summary',
                    'dailyTrend',
                    'topCategories',
                    'recentTransactions',
                    'uncategorizedCount',
                ],
                preserveState: true,
                preserveScroll: true,
                replace: true,
            },
        );
    }

    const categoryChartConfig: ChartConfig = Object.fromEntries(
        topCats.map((cat, index) => [
            cat.name,
            {
                label: cat.name,
                color: cat.color ?? CHART_COLORS[index % CHART_COLORS.length],
            },
        ]),
    );

    const categoryChartData = topCats.map((cat, index) => ({
        name: cat.name,
        total: cat.total,
        fill: cat.color ?? CHART_COLORS[index % CHART_COLORS.length],
    }));

    function handleLoadSampleData() {
        setIsLoadingSampleData(true);

        router.post(
            storeSampleData.url(ledger!.id),
            {},
            {
                onSuccess: () => {
                    toast.success('Sample data loaded successfully.');
                    setIsLoadingSampleData(false);
                },
                onError: () => {
                    toast.error('Failed to load sample data.');
                    setIsLoadingSampleData(false);
                },
            },
        );
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={ledger!.name} />

            <div className="flex h-full flex-1 flex-col gap-4 p-4 md:p-6">
                {/* Cycle nav */}
                <div className="flex items-center justify-end">
                    <div className="flex shrink-0 items-center gap-1">
                        <Button
                            variant="ghost"
                            size="icon"
                            className="size-6"
                            onClick={() => navigateCycle(cycle.offset - 1)}
                        >
                            <ChevronLeft className="size-4" />
                        </Button>
                        <p className="text-xs whitespace-nowrap text-muted-foreground">
                            <Calendar className="mr-1 inline size-3" />
                            {formatDate(cycleDates.start)} &ndash;{' '}
                            {formatDate(cycleDates.end)}
                        </p>
                        <Button
                            variant="ghost"
                            size="icon"
                            className="size-6"
                            onClick={() => navigateCycle(cycle.offset + 1)}
                        >
                            <ChevronRight className="size-4" />
                        </Button>
                        {cycle.offset !== 0 && (
                            <Button
                                variant="outline"
                                size="sm"
                                className="ml-1 h-6 text-xs"
                                onClick={() => navigateCycle(0)}
                            >
                                Current
                            </Button>
                        )}
                    </div>
                </div>

                {/* Empty state */}
                {isEmpty && (
                    <Card>
                        <CardContent className="flex flex-col items-center gap-4 py-12 text-center">
                            <DatabaseZap className="size-10 text-muted-foreground" />
                            <div>
                                <h2 className="text-lg font-semibold">
                                    No data yet
                                </h2>
                                <p className="mt-1 max-w-sm text-sm text-muted-foreground">
                                    Start by adding your first account and
                                    transaction, or load sample data to explore.
                                </p>
                            </div>
                            <div className="flex flex-col gap-2 sm:flex-row">
                                <Button asChild>
                                    <Link href={accountsIndex.url(ledger!.id)}>
                                        <CreditCard className="mr-2 size-4" />
                                        Add Your First Account
                                    </Link>
                                </Button>
                                <Button
                                    onClick={handleLoadSampleData}
                                    disabled={isLoadingSampleData}
                                    variant="outline"
                                >
                                    <DatabaseZap className="mr-2 size-4" />
                                    {isLoadingSampleData
                                        ? 'Loading...'
                                        : 'Load Sample Data'}
                                </Button>
                            </div>
                        </CardContent>
                    </Card>
                )}

                {/* Uncategorized alert */}
                <Deferred data="uncategorizedCount" fallback={null}>
                    <UncategorizedAlert
                        count={uncatCount}
                        dismissed={uncategorizedDismissed}
                        ledgerId={ledger!.id}
                        onDismiss={() => setUncategorizedDismissed(true)}
                    />
                </Deferred>

                {/* Upcoming Recurring */}
                <Deferred
                    data="upcomingBills"
                    fallback={
                        <Card className="border-amber-500 p-0 dark:border-amber-400">
                            <CardContent className="p-3">
                                <div className="flex items-center gap-2 pb-2">
                                    <Bell className="size-4 text-muted-foreground" />
                                    <Skeleton className="h-4 w-36" />
                                </div>
                                <div className="space-y-1">
                                    {[1, 2].map((i) => (
                                        <div
                                            key={i}
                                            className="flex items-center justify-between rounded-lg px-3 py-2"
                                        >
                                            <Skeleton className="h-4 w-28" />
                                            <Skeleton className="h-7 w-24" />
                                        </div>
                                    ))}
                                </div>
                            </CardContent>
                        </Card>
                    }
                >
                    {hasAnyBills && (
                        <Card className="border-amber-500 p-0 dark:border-amber-400">
                            <CardContent className="p-3">
                                <div className="flex items-center gap-2 pb-2">
                                    <Bell className="size-4 text-muted-foreground" />
                                    <span className="text-sm font-semibold">
                                        Upcoming Recurring
                                    </span>
                                </div>
                                <div className="space-y-1">
                                    {bills.missed.map((bill) => (
                                        <UpcomingBillRow
                                            key={`missed-${bill.id}`}
                                            bill={bill}
                                            status="missed"
                                            onPay={setPayingBill}
                                        />
                                    ))}
                                    {bills.due.map((bill) => (
                                        <UpcomingBillRow
                                            key={`due-${bill.id}`}
                                            bill={bill}
                                            status="due"
                                            onPay={setPayingBill}
                                        />
                                    ))}
                                    {bills.upcoming.map((bill) => (
                                        <UpcomingBillRow
                                            key={`upcoming-${bill.id}`}
                                            bill={bill}
                                            status="upcoming"
                                            onPay={setPayingBill}
                                        />
                                    ))}
                                </div>
                            </CardContent>
                        </Card>
                    )}
                </Deferred>

                {/* KPI Cards - Income, Expense, Net in one row */}
                <div className="grid grid-cols-3 gap-3">
                    <Link
                        href={transactionsIndex.url(ledger!.id, {
                            query: {
                                'transaction_types[]': 'income',
                                date_from: cycleDates.start,
                                date_to: cycleDates.end,
                            },
                        })}
                        className="block"
                    >
                        <Card className="py-3 transition-colors hover:bg-muted/30">
                            <CardContent className="px-3">
                                <span className="text-xs text-muted-foreground">
                                    Income
                                </span>
                                <p
                                    className={`text-sm font-bold tabular-nums sm:text-base md:text-lg ${amountColor(summary.income)}`}
                                >
                                    {formatAbsAmount(
                                        summary.income,
                                        privacyMode,
                                    )}
                                </p>
                            </CardContent>
                        </Card>
                    </Link>
                    <Link
                        href={transactionsIndex.url(ledger!.id, {
                            query: {
                                'transaction_types[]': 'expense',
                                date_from: cycleDates.start,
                                date_to: cycleDates.end,
                            },
                        })}
                        className="block"
                    >
                        <Card className="py-3 transition-colors hover:bg-muted/30">
                            <CardContent className="px-3">
                                <span className="text-xs text-muted-foreground">
                                    Expense
                                </span>
                                <p
                                    className={`text-sm font-bold tabular-nums sm:text-base md:text-lg ${amountColor(summary.expense)}`}
                                >
                                    {formatAbsAmount(
                                        summary.expense,
                                        privacyMode,
                                    )}
                                </p>
                            </CardContent>
                        </Card>
                    </Link>
                    <Card className="py-3">
                        <CardContent className="px-3">
                            <span className="text-xs text-muted-foreground">
                                Net
                            </span>
                            <p
                                className={`text-sm font-bold tabular-nums sm:text-base md:text-lg ${amountColor(summary.net)}`}
                            >
                                {formatAbsAmount(summary.net, privacyMode)}
                            </p>
                        </CardContent>
                    </Card>
                </div>

                {/* Expense Trend */}
                <Card className="min-w-0 overflow-hidden">
                    <CardHeader>
                        <CardTitle>Expense Trend</CardTitle>
                        <CardDescription>
                            Daily categorized expenses this cycle
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <Deferred
                            data="dailyTrend"
                            fallback={<Skeleton className="h-[280px] w-full" />}
                        >
                            {dailyExpenseTrend.length === 0 ? (
                                <p className="text-sm text-muted-foreground">
                                    No expense data this cycle.
                                </p>
                            ) : (
                                <ChartContainer
                                    config={expenseChartConfig}
                                    className="h-[280px] w-full"
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
                                            fontSize={12}
                                            tickFormatter={formatChartDate}
                                        />
                                        <YAxis
                                            tickLine={false}
                                            axisLine={false}
                                            fontSize={12}
                                            width={60}
                                            tickFormatter={(v) =>
                                                formatAbsAmount(v, privacyMode)
                                            }
                                        />
                                        <ChartTooltip
                                            content={
                                                <ChartTooltipContent
                                                    labelFormatter={(value) =>
                                                        formatChartDate(
                                                            String(value),
                                                        )
                                                    }
                                                    formatter={(value) =>
                                                        formatAbsAmount(
                                                            Number(value),
                                                            privacyMode,
                                                        )
                                                    }
                                                />
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
                                                    stopColor="oklch(0.637 0.237 25.331)"
                                                    stopOpacity={0.3}
                                                />
                                                <stop
                                                    offset="95%"
                                                    stopColor="oklch(0.637 0.237 25.331)"
                                                    stopOpacity={0}
                                                />
                                            </linearGradient>
                                        </defs>
                                        <Area
                                            dataKey="expense"
                                            type="monotone"
                                            stroke="oklch(0.637 0.237 25.331)"
                                            fill="url(#expenseGradient)"
                                            strokeWidth={2}
                                        />
                                    </AreaChart>
                                </ChartContainer>
                            )}
                        </Deferred>
                    </CardContent>
                </Card>

                {/* Top Categories + Accounts */}
                <div className="grid gap-6 lg:grid-cols-2">
                    <Card className="min-w-0 overflow-hidden">
                        <CardHeader>
                            <CardTitle>Top Expense Categories</CardTitle>
                            <CardDescription>
                                Highest spending this cycle
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <Deferred
                                data="topCategories"
                                fallback={
                                    <Skeleton className="h-[280px] w-full" />
                                }
                            >
                                {topCats.length === 0 ? (
                                    <p className="text-sm text-muted-foreground">
                                        No categorized expenses this cycle.
                                    </p>
                                ) : (
                                    <ChartContainer
                                        config={categoryChartConfig}
                                        className="h-[280px] w-full"
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
                                                tickFormatter={(v) =>
                                                    formatAbsAmount(
                                                        v,
                                                        privacyMode,
                                                    )
                                                }
                                            />
                                            <ChartTooltip
                                                content={
                                                    <ChartTooltipContent
                                                        formatter={(value) =>
                                                            formatAbsAmount(
                                                                Number(value),
                                                                privacyMode,
                                                            )
                                                        }
                                                    />
                                                }
                                            />
                                            <Bar
                                                dataKey="total"
                                                radius={[0, 4, 4, 0]}
                                                className="cursor-pointer"
                                                onClick={(data) => {
                                                    const cat = topCats.find(
                                                        (c) =>
                                                            c.name ===
                                                            data.name,
                                                    );

                                                    if (cat?.id) {
                                                        router.visit(
                                                            transactionsIndex.url(
                                                                ledger!.id,
                                                                {
                                                                    query: {
                                                                        'category_ids[]':
                                                                            cat.id,
                                                                        date_from:
                                                                            cycleDates.start,
                                                                        date_to:
                                                                            cycleDates.end,
                                                                    },
                                                                },
                                                            ),
                                                        );
                                                    }
                                                }}
                                            />
                                        </BarChart>
                                    </ChartContainer>
                                )}
                            </Deferred>
                        </CardContent>
                    </Card>

                    {/* Accounts */}
                    <Card className="max-h-[28rem] min-w-0 overflow-hidden">
                        <CardHeader>
                            <div className="flex items-center gap-2">
                                <CreditCard className="size-4 text-muted-foreground" />
                                <CardTitle>Accounts</CardTitle>
                            </div>
                        </CardHeader>
                        <CardContent className="overflow-y-auto">
                            <div className="space-y-4">
                                {accountGroups.map((group) => (
                                    <div key={group.type.id}>
                                        <div className="mb-1.5">
                                            <span className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                                                {group.type.name}
                                            </span>
                                        </div>
                                        <div className="space-y-0.5">
                                            {group.accounts.map((account) => {
                                                const balance = parseFloat(
                                                    String(
                                                        account.current_balance ??
                                                            account.initial_balance ??
                                                            '0',
                                                    ),
                                                );

                                                return (
                                                    <Link
                                                        key={account.id}
                                                        href={transactionsIndex.url(
                                                            ledger!.id,
                                                            {
                                                                query: {
                                                                    'account_ids[]':
                                                                        String(
                                                                            account.id,
                                                                        ),
                                                                },
                                                            },
                                                        )}
                                                        className="flex items-center justify-between rounded-lg px-3 py-2 transition-colors hover:bg-muted/50"
                                                    >
                                                        <span className="inline-flex items-center gap-1.5 text-sm">
                                                            {account.color && (
                                                                <span
                                                                    className="inline-block size-2 shrink-0 rounded-full"
                                                                    style={{
                                                                        backgroundColor:
                                                                            account.color,
                                                                    }}
                                                                />
                                                            )}
                                                            {account.name}
                                                        </span>
                                                        {balance < 0 ? (
                                                            <Tooltip>
                                                                <TooltipTrigger
                                                                    asChild
                                                                >
                                                                    <span className="text-sm font-medium text-red-500 tabular-nums dark:text-red-400">
                                                                        {formatAbsAmount(
                                                                            balance,
                                                                            privacyMode,
                                                                        )}
                                                                    </span>
                                                                </TooltipTrigger>
                                                                <TooltipContent>
                                                                    <p>
                                                                        Negative
                                                                        balance
                                                                        &mdash;
                                                                        expenses
                                                                        exceed
                                                                        the
                                                                        initial
                                                                        balance.
                                                                    </p>
                                                                </TooltipContent>
                                                            </Tooltip>
                                                        ) : (
                                                            <span className="text-sm font-medium tabular-nums">
                                                                {formatAbsAmount(
                                                                    balance,
                                                                    privacyMode,
                                                                )}
                                                            </span>
                                                        )}
                                                    </Link>
                                                );
                                            })}
                                        </div>
                                    </div>
                                ))}
                                {accountGroups.length === 0 && (
                                    <p className="text-sm text-muted-foreground">
                                        No accounts yet.
                                    </p>
                                )}
                            </div>
                        </CardContent>
                    </Card>
                </div>

                {/* Budget Progress */}
                <Deferred
                    data="topBudgets"
                    fallback={
                        <Card>
                            <CardHeader>
                                <CardTitle>Budget Progress</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div className="space-y-4">
                                    {[1, 2, 3].map((i) => (
                                        <div key={i} className="space-y-1.5">
                                            <div className="flex items-center justify-between">
                                                <Skeleton className="h-4 w-24" />
                                                <Skeleton className="h-3 w-20" />
                                            </div>
                                            <Skeleton className="h-2 w-full" />
                                        </div>
                                    ))}
                                </div>
                            </CardContent>
                        </Card>
                    }
                >
                    {budgets.length > 0 && (
                        <Card>
                            <CardHeader>
                                <div className="flex items-center justify-between">
                                    <CardTitle>Budget Progress</CardTitle>
                                    <Button variant="ghost" size="sm" asChild>
                                        <Link
                                            href={budgetsIndex.url(ledger!.id)}
                                        >
                                            View all
                                        </Link>
                                    </Button>
                                </div>
                            </CardHeader>
                            <CardContent>
                                <div className="space-y-4">
                                    {budgets.map((budget) => (
                                        <div
                                            key={budget.id}
                                            className="space-y-1.5"
                                        >
                                            <div className="flex items-center justify-between text-sm">
                                                <span className="font-medium">
                                                    {budget.category_name}
                                                </span>
                                                <span className="text-xs text-muted-foreground tabular-nums">
                                                    {formatAbsAmount(
                                                        budget.spent,
                                                        privacyMode,
                                                    )}{' '}
                                                    /{' '}
                                                    {formatAbsAmount(
                                                        budget.amount,
                                                        privacyMode,
                                                    )}
                                                    {budget.status === 'over' &&
                                                        ' · Over'}
                                                </span>
                                            </div>
                                            <Progress
                                                value={Math.min(
                                                    budget.percentage,
                                                    100,
                                                )}
                                                className="h-2"
                                            />
                                        </div>
                                    ))}
                                </div>
                            </CardContent>
                        </Card>
                    )}
                </Deferred>

                {/* Recent Transactions */}
                <Card>
                    <CardHeader>
                        <div className="flex items-center justify-between">
                            <CardTitle>Recent Transactions</CardTitle>
                            <Button variant="outline" size="sm" asChild>
                                <Link href={transactionsIndex.url(ledger!.id)}>
                                    View all
                                </Link>
                            </Button>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <Deferred
                            data="recentTransactions"
                            fallback={
                                <div className="space-y-3">
                                    {[1, 2, 3, 4, 5].map((i) => (
                                        <div
                                            key={i}
                                            className="flex items-center justify-between py-2"
                                        >
                                            <div className="space-y-1.5">
                                                <Skeleton className="h-4 w-40" />
                                                <Skeleton className="h-3 w-24" />
                                            </div>
                                            <Skeleton className="h-4 w-16" />
                                        </div>
                                    ))}
                                </div>
                            }
                        >
                            {recentTxs.length === 0 ? (
                                <p className="text-sm text-muted-foreground">
                                    No recent transactions.
                                </p>
                            ) : (
                                <>
                                    {/* Mobile cards */}
                                    <div className="divide-y sm:hidden">
                                        {recentTxs.map((transaction) => {
                                            const amount = parseFloat(
                                                transaction.amount,
                                            );
                                            const label =
                                                transaction.payee?.name ??
                                                transaction.description ??
                                                'Transaction';

                                            return (
                                                <div
                                                    key={transaction.id}
                                                    className="cursor-pointer rounded-2xl border-b-2 p-3 transition-colors hover:bg-muted/50"
                                                    onClick={() =>
                                                        router.visit(
                                                            transactionEdit.url(
                                                                {
                                                                    ledger: ledger!
                                                                        .id,
                                                                    transaction:
                                                                        transaction.id,
                                                                },
                                                            ),
                                                        )
                                                    }
                                                >
                                                    <div className="flex items-start justify-between gap-3">
                                                        <p className="truncate text-sm font-medium">
                                                            {label}
                                                        </p>
                                                        <span
                                                            className={`shrink-0 text-sm font-semibold tabular-nums ${amountColor(amount)}`}
                                                        >
                                                            {formatAbsAmount(
                                                                amount,
                                                                privacyMode,
                                                            )}
                                                        </span>
                                                    </div>
                                                    <p className="mt-0.5 truncate text-xs text-muted-foreground">
                                                        {transaction.category
                                                            ?.name ??
                                                            'Uncategorized'}
                                                    </p>
                                                    <p className="mt-0.5 text-xs text-muted-foreground">
                                                        {formatDate(
                                                            transaction.transaction_date,
                                                        )}
                                                        {transaction.account
                                                            ?.name
                                                            ? ` · ${transaction.account.name}`
                                                            : ''}
                                                    </p>
                                                </div>
                                            );
                                        })}
                                    </div>

                                    {/* Desktop table */}
                                    <Table className="hidden sm:table">
                                        <TableHeader>
                                            <TableRow className="text-xs text-muted-foreground">
                                                <TableHead className="pr-4">
                                                    Date
                                                </TableHead>
                                                <TableHead className="pr-4">
                                                    Payee
                                                </TableHead>
                                                <TableHead className="pr-4">
                                                    Description
                                                </TableHead>
                                                <TableHead className="hidden pr-4 md:table-cell">
                                                    Account
                                                </TableHead>
                                                <TableHead className="hidden pr-4 lg:table-cell">
                                                    Category
                                                </TableHead>
                                                <TableHead className="text-right">
                                                    Amount
                                                </TableHead>
                                            </TableRow>
                                        </TableHeader>
                                        <TableBody>
                                            {recentTxs.map((transaction) => {
                                                const amount = parseFloat(
                                                    transaction.amount,
                                                );

                                                return (
                                                    <TableRow
                                                        key={transaction.id}
                                                        className="cursor-pointer"
                                                        onClick={() =>
                                                            router.visit(
                                                                transactionEdit.url(
                                                                    {
                                                                        ledger: ledger!
                                                                            .id,
                                                                        transaction:
                                                                            transaction.id,
                                                                    },
                                                                ),
                                                            )
                                                        }
                                                    >
                                                        <TableCell className="pr-4 whitespace-nowrap">
                                                            {formatDate(
                                                                transaction.transaction_date,
                                                            )}
                                                        </TableCell>
                                                        <TableCell className="pr-4">
                                                            <span className="truncate">
                                                                {transaction
                                                                    .payee
                                                                    ?.name ??
                                                                    '-'}
                                                            </span>
                                                        </TableCell>
                                                        <TableCell className="pr-4">
                                                            <span className="truncate">
                                                                {transaction.description ??
                                                                    '-'}
                                                            </span>
                                                        </TableCell>
                                                        <TableCell className="hidden pr-4 md:table-cell">
                                                            {transaction.account
                                                                ?.name ?? '-'}
                                                        </TableCell>
                                                        <TableCell className="hidden pr-4 lg:table-cell">
                                                            {transaction
                                                                .category
                                                                ?.name ?? '-'}
                                                        </TableCell>
                                                        <TableCell
                                                            className={`text-right font-medium tabular-nums ${amountColor(amount)}`}
                                                        >
                                                            {formatAbsAmount(
                                                                amount,
                                                                privacyMode,
                                                            )}
                                                        </TableCell>
                                                    </TableRow>
                                                );
                                            })}
                                        </TableBody>
                                    </Table>
                                </>
                            )}
                        </Deferred>
                    </CardContent>
                </Card>
            </div>

            <PayBillDialog
                bill={payingBill}
                ledgerId={ledger!.id}
                accounts={flatAccounts}
                onClose={() => setPayingBill(null)}
            />
        </AppLayout>
    );
}

function UncategorizedAlert({
    count,
    dismissed,
    ledgerId,
    onDismiss,
}: {
    count: number;
    dismissed: boolean;
    ledgerId: number;
    onDismiss: () => void;
}) {
    if (count === 0 || dismissed) {
        return null;
    }

    return (
        <div className="flex items-center gap-3 rounded-lg border border-amber-400 bg-amber-50 px-4 py-3 text-sm dark:border-amber-600 dark:bg-amber-950/40">
            <AlertTriangle className="size-4 shrink-0 text-amber-500" />
            <span className="flex-1">
                You have{' '}
                <span className="font-bold text-amber-700 tabular-nums dark:text-amber-300">
                    {count}
                </span>{' '}
                uncategorized transaction(s)
            </span>
            <Link
                href={transactionsIndex.url(ledgerId, {
                    query: { uncategorized: '1' },
                })}
                className="font-medium underline underline-offset-2"
            >
                Review
            </Link>
            <button
                onClick={onDismiss}
                className="text-muted-foreground transition-colors hover:text-foreground"
            >
                <X className="size-4" />
            </button>
        </div>
    );
}

function UpcomingBillRow({
    bill,
    status,
    onPay,
}: {
    bill: Bill;
    status: 'missed' | 'due' | 'upcoming';
    onPay: (bill: Bill) => void;
}) {
    const isIncome = bill.transaction_type === 'income';
    const payeeName = bill.payee?.name ?? bill.name;
    const { privacyMode } = usePrivacyMode();

    const borderColor: Record<string, string> = {
        missed: 'border-l-red-500',
        due: 'border-l-amber-500',
        upcoming: 'border-l-green-500',
    };

    const statusLabels: Record<string, string> = {
        missed: 'Missed',
        due: 'Due Today',
        upcoming: relativeDate(bill.next_due_date),
    };

    return (
        <div
            className={`flex items-center justify-between rounded-lg border-l-2 px-3 py-2 ${borderColor[status]}`}
        >
            <div className="min-w-0 flex-1">
                <p className="truncate text-sm font-medium">{payeeName}</p>
                <div className="mt-0.5 flex items-center gap-2">
                    <span
                        className={`text-sm font-semibold tabular-nums ${
                            isIncome
                                ? 'text-foreground'
                                : 'text-red-500 dark:text-red-400'
                        }`}
                    >
                        {formatAbsAmount(bill.amount, privacyMode)}
                    </span>
                    <span className="text-xs text-muted-foreground">
                        &middot;
                    </span>
                    <span className="text-xs font-medium text-muted-foreground">
                        {statusLabels[status]}
                    </span>
                </div>
            </div>
            <div className="ml-3 shrink-0">
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    onClick={() => onPay(bill)}
                >
                    Mark as paid
                </Button>
            </div>
        </div>
    );
}
