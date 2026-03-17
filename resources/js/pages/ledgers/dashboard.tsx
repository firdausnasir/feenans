import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    AlertTriangle,
    Bell,
    Calendar,
    ChevronLeft,
    ChevronRight,
    CreditCard,
    DatabaseZap,
    Landmark,
    TrendingDown,
    TrendingUp,
    Wallet,
    X,
} from 'lucide-react';
import { useCallback, useState } from 'react';
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

import { AddTransactionModal } from '@/components/add-transaction-modal';
import { PayBillDialog } from '@/components/pay-bill-dialog';
import { Badge } from '@/components/ui/badge';
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
import { useApiQuery } from '@/hooks/use-api-query';
import AppLayout from '@/layouts/app-layout';
import { api } from '@/lib/api-client';
import { formatAbsAmount, formatAmount, formatDate } from '@/lib/format';
import { dashboard } from '@/routes/ledgers';
import {
    show as accountShow,
    index as accountsIndex,
    create as createAccount,
} from '@/routes/ledgers/accounts';
import { index as budgetsIndex } from '@/routes/ledgers/budgets';
import { index as reportsIndex } from '@/routes/ledgers/reports';
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

type NetWorthData = {
    assets: number;
    liabilities: number;
    net: number;
    trend: Array<{ month: string; net: number }>;
};

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

