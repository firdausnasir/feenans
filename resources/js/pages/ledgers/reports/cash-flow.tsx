import { Deferred, Head, usePage } from '@inertiajs/react';
import { BarChart3, Calendar } from 'lucide-react';
import {
    Bar,
    CartesianGrid,
    ComposedChart,
    Legend,
    Line,
    ResponsiveContainer,
    Tooltip,
    XAxis,
    YAxis,
} from 'recharts';
import Heading from '@/components/heading';
import { ReportViewSelect } from '@/components/report-view-select';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { EmptyState } from '@/components/ui/empty-state';
import { Skeleton } from '@/components/ui/skeleton';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import AppLayout from '@/layouts/app-layout';
import { formatAbsAmount, formatAmount, formatDate } from '@/lib/format';
import { dashboard as ledgerDashboard } from '@/routes/ledgers';
import {
    index as reportsIndex,
    cashFlow as cashFlowRoute,
} from '@/routes/ledgers/reports';
import type { BreadcrumbItem } from '@/types';

// ─── Types ───────────────────────────────────────────────────────────────────

type DailyCashFlowEntry = {
    date: string;
    income: number;
    expense: number;
    net: number;
};

type UpcomingBill = {
    id: number;
    name: string;
    amount: number;
    transaction_type: string;
    next_due_date: string;
    account_name: string | null;
};

// ─── Helpers ─────────────────────────────────────────────────────────────────

function formatDayLabel(dateStr: string): string {
    const date = new Date(dateStr + 'T00:00:00');

    return date.toLocaleDateString('en-MY', { month: 'short', day: 'numeric' });
}

// ─── Components ──────────────────────────────────────────────────────────────

function DailyCashFlowChart({ data }: { data: DailyCashFlowEntry[] }) {
    if (data.length === 0) {
        return (
            <EmptyState
                icon={<BarChart3 className="size-6" />}
                title="No data"
                description="No cash flow data for this period."
            />
        );
    }

    const chartData = data.map((entry) => ({
        ...entry,
        dayLabel: formatDayLabel(entry.date),
    }));

    return (
        <ResponsiveContainer width="100%" height={300}>
            <ComposedChart
                data={chartData}
                margin={{ top: 8, right: 16, left: 0, bottom: 0 }}
            >
                <CartesianGrid
                    strokeDasharray="3 3"
                    className="stroke-border"
                />
                <XAxis
                    dataKey="dayLabel"
                    tick={{ fontSize: 10 }}
                    className="text-muted-foreground"
                    interval="preserveStartEnd"
                />
                <YAxis
                    tick={{ fontSize: 11 }}
                    className="text-muted-foreground"
                />
                <Tooltip
                    formatter={(value: any, name: any) => [
                        formatAmount(Number(value)),
                        String(name).charAt(0).toUpperCase() +
                            String(name).slice(1),
                    ]}
                    labelFormatter={(label) => String(label)}
                />
                <Legend />
                <Bar
                    dataKey="income"
                    fill="var(--color-chart-3)"
                    name="Income"
                    barSize={8}
                    radius={[2, 2, 0, 0]}
                />
                <Bar
                    dataKey="expense"
                    fill="var(--color-chart-1)"
                    name="Expense"
                    barSize={8}
                    radius={[2, 2, 0, 0]}
                />
                <Line
                    type="monotone"
                    dataKey="net"
                    stroke="var(--color-chart-4)"
                    strokeWidth={2}
                    dot={false}
                    name="Net"
                />
            </ComposedChart>
        </ResponsiveContainer>
    );
}

function UpcomingBillsSection({ bills }: { bills: UpcomingBill[] }) {
    if (bills.length === 0) {
        return (
            <EmptyState
                icon={<Calendar className="size-6" />}
                title="No upcoming bills"
                description="No recurring transactions due in the next 3 months."
            />
        );
    }

    return (
        <>
            {/* Mobile card list */}
            <div className="divide-y sm:hidden">
                {bills.map((bill) => (
                    <div
                        key={bill.id}
                        className="flex items-center justify-between gap-3 py-2.5"
                    >
                        <div className="min-w-0">
                            <p className="truncate text-sm font-medium">
                                {bill.name}
                            </p>
                            <p className="text-xs text-muted-foreground">
                                {formatDate(bill.next_due_date)}
                                {bill.account_name
                                    ? ` - ${bill.account_name}`
                                    : ''}
                            </p>
                        </div>
                        <span
                            className={`shrink-0 text-sm font-semibold tabular-nums ${
                                bill.transaction_type === 'income'
                                    ? 'text-foreground'
                                    : 'text-red-500'
                            }`}
                        >
                            {bill.transaction_type === 'expense' ? '-' : '+'}
                            {formatAbsAmount(bill.amount)}
                        </span>
                    </div>
                ))}
            </div>

            <Table className="hidden sm:table">
                <TableHeader>
                    <TableRow>
                        <TableHead>Name</TableHead>
                        <TableHead>Due date</TableHead>
                        <TableHead>Account</TableHead>
                        <TableHead className="text-right">Amount</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    {bills.map((bill) => (
                        <TableRow key={bill.id}>
                            <TableCell className="font-medium">
                                {bill.name}
                            </TableCell>
                            <TableCell className="text-muted-foreground">
                                {formatDate(bill.next_due_date)}
                            </TableCell>
                            <TableCell className="text-muted-foreground">
                                {bill.account_name ?? '-'}
                            </TableCell>
                            <TableCell
                                className={`text-right font-semibold tabular-nums ${
                                    bill.transaction_type === 'income'
                                        ? 'text-foreground'
                                        : 'text-red-500'
                                }`}
                            >
                                {bill.transaction_type === 'expense'
                                    ? '-'
                                    : '+'}
                                {formatAbsAmount(bill.amount)}
                            </TableCell>
                        </TableRow>
                    ))}
                </TableBody>
            </Table>
        </>
    );
}