export default function LedgerDashboard() {
    const { currentLedger: ledger } = usePage().props;
    const base = `/api/v1/ledgers/${ledger!.id}`;

    const breadcrumbs: BreadcrumbItem[] = [
        { title: ledger!.name, href: dashboard.url(ledger!.id) },
    ];

    const [cycleOffset, setCycleOffset] = useState(0);
    const [payingBill, setPayingBill] = useState<Bill | null>(null);
    const [showExpense, setShowExpense] = useState(true);
    const [showIncome, setShowIncome] = useState(true);
    const [uncategorizedDismissed, setUncategorizedDismissed] = useState(false);
    const [isLoadingSampleData, setIsLoadingSampleData] = useState(false);

    // ── Cycle dates (other queries depend on this) ─────────────────────────
    const { data: cycle } = useApiQuery<CycleResponse>(`${base}/cycle`, {
        params: { offset: cycleOffset },
        deps: [cycleOffset],
    });

    const dateParams = cycle
        ? { date_from: cycle.cycle_start, date_to: cycle.cycle_end }
        : {};

    // ── Parallel data fetches ──────────────────────────────────────────────
    const {
        data: summary,
        loading: summaryLoading,
        refetch: refetchSummary,
    } = useApiQuery<Summary>(cycle ? `${base}/transactions/summary` : null, {
        params: dateParams,
        deps: [cycle],
    });

    const {
        data: accountsResponse,
        loading: accountsLoading,
        refetch: refetchAccounts,
    } = useApiQuery<{ data: AccountGroup[] }>(`${base}/accounts`, {
        params: { grouped: true, with_type_totals: true },
    });

    const {
        data: recentTxResponse,
        loading: recentTxLoading,
        refetch: refetchRecentTx,
    } = useApiQuery<{ data: Transaction[] }>(
        cycle ? `${base}/transactions` : null,
        { params: { ...dateParams, per_page: 10 }, deps: [cycle] },
    );

    const {
        data: dailyTrendResponse,
        loading: dailyTrendLoading,
        refetch: refetchDailyTrend,
    } = useApiQuery<{ data: DailyTrend[] }>(
        cycle ? `${base}/transactions/daily-trend` : null,
        { params: dateParams, deps: [cycle] },
    );

    const {
        data: topCategoriesResponse,
        loading: topCategoriesLoading,
        refetch: refetchTopCategories,
    } = useApiQuery<{ data: TopCategory[] }>(
        cycle ? `${base}/categories/top-spending` : null,
        { params: { ...dateParams, limit: 5 }, deps: [cycle] },
    );

    const { data: uncategorizedResponse, refetch: refetchUncategorized } =
        useApiQuery<{ count: number }>(
            cycle ? `${base}/transactions/uncategorized-count` : null,
            { params: dateParams, deps: [cycle] },
        );

    const { data: upcomingBillsResponse, loading: billsLoading } =
        useApiQuery<UpcomingBills>(`${base}/bills`, {
            params: { upcoming: true },
        });

    const {
        data: topBudgetsResponse,
        loading: budgetsLoading,
        refetch: refetchBudgets,
    } = useApiQuery<{ data: BudgetStat[] }>(`${base}/budgets`, {
        params: { with_stats: true, top: 3 },
    });

    const {
        data: netWorthResponse,
        loading: netWorthLoading,
        refetch: refetchNetWorth,
    } = useApiQuery<{ data: NetWorthData }>(`${base}/net-worth`);

    const refetchDashboard = useCallback(() => {
        refetchSummary();
        refetchAccounts();
        refetchRecentTx();
        refetchDailyTrend();
        refetchTopCategories();
        refetchUncategorized();
        refetchBudgets();
        refetchNetWorth();
    }, [
        refetchSummary,
        refetchAccounts,
        refetchRecentTx,
        refetchDailyTrend,
        refetchTopCategories,
        refetchUncategorized,
        refetchBudgets,
        refetchNetWorth,
    ]);

    // ── Derived values ─────────────────────────────────────────────────────
    const accounts = accountsResponse?.data ?? [];
    const recentTransactions = recentTxResponse?.data ?? [];
    const dailyExpenseTrend = dailyTrendResponse?.data ?? [];
    const topCategories = topCategoriesResponse?.data ?? [];
    const uncategorizedCount = uncategorizedResponse?.count ?? 0;
    const upcomingBills: {
        upcoming: Bill[];
        due: Bill[];
        missed: Bill[];
    } = {
        upcoming: upcomingBillsResponse?.upcoming ?? [],
        due: upcomingBillsResponse?.due ?? [],
        missed: upcomingBillsResponse?.missed ?? [],
    };
    const topBudgets = topBudgetsResponse?.data ?? [];
    const netWorth = netWorthResponse?.data ?? null;
    const netWorthTrend = netWorth?.trend ?? [];
    const flatAccounts = accounts.flatMap((g) => g.accounts);
    const cycleDates = cycle
        ? { start: cycle.cycle_start, end: cycle.cycle_end }
        : null;

    const hasAnyBills =
        upcomingBills.due.length > 0 ||
        upcomingBills.upcoming.length > 0 ||
        upcomingBills.missed.length > 0;

    const hasUrgentBills =
        upcomingBills.missed.length > 0 || upcomingBills.due.length > 0;

    const isEmpty =
        !accountsLoading &&
        !recentTxLoading &&
        accounts.length === 0 &&
        recentTransactions.length === 0;

    function handlePayBill(bill: Bill) {
        setPayingBill(bill);
    }

    function navigateCycle(offset: number) {
        setCycleOffset(offset);
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

    function handleLoadSampleData() {
        setIsLoadingSampleData(true);

        api.post(storeSampleData.url(ledger!.id))
            .then(() => {
                toast.success('Sample data loaded successfully.');
                window.location.reload();
            })
            .catch(() => {
                toast.error('Failed to load sample data.');
            })
            .finally(() => {
                setIsLoadingSampleData(false);
            });
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={ledger!.name} />

            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6 lg:p-8">
                {/* Header */}
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">
                            {ledger!.name}
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Track balances, spending, and recent activity in one
                            place.
                        </p>
                    </div>
                    <div className="w-full sm:w-auto">
                        <AddTransactionModal
                            ledger={ledger!}
                            onModalClosed={refetchDashboard}
                        />
                    </div>
                </div>

                {/* Empty state with sample data option */}
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
                                    transaction, or load sample data to explore
                                    how everything works.
                                </p>
                            </div>
                            <div className="flex flex-col gap-2 sm:flex-row">
                                <Button asChild>
                                    <Link href={createAccount.url(ledger!.id)}>
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

                {/* Uncategorized transactions alert */}
                {uncategorizedCount > 0 && !uncategorizedDismissed && (
                    <div className="flex items-center gap-3 rounded-lg border border-yellow-200 bg-yellow-50 px-4 py-3 text-sm text-yellow-800 dark:border-yellow-800 dark:bg-yellow-950 dark:text-yellow-200">
                        <AlertTriangle className="size-4 shrink-0" />
                        <span className="flex-1">
                            You have {uncategorizedCount} uncategorized
                            transaction(s)
                        </span>
                        <Link
                            href={transactionsIndex.url(ledger!.id, {
                                query: { uncategorized: '1' },
                            })}
                            className="font-medium underline underline-offset-2"
                        >
                            Review
                        </Link>
                        <button
                            onClick={() => setUncategorizedDismissed(true)}
                            className="text-yellow-600 hover:text-yellow-800 dark:text-yellow-400"
                        >
                            <X className="size-4" />
                        </button>
                    </div>
                )}

                {/* Net Worth Card */}
                {netWorthLoading || !netWorth ? (
                    <Card className="border-primary/20 bg-gradient-to-r from-primary/5 to-transparent">
                        <CardContent className="px-4 py-3">
                            <div className="flex items-start justify-between gap-4">
                                <div>
                                    <Skeleton className="mb-2 h-5 w-24" />
                                    <Skeleton className="mb-2 h-8 w-40" />
                                    <Skeleton className="h-4 w-48" />
                                </div>
                                <Skeleton className="hidden h-16 w-32 sm:block" />
                            </div>
                        </CardContent>
                    </Card>
                ) : (
                    <Link
                        href={accountsIndex.url(ledger!.id)}
                        className="block"
                    >
                        <Card className="border-primary/20 bg-gradient-to-r from-primary/5 to-transparent transition-all duration-150 hover:scale-[1.01] hover:bg-primary/5">
                            <CardContent className="px-4 py-3">
                                <div className="flex items-start justify-between gap-4">
                                    <div>
                                        <div className="flex items-center gap-2">
                                            <Landmark className="size-5 text-primary" />
                                            <span className="text-sm font-medium text-muted-foreground">
                                                Net Worth
                                            </span>
                                        </div>
                                        <p
                                            className={`mt-2 text-2xl font-bold sm:text-3xl ${
                                                netWorth.net >= 0
                                                    ? 'text-green-600 dark:text-green-400'
                                                    : 'text-red-600 dark:text-red-400'
                                            }`}
                                        >
                                            {formatAmount(netWorth.net)}
                                        </p>
                                        <div className="mt-2 flex items-center gap-4 text-xs text-muted-foreground">
                                            <span>
                                                Assets:{' '}
                                                <span className="font-medium text-foreground">
                                                    {formatAmount(
                                                        netWorth.assets,
                                                    )}
                                                </span>
                                            </span>
                                            <span>
                                                Liabilities:{' '}
                                                <span className="font-medium text-red-600 dark:text-red-400">
                                                    {formatAmount(
                                                        netWorth.liabilities,
                                                    )}
                                                </span>
                                            </span>
                                        </div>
                                    </div>
                                    {netWorthTrend.length >= 2 && (
                                        <div className="hidden h-16 w-32 sm:block">
                                            <AreaChart
                                                width={128}
                                                height={64}
                                                data={netWorthTrend}
                                                margin={{
                                                    top: 4,
                                                    right: 0,
                                                    left: 0,
                                                    bottom: 0,
                                                }}
                                            >
                                                <defs>
                                                    <linearGradient
                                                        id="nwGrad"
                                                        x1="0"
                                                        y1="0"
                                                        x2="0"
                                                        y2="1"
                                                    >
                                                        <stop
                                                            offset="5%"
                                                            stopColor="var(--color-primary)"
                                                            stopOpacity={0.3}
                                                        />
                                                        <stop
                                                            offset="95%"
                                                            stopColor="var(--color-primary)"
                                                            stopOpacity={0}
                                                        />
                                                    </linearGradient>
                                                </defs>
                                                <Area
                                                    type="monotone"
                                                    dataKey="net"
                                                    stroke="var(--color-primary)"
                                                    strokeWidth={1.5}
                                                    fill="url(#nwGrad)"
                                                />
                                            </AreaChart>
                                        </div>
                                    )}
                                </div>
                            </CardContent>
                        </Card>
                    </Link>
                )}

                {/* Summary Cards - always 3 columns */}
                <div>
                    <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
                        {summaryLoading || !summary || !cycleDates ? (
                            <>
                                {[1, 2, 3].map((i) => (
                                    <Card key={i}>
                                        <CardContent className="px-4 py-2.5">
                                            <Skeleton className="mb-2 h-4 w-16" />
                                            <Skeleton className="h-6 w-24" />
                                        </CardContent>
                                    </Card>
                                ))}
                            </>
                        ) : (
                            <>
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
                                    <SummaryCard
                                        label="Income"
                                        value={summary.income}
                                        icon={
                                            <TrendingUp className="size-4 text-gray-700 dark:text-gray-300" />
                                        }
                                        colorClass="text-gray-700 dark:text-gray-300"
                                        previousValue={summary.prev_income}
                                    />
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
                                    <SummaryCard
                                        label="Expense"
                                        value={summary.expense}
                                        icon={
                                            <TrendingDown className="size-4 text-gray-700 dark:text-gray-300" />
                                        }
                                        colorClass="text-gray-700 dark:text-gray-300"
                                        previousValue={summary.prev_expense}
                                        invertTrendColor
                                    />
                                </Link>
                                <Link
                                    href={reportsIndex.url(ledger!.id)}
                                    className="block"
                                >
                                    <SummaryCard
                                        label="Net"
                                        value={summary.net}
                                        icon={
                                            <Wallet className="size-4 text-gray-700 dark:text-gray-300" />
                                        }
                                        colorClass="text-gray-700 dark:text-gray-300"
                                        previousValue={
                                            summary.prev_income -
                                            summary.prev_expense
                                        }
                                    />
                                </Link>
                            </>
                        )}
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
                            {cycleDates ? (
                                <p className="text-xs text-muted-foreground">
                                    <Calendar className="mr-1 inline size-3" />
                                    Cycle: {formatDate(cycleDates.start)}{' '}
                                    &ndash; {formatDate(cycleDates.end)}
                                </p>
                            ) : (
                                <Skeleton className="h-4 w-36" />
                            )}
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

                {/* Upcoming Recurring + Accounts */}
                <div className="grid gap-6 lg:grid-cols-2">
                    {/* Upcoming Bills */}
                    <Card className="max-h-[28rem] min-w-0 overflow-hidden">
                        <CardHeader>
                            <div className="flex items-center gap-2">
                                <Bell className="size-4 text-muted-foreground" />
                                <CardTitle>Upcoming Recurring</CardTitle>
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
                        <CardContent className="overflow-y-auto">
                            {billsLoading ? (
                                <div className="space-y-3">
                                    {[1, 2, 3].map((i) => (
                                        <div
                                            key={i}
                                            className="flex items-center justify-between px-3 py-2.5"
                                        >
                                            <div className="space-y-2">
                                                <Skeleton className="h-4 w-32" />
                                                <Skeleton className="h-3 w-24" />
                                            </div>
                                            <Skeleton className="h-8 w-16" />
                                        </div>
                                    ))}
                                </div>
                            ) : (
                                <div className="space-y-4">
                                    {!hasAnyBills && (
                                        <p className="text-sm text-muted-foreground">
                                            No upcoming recurring transactions.
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
                            )}
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
                            {accountsLoading ? (
                                <div className="space-y-5">
                                    {[1, 2].map((i) => (
                                        <div key={i}>
                                            <Skeleton className="mb-2 h-3 w-20" />
                                            <div className="space-y-1">
                                                {[1, 2].map((j) => (
                                                    <div
                                                        key={j}
                                                        className="flex items-center justify-between px-3 py-2.5"
                                                    >
                                                        <Skeleton className="h-4 w-28" />
                                                        <Skeleton className="h-4 w-16" />
                                                    </div>
                                                ))}
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            ) : (
                                <div className="space-y-5">
                                    {accounts.map((group) => (
                                        <div key={group.type.id}>
                                            <div className="mb-2 flex items-center gap-2">
                                                {group.type.color && (
                                                    <span
                                                        className="inline-block size-2.5 rounded-full"
                                                        style={{
                                                            backgroundColor:
                                                                group.type
                                                                    .color,
                                                        }}
                                                    />
                                                )}
                                                <span className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                                                    {group.type.name}
                                                </span>
                                            </div>
                                            <div className="space-y-1">
                                                {group.accounts.map(
                                                    (account) => {
                                                        const balance =
                                                            parseFloat(
                                                                String(
                                                                    account.current_balance ??
                                                                        account.initial_balance ??
                                                                        '0',
                                                                ),
                                                            );

                                                        return (
                                                            <Link
                                                                key={account.id}
                                                                href={accountShow.url(
                                                                    {
                                                                        ledger: ledger!
                                                                            .id,
                                                                        account:
                                                                            account.id,
                                                                    },
                                                                )}
                                                                className="flex items-center justify-between rounded-lg px-3 py-2.5 transition-colors hover:bg-muted/50"
                                                            >
                                                                <span className="inline-flex items-center gap-1.5 text-sm">
                                                                    {account.color && (
                                                                        <span
                                                                            className="inline-block h-2 w-2 shrink-0 rounded-full"
                                                                            style={{
                                                                                backgroundColor:
                                                                                    account.color,
                                                                            }}
                                                                        />
                                                                    )}
                                                                    {
                                                                        account.name
                                                                    }
                                                                </span>
                                                                {balance < 0 ? (
                                                                    <Tooltip>
                                                                        <TooltipTrigger
                                                                            asChild
                                                                        >
                                                                            <span className="inline-flex items-center gap-1 text-sm font-medium text-red-600 dark:text-red-400">
                                                                                <AlertTriangle className="size-3.5 shrink-0" />
                                                                                {formatAmount(
                                                                                    balance,
                                                                                )}
                                                                            </span>
                                                                        </TooltipTrigger>
                                                                        <TooltipContent>
                                                                            <p>
                                                                                This
                                                                                account
                                                                                has
                                                                                a
                                                                                negative
                                                                                balance,
                                                                                which
                                                                                can
                                                                                happen
                                                                                if
                                                                                you've
                                                                                logged
                                                                                more
                                                                                expenses
                                                                                than
                                                                                the
                                                                                initial
                                                                                balance
                                                                                you
                                                                                set.
                                                                            </p>
                                                                        </TooltipContent>
                                                                    </Tooltip>
                                                                ) : (
                                                                    <span className="text-sm font-medium">
                                                                        {formatAmount(
                                                                            balance,
                                                                        )}
                                                                    </span>
                                                                )}
                                                            </Link>
                                                        );
                                                    },
                                                )}
                                            </div>
                                        </div>
                                    ))}
                                    {accounts.length === 0 && (
                                        <p className="text-sm text-muted-foreground">
                                            No accounts yet.
                                        </p>
                                    )}
                                </div>
                            )}
                        </CardContent>
                    </Card>
                </div>

                {/* Charts: Expense Trend + Top Categories */}
                <div className="grid gap-6 lg:grid-cols-2">
                    {/* Expense & Income Trend */}
                    <Card className="min-w-0 overflow-hidden">
                        <CardHeader>
                            <CardTitle>Expense & Income Trend</CardTitle>
                            <CardDescription>
                                Daily expenses and income this cycle
                            </CardDescription>
                            {dailyExpenseTrend.length > 0 && (
                                <div className="flex items-center gap-2 pt-1">
                                    <button
                                        type="button"
                                        onClick={() =>
                                            setShowExpense((v) => !v)
                                        }
                                        className={`inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-xs font-medium transition-colors ${
                                            showExpense
                                                ? 'border-transparent bg-muted text-foreground'
                                                : 'border-border text-muted-foreground opacity-60'
                                        }`}
                                    >
                                        <span
                                            className="inline-block size-2 rounded-full"
                                            style={{
                                                backgroundColor:
                                                    'var(--color-chart-1)',
                                            }}
                                        />
                                        Expense
                                    </button>
                                    <button
                                        type="button"
                                        onClick={() => setShowIncome((v) => !v)}
                                        className={`inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-xs font-medium transition-colors ${
                                            showIncome
                                                ? 'border-transparent bg-muted text-foreground'
                                                : 'border-border text-muted-foreground opacity-60'
                                        }`}
                                    >
                                        <span
                                            className="inline-block size-2 rounded-full"
                                            style={{
                                                backgroundColor:
                                                    'var(--color-chart-3)',
                                            }}
                                        />
                                        Income
                                    </button>
                                </div>
                            )}
                        </CardHeader>
                        <CardContent>
                            {dailyTrendLoading ? (
                                <Skeleton className="h-[280px] w-full" />
                            ) : dailyExpenseTrend.length === 0 ? (
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
                                        />
                                        <ChartTooltip
                                            content={
                                                <ChartTooltipContent
                                                    labelFormatter={(value) =>
                                                        formatChartDate(
                                                            String(value),
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
                                        {showIncome && (
                                            <Area
                                                dataKey="income"
                                                type="monotone"
                                                stroke="var(--color-chart-3)"
                                                fill="url(#incomeGradient)"
                                                strokeWidth={2}
                                                strokeDasharray="5 3"
                                            />
                                        )}
                                        {showExpense && (
                                            <Area
                                                dataKey="expense"
                                                type="monotone"
                                                stroke="var(--color-chart-1)"
                                                fill="url(#expenseGradient)"
                                                strokeWidth={2.5}
                                            />
                                        )}
                                    </AreaChart>
                                </ChartContainer>
                            )}
                        </CardContent>
                    </Card>

                    {/* Top Expense Categories */}
                    <Card className="min-w-0 overflow-hidden">
                        <CardHeader>
                            <CardTitle>Top Expense Categories</CardTitle>
                            <CardDescription>
                                Highest spending this cycle
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            {topCategoriesLoading ? (
                                <Skeleton className="h-[280px] w-full" />
                            ) : topCategories.length === 0 ? (
                                <p className="text-sm text-muted-foreground">
                                    No expenses this cycle.
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
                                        />
                                        <ChartTooltip
                                            content={<ChartTooltipContent />}
                                        />
                                        <Bar
                                            dataKey="total"
                                            radius={[0, 4, 4, 0]}
                                            className="cursor-pointer"
                                            onClick={(data) => {
                                                const cat = topCategories.find(
                                                    (c) => c.name === data.name,
                                                );

                                                if (cat?.id && cycleDates) {
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
                        </CardContent>
                    </Card>
                </div>

                {/* Budget Progress */}
                {budgetsLoading ? (
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
                ) : (
                    topBudgets.length > 0 && (
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
                                    {topBudgets.map((budget) => {
                                        const statusColor: Record<
                                            string,
                                            string
                                        > = {
                                            good: 'text-green-600 dark:text-green-400',
                                            warning:
                                                'text-yellow-600 dark:text-yellow-400',
                                            danger: 'text-orange-600 dark:text-orange-400',
                                            over: 'text-red-600 dark:text-red-400',
                                        };

                                        return (
                                            <div
                                                key={budget.id}
                                                className="space-y-1.5"
                                            >
                                                <div className="flex items-center justify-between text-sm">
                                                    <span className="font-medium">
                                                        {budget.category_name}
                                                    </span>
                                                    <span
                                                        className={`text-xs ${statusColor[budget.status] ?? ''}`}
                                                    >
                                                        {formatAbsAmount(
                                                            budget.spent,
                                                        )}{' '}
                                                        /{' '}
                                                        {formatAbsAmount(
                                                            budget.amount,
                                                        )}
                                                    </span>
                                                </div>
                                                <Progress
                                                    value={budget.percentage}
                                                    className="h-2"
                                                />
                                            </div>
                                        );
                                    })}
                                </div>
                            </CardContent>
                        </Card>
                    )
                )}

                {/* Recent Transactions - full width */}
                <Card>
                    <CardHeader>
                        <CardTitle>Recent Transactions</CardTitle>
                    </CardHeader>
                    <CardContent>
                        {recentTxLoading ? (
                            <div className="space-y-3">
                                {[1, 2, 3, 4, 5].map((i) => (
                                    <div
                                        key={i}
                                        className="flex items-center justify-between py-2"
                                    >
                                        <div className="space-y-2">
                                            <Skeleton className="h-4 w-40" />
                                            <Skeleton className="h-3 w-24" />
                                        </div>
                                        <Skeleton className="h-4 w-16" />
                                    </div>
                                ))}
                            </div>
                        ) : recentTransactions.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                No recent transactions.
                            </p>
                        ) : (
                            <>
                                <div className="divide-y sm:hidden">
                                    {recentTransactions.map((transaction) => {
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
                                            <div
                                                key={transaction.id}
                                                className="flex cursor-pointer items-center justify-between gap-3 py-3"
                                                onClick={() =>
                                                    router.visit(
                                                        transactionEdit.url({
                                                            ledger: ledger!.id,
                                                            transaction:
                                                                transaction.id,
                                                        }),
                                                    )
                                                }
                                            >
                                                <div className="min-w-0 flex-1">
                                                    <p className="truncate text-sm font-medium">
                                                        {transaction.description ??
                                                            'Transaction'}
                                                    </p>
                                                    <p className="text-xs text-muted-foreground">
                                                        {formatDate(
                                                            transaction.transaction_date,
                                                        )}
                                                        {transaction.account
                                                            ?.name
                                                            ? ` · ${transaction.account.name}`
                                                            : ''}
                                                    </p>
                                                </div>
                                                <span
                                                    className={`shrink-0 text-sm font-semibold tabular-nums ${amountClass}`}
                                                >
                                                    {formatAbsAmount(amount)}
                                                </span>
                                            </div>
                                        );
                                    })}
                                </div>
                                <Table className="hidden sm:table">
                                    <TableHeader>
                                        <TableRow className="text-xs text-muted-foreground">
                                            <TableHead className="pr-4">
                                                Date
                                            </TableHead>
                                            <TableHead className="pr-4">
                                                Description
                                            </TableHead>
                                            <TableHead className="hidden pr-4 sm:table-cell">
                                                Account
                                            </TableHead>
                                            <TableHead className="hidden pr-4 md:table-cell">
                                                Category
                                            </TableHead>
                                            <TableHead className="text-right">
                                                Amount
                                            </TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
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
                                                        <TableCell className="pr-4">
                                                            {formatDate(
                                                                transaction.transaction_date,
                                                            )}
                                                        </TableCell>
                                                        <TableCell className="pr-4">
                                                            <div className="flex items-center gap-2">
                                                                <span className="truncate">
                                                                    {transaction.description ??
                                                                        'Transaction'}
                                                                </span>
                                                            </div>
                                                        </TableCell>
                                                        <TableCell className="hidden pr-4 sm:table-cell">
                                                            {transaction.account
                                                                ?.name ?? '-'}
                                                        </TableCell>
                                                        <TableCell className="hidden pr-4 md:table-cell">
                                                            {transaction
                                                                .category
                                                                ?.name ?? '-'}
                                                        </TableCell>
                                                        <TableCell
                                                            className={`text-right font-medium ${amountClass}`}
                                                        >
                                                            {formatAbsAmount(
                                                                amount,
                                                            )}
                                                        </TableCell>
                                                    </TableRow>
                                                );
                                            },
                                        )}
                                    </TableBody>
                                </Table>
                            </>
                        )}
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

function trendInfo(current: number, previous: number, invertColor = false) {
    if (previous === 0) {
        return null;
    }

    const pctChange = Math.round(((current - previous) / previous) * 100);

    if (pctChange === 0) {
        return null;
    }

    const isUp = pctChange > 0;
    const isPositive = invertColor ? !isUp : isUp;

    return {
        label: `${isUp ? '\u2191' : '\u2193'} ${Math.abs(pctChange)}% vs last period`,
        colorClass: isPositive ? 'text-emerald-500' : 'text-red-400',
    };
}

function SummaryCard({
    label,
    value,
    icon,
    colorClass,
    previousValue,
    invertTrendColor,
}: {
    label: string;
    value: number;
    icon: React.ReactNode;
    colorClass: string;
    previousValue?: number;
    invertTrendColor?: boolean;
}) {
    const trend =
        previousValue !== undefined
            ? trendInfo(value, previousValue, invertTrendColor)
            : null;

    return (
        <Card className="cursor-pointer transition-all duration-150 hover:scale-[1.02] hover:bg-muted/30">
            <CardContent className="px-4 py-2.5">
                <div className="flex items-center gap-2">
                    {icon}
                    <span className="text-xs font-medium text-muted-foreground">
                        {label}
                    </span>
                </div>
                <p
                    className={`mt-1 text-lg font-bold sm:text-xl ${colorClass}`}
                >
                    {formatAmount(value)}
                </p>
                {trend && (
                    <p className={`mt-0.5 text-xs ${trend.colorClass}`}>
                        {trend.label}
                    </p>
                )}
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
    onPay: (bill: Bill) => void;
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

function BillRow({ bill, onPay }: { bill: Bill; onPay: (bill: Bill) => void }) {
    const isIncome = bill.transaction_type === 'income';
    const amountClass = isIncome
        ? 'text-green-600 dark:text-green-400'
        : 'text-red-600 dark:text-red-400';
    const actionLabel = isIncome ? 'Record' : 'Pay';

    return (
        <div className="flex items-center justify-between rounded-lg px-3 py-2.5 transition-colors hover:bg-muted/50">
            <div className="min-w-0 flex-1">
                <div className="flex items-center gap-2">
                    <p className="truncate text-sm font-medium">{bill.name}</p>
                    <Badge
                        variant="outline"
                        className={`shrink-0 text-[10px] ${
                            isIncome
                                ? 'border-green-200 text-green-700 dark:border-green-800 dark:text-green-400'
                                : 'border-red-200 text-red-700 dark:border-red-800 dark:text-red-400'
                        }`}
                    >
                        {isIncome ? 'Income' : 'Expense'}
                    </Badge>
                </div>
                <div className="mt-1 flex flex-wrap items-center gap-2 text-xs text-muted-foreground">
                    <span className={amountClass}>
                        {formatAmount(bill.amount)}
                    </span>
                    <span>&middot;</span>
                    <span>{formatDate(bill.next_due_date)}</span>
                </div>
            </div>
            <div className="ml-3 flex shrink-0 items-center gap-2">
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    onClick={() => onPay(bill)}
                    className="shrink-0"
                >
                    {actionLabel}
                </Button>
            </div>
        </div>
    );
}