// ─── Skeleton ────────────────────────────────────────────────────────────────

function CashFlowSkeleton() {
    return (
        <div className="space-y-6">
            <div className="grid gap-4 sm:grid-cols-3">
                {[1, 2, 3].map((i) => (
                    <Card key={i}>
                        <CardContent className="pt-6">
                            <Skeleton className="mb-2 h-3 w-24 rounded" />
                            <Skeleton className="h-8 w-32 rounded" />
                        </CardContent>
                    </Card>
                ))}
            </div>
            <Card>
                <CardHeader>
                    <CardTitle>Daily cash flow</CardTitle>
                </CardHeader>
                <CardContent>
                    <Skeleton className="h-[300px] w-full rounded" />
                </CardContent>
            </Card>
            <Card>
                <CardHeader>
                    <CardTitle>Upcoming recurring transactions</CardTitle>
                </CardHeader>
                <CardContent>
                    <Skeleton className="h-[120px] w-full rounded" />
                </CardContent>
            </Card>
        </div>
    );
}

// ─── Page ────────────────────────────────────────────────────────────────────

export default function CashFlowPage() {
    const { currentLedger, cashFlow } = usePage<{
        cashFlow?: {
            daily_cash_flow: DailyCashFlowEntry[];
            upcoming_bills: UpcomingBill[];
            period_label: string;
        };
    }>().props;
    const ledger = currentLedger!;

    const dailyCashFlow = cashFlow?.daily_cash_flow ?? [];
    const upcomingBills = cashFlow?.upcoming_bills ?? [];
    const periodLabel = cashFlow?.period_label ?? 'current period';

    // Quick summary stats
    const totalIncome = dailyCashFlow.reduce((sum, d) => sum + d.income, 0);
    const totalExpense = dailyCashFlow.reduce((sum, d) => sum + d.expense, 0);
    const netFlow = totalIncome - totalExpense;

    const breadcrumbs: BreadcrumbItem[] = [
        { title: ledger.name, href: ledgerDashboard.url(ledger.id) },
        { title: 'Reports', href: reportsIndex.url(ledger.id) },
        { title: 'Cash Flow', href: cashFlowRoute.url(ledger.id) },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`${ledger.name} - Cash Flow`} />

            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6 lg:p-8">
                {/* Header */}
                <div className="space-y-3">
                    <div className="flex items-center justify-between">
                        <Heading
                            title="Reports"
                            description={`Cash flow for ${periodLabel}.`}
                        />
                        <div className="hidden items-center gap-2 sm:flex">
                            <ReportViewSelect
                                ledgerId={ledger.id}
                                currentView="cash-flow"
                            />
                            <Button variant="outline" size="sm" asChild>
                                <a
                                    href={`/ledgers/${ledger.id}/reports/export-pdf`}
                                >
                                    Export PDF
                                </a>
                            </Button>
                        </div>
                    </div>
                    <div className="flex items-center gap-2 sm:hidden">
                        <div className="min-w-0 flex-1">
                            <ReportViewSelect
                                ledgerId={ledger.id}
                                currentView="cash-flow"
                            />
                        </div>
                        <Button
                            variant="outline"
                            size="sm"
                            className="w-1/4 shrink-0"
                            asChild
                        >
                            <a
                                href={`/ledgers/${ledger.id}/reports/export-pdf`}
                            >
                                Export PDF
                            </a>
                        </Button>
                    </div>
                </div>

                <Deferred data="cashFlow" fallback={<CashFlowSkeleton />}>
                    {/* Summary cards */}
                    <div className="grid gap-4 sm:grid-cols-3">
                        <Card>
                            <CardContent className="pt-6">
                                <p className="text-xs font-medium text-muted-foreground uppercase">
                                    Total inflow
                                </p>
                                <p className="mt-2 text-2xl font-semibold text-foreground tabular-nums">
                                    {formatAbsAmount(totalIncome)}
                                </p>
                            </CardContent>
                        </Card>
                        <Card>
                            <CardContent className="pt-6">
                                <p className="text-xs font-medium text-muted-foreground uppercase">
                                    Total outflow
                                </p>
                                <p className="mt-2 text-2xl font-semibold text-red-500 tabular-nums">
                                    {formatAbsAmount(totalExpense)}
                                </p>
                            </CardContent>
                        </Card>
                        <Card>
                            <CardContent className="pt-6">
                                <p className="text-xs font-medium text-muted-foreground uppercase">
                                    Net cash flow
                                </p>
                                <p
                                    className={`mt-2 text-2xl font-semibold tabular-nums ${
                                        netFlow >= 0
                                            ? 'text-foreground'
                                            : 'text-red-500'
                                    }`}
                                >
                                    {netFlow < 0 ? '-' : '+'}
                                    {formatAbsAmount(Math.abs(netFlow))}
                                </p>
                            </CardContent>
                        </Card>
                    </div>

                    {/* Daily cash flow chart */}
                    <Card>
                        <CardHeader>
                            <CardTitle>Daily cash flow</CardTitle>
                        </CardHeader>
                        <CardContent className="min-w-0 overflow-hidden">
                            <DailyCashFlowChart data={dailyCashFlow} />
                        </CardContent>
                    </Card>

                    {/* Upcoming bills */}
                    <Card>
                        <CardHeader>
                            <CardTitle>
                                Upcoming recurring transactions
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <UpcomingBillsSection bills={upcomingBills} />
                        </CardContent>
                    </Card>
                </Deferred>
            </div>
        </AppLayout>
    );
}
