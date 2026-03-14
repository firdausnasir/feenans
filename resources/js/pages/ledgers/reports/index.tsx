import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import {
    Bar,
    CartesianGrid,
    Cell,
    ComposedChart,
    Legend,
    Line,
    Pie,
    PieChart,
    ResponsiveContainer,
    Tooltip,
    XAxis,
    YAxis,
} from 'recharts';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import AppLayout from '@/layouts/app-layout';
import { formatAbsAmount, formatDate } from '@/lib/format';
import { dashboard as ledgerDashboard } from '@/routes/ledgers';
import { index as reportsIndex } from '@/routes/ledgers/reports';
import type { Account, BreadcrumbItem, Ledger } from '@/types';

// ─── Types ───────────────────────────────────────────────────────────────────

type MonthlyTrend = {
    month: string;
    income: number;
    expense: number;
    net: number;
};

type CategoryBreakdownItem = {
    id: number;
    name: string;
    color: string | null;
    total: number;
    percentage: number;
    parent_id: number | null;
    children: ChildCategory[];
};

type ChildCategory = {
    id: number;
    name: string;
    color: string | null;
    total: number;
    percentage: number;
};

type ParentCategory = {
    id: number;
    name: string;
    color: string | null;
    total: number;
    percentage: number;
    parent_id: null;
    children: ChildCategory[];
};

type CategoryBreakdownResponse = {
    items: CategoryBreakdownItem[];
    parents: ParentCategory[];
};

type StatementCycle = {
    account_id: number;
    account_name: string;
    start_date: string;
    end_date: string;
    total: number;
};

type DateRange = {
    date_from: string;
    date_to: string;
    preset: string;
    account_id: string | null;
};

type ReportAccount = { id: number; name: string };

type PayeeBreakdownItem = {
    id: number | null;
    name: string;
    total: number;
    percentage: number;
};

type CreditAccount = Pick<Account, 'id' | 'name' | 'statement_day'>;

// ─── Helpers ─────────────────────────────────────────────────────────────────

function toDateString(date: Date): string {
    const y = date.getFullYear();
    const m = String(date.getMonth() + 1).padStart(2, '0');
    const d = String(date.getDate()).padStart(2, '0');

    return `${y}-${m}-${d}`;
}

/** Compute cycle bounds for a reference date, given the ledger cycle start day */
function getCycleBounds(
    referenceDate: Date,
    cycleStartDay: number,
): { start: Date; end: Date } {
    const day = referenceDate.getDate();
    let start: Date;

    if (day >= cycleStartDay) {
        start = new Date(
            referenceDate.getFullYear(),
            referenceDate.getMonth(),
            cycleStartDay,
        );
    } else {
        const prevYear =
            referenceDate.getMonth() === 0
                ? referenceDate.getFullYear() - 1
                : referenceDate.getFullYear();
        const prevMonth =
            referenceDate.getMonth() === 0 ? 11 : referenceDate.getMonth() - 1;
        const maxDay = new Date(prevYear, prevMonth + 1, 0).getDate();
        start = new Date(prevYear, prevMonth, Math.min(cycleStartDay, maxDay));
    }

    // end = start + 1 month - 1 day
    const endRaw = new Date(
        start.getFullYear(),
        start.getMonth() + 1,
        start.getDate() - 1,
    );

    return { start, end: endRaw };
}

/** Go back N cycle months from the current cycle start */
function goBackNCycles(
    currentStart: Date,
    n: number,
    cycleStartDay: number,
): Date {
    let d = currentStart;

    for (let i = 0; i < n; i++) {
        // go one day before current start to land in previous cycle
        const prev = new Date(d);
        prev.setDate(prev.getDate() - 1);
        d = getCycleBounds(prev, cycleStartDay).start;
    }

    return d;
}

type Preset = {
    key: string;
    label: string;
    compute: (
        today: Date,
        csd: number,
    ) => { date_from: string; date_to: string };
};

const PRESETS: Preset[] = [
    {
        key: 'this_month',
        label: 'This month',
        compute: (today, csd) => {
            const { start, end } = getCycleBounds(today, csd);

            return {
                date_from: toDateString(start),
                date_to: toDateString(end),
            };
        },
    },
    {
        key: 'last_month',
        label: 'Last month',
        compute: (today, csd) => {
            const { start: curStart, end } = getCycleBounds(today, csd);
            const start = goBackNCycles(curStart, 1, csd);

            return {
                date_from: toDateString(start),
                date_to: toDateString(end),
            };
        },
    },
    {
        key: 'last_3_months',
        label: 'Last 3 months',
        compute: (today, csd) => {
            const { start: curStart, end: curEnd } = getCycleBounds(today, csd);
            const start = goBackNCycles(curStart, 3, csd);

            return {
                date_from: toDateString(start),
                date_to: toDateString(curEnd),
            };
        },
    },
    {
        key: 'last_6_months',
        label: 'Last 6 months',
        compute: (today, csd) => {
            const { start: curStart, end: curEnd } = getCycleBounds(today, csd);
            const start = goBackNCycles(curStart, 6, csd);

            return {
                date_from: toDateString(start),
                date_to: toDateString(curEnd),
            };
        },
    },
    {
        key: 'this_year',
        label: 'This year',
        compute: (today, csd) => {
            const { end: curEnd } = getCycleBounds(today, csd);
            const janFirst = new Date(today.getFullYear(), 0, 1);
            const { start } = getCycleBounds(janFirst, csd);

            return {
                date_from: toDateString(start),
                date_to: toDateString(curEnd),
            };
        },
    },
];

// ─── Chart month label formatter ─────────────────────────────────────────────

function formatMonthLabel(month: string): string {
    // month is 'YYYY-MM'
    const [year, m] = month.split('-');
    const date = new Date(Number(year), Number(m) - 1, 1);
    const shortMonth = date.toLocaleDateString('en-MY', { month: 'short' });

    return `${shortMonth} ${String(year).slice(2)}`;
}

// ─── Chart colors ─────────────────────────────────────────────────────────────

const CHART_COLORS = [
    'var(--color-chart-1)',
    'var(--color-chart-2)',
    'var(--color-chart-3)',
    'var(--color-chart-4)',
    'var(--color-chart-5)',
];

function getCategoryColor(color: string | null, index: number): string {
    return color ?? CHART_COLORS[index % CHART_COLORS.length];
}

// ─── Components ───────────────────────────────────────────────────────────────

function DateRangeSelector({
    ledger,
    dateRange,
    allAccounts,
    compareEnabled,
    onCompareToggle,
}: {
    ledger: Ledger;
    dateRange: DateRange;
    allAccounts: ReportAccount[];
    compareEnabled: boolean;
    onCompareToggle: () => void;
}) {
    const today = new Date();
    const csd = ledger.cycle_start_day;

    const [customFrom, setCustomFrom] = useState(dateRange.date_from);
    const [customTo, setCustomTo] = useState(dateRange.date_to);

    function buildParams(
        overrides: Record<string, string | null> = {},
    ): Record<string, string> {
        const params: Record<string, string> = {
            date_from: overrides.date_from ?? dateRange.date_from,
            date_to: overrides.date_to ?? dateRange.date_to,
        };

        const accountId =
            'account_id' in overrides
                ? overrides.account_id
                : dateRange.account_id;

        if (accountId) {
            params.account_id = accountId;
        }

        return params;
    }

    function applyPreset(preset: Preset) {
        const range = preset.compute(today, csd);
        router.get(
            reportsIndex.url(ledger.id),
            buildParams({ date_from: range.date_from, date_to: range.date_to }),
            { preserveState: true },
        );
    }

    function applyCustomRange() {
        if (!customFrom || !customTo) {
            return;
        }

        router.get(
            reportsIndex.url(ledger.id),
            buildParams({ date_from: customFrom, date_to: customTo }),
            { preserveState: true },
        );
    }

    function handleAccountChange(value: string) {
        const accountId = value === 'all' ? null : value;
        router.get(
            reportsIndex.url(ledger.id),
            buildParams({ account_id: accountId }),
            { preserveState: true },
        );
    }

    return (
        <Card>
            <CardContent className="pt-6">
                <div className="flex flex-wrap items-center gap-2">
                    {PRESETS.map((preset) => (
                        <Button
                            key={preset.key}
                            size="sm"
                            variant={
                                dateRange.preset === preset.key
                                    ? 'default'
                                    : 'outline'
                            }
                            onClick={() => applyPreset(preset)}
                        >
                            {preset.label}
                        </Button>
                    ))}

                    <Button
                        size="sm"
                        variant={compareEnabled ? 'default' : 'outline'}
                        onClick={onCompareToggle}
                    >
                        Compare
                    </Button>

                    <div className="ml-auto flex items-end gap-2">
                        {allAccounts.length > 0 && (
                            <div className="grid gap-1">
                                <Label className="text-xs">Account</Label>
                                <Select
                                    value={dateRange.account_id ?? 'all'}
                                    onValueChange={handleAccountChange}
                                >
                                    <SelectTrigger className="h-8 w-40 text-xs">
                                        <SelectValue placeholder="All accounts" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">
                                            All accounts
                                        </SelectItem>
                                        {allAccounts.map((account) => (
                                            <SelectItem
                                                key={account.id}
                                                value={account.id.toString()}
                                            >
                                                {account.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                        )}
                        <div className="grid gap-1">
                            <Label className="text-xs">From</Label>
                            <Input
                                type="date"
                                value={customFrom}
                                onChange={(e) => setCustomFrom(e.target.value)}
                                className="h-8 text-xs"
                            />
                        </div>
                        <div className="grid gap-1">
                            <Label className="text-xs">To</Label>
                            <Input
                                type="date"
                                value={customTo}
                                onChange={(e) => setCustomTo(e.target.value)}
                                className="h-8 text-xs"
                            />
                        </div>
                        <Button size="sm" onClick={applyCustomRange}>
                            Apply
                        </Button>
                    </div>
                </div>
            </CardContent>
        </Card>
    );
}

function MonthlyTrendChart({ data }: { data: MonthlyTrend[] }) {
    const chartData = data.map((item) => ({
        ...item,
        monthLabel: formatMonthLabel(item.month),
    }));

    if (chartData.length === 0) {
        return (
            <div className="flex h-48 items-center justify-center text-sm text-muted-foreground">
                No data for this period.
            </div>
        );
    }

    return (
        <ResponsiveContainer width="100%" height={280}>
            <ComposedChart
                data={chartData}
                margin={{ top: 8, right: 16, left: 0, bottom: 0 }}
            >
                <CartesianGrid
                    strokeDasharray="3 3"
                    className="stroke-border"
                />
                <XAxis
                    dataKey="monthLabel"
                    tick={{ fontSize: 11 }}
                    className="text-muted-foreground"
                />
                <YAxis
                    tick={{ fontSize: 11 }}
                    className="text-muted-foreground"
                />
                <Tooltip
                    formatter={(value: any, name: any) => [
                        formatAbsAmount(Number(value)),
                        String(name).charAt(0).toUpperCase() +
                            String(name).slice(1),
                    ]}
                />
                <Legend />
                <Bar
                    dataKey="income"
                    fill="var(--color-chart-3)"
                    name="Income"
                    barSize={16}
                />
                <Bar
                    dataKey="expense"
                    fill="var(--color-chart-1)"
                    name="Expense"
                    barSize={16}
                />
                <Line
                    type="monotone"
                    dataKey="net"
                    stroke="var(--color-chart-4)"
                    strokeWidth={2}
                    dot={{ r: 3 }}
                    name="Net"
                />
            </ComposedChart>
        </ResponsiveContainer>
    );
}

function CategoryBreakdownSection({
    data,
}: {
    data: CategoryBreakdownResponse;
}) {
    const [showSubcategories, setShowSubcategories] = useState(false);

    const isEmpty = data.items.length === 0 && data.parents.length === 0;

    // When showing subcategories, use items (individual categories).
    // When showing parent-only, use parents (pre-aggregated with children).
    const displayData = showSubcategories ? data.items : data.parents;

    const pieData = displayData.map((item, index) => ({
        name: item.name,
        value: item.total,
        color: getCategoryColor(item.color, index),
    }));

    if (isEmpty) {
        return (
            <div className="flex h-48 items-center justify-center text-sm text-muted-foreground">
                No expense categories for this period.
            </div>
        );
    }

    return (
        <div className="space-y-4">
            <div className="flex items-center justify-between">
                <Button
                    size="sm"
                    variant="outline"
                    onClick={() => setShowSubcategories((prev) => !prev)}
                >
                    {showSubcategories ? 'Parent only' : 'Show subcategories'}
                </Button>
            </div>

            <div className="flex flex-col items-center gap-6 lg:flex-row lg:items-start">
                {/* Donut chart */}
                <div className="shrink-0">
                    <PieChart width={200} height={200}>
                        <Pie
                            data={pieData}
                            cx={100}
                            cy={100}
                            innerRadius={55}
                            outerRadius={90}
                            dataKey="value"
                            nameKey="name"
                        >
                            {pieData.map((entry, index) => (
                                <Cell
                                    key={`cell-${index}`}
                                    fill={entry.color}
                                />
                            ))}
                        </Pie>
                        <Tooltip
                            formatter={(value: any) => [
                                formatAbsAmount(Number(value)),
                                'Amount',
                            ]}
                        />
                    </PieChart>
                </div>

                {/* Table */}
                <div className="w-full">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Category</TableHead>
                                <TableHead className="text-right">
                                    Amount
                                </TableHead>
                                <TableHead className="text-right">%</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {displayData.map((item, index) => (
                                <TableRow key={`${item.id}-${index}`}>
                                    <TableCell>
                                        <div className="flex items-center gap-2">
                                            <span
                                                className="inline-block size-2.5 shrink-0 rounded-full"
                                                style={{
                                                    backgroundColor:
                                                        getCategoryColor(
                                                            item.color,
                                                            index,
                                                        ),
                                                }}
                                            />
                                            <span
                                                className={
                                                    item.parent_id !== null
                                                        ? 'pl-3 text-muted-foreground'
                                                        : ''
                                                }
                                            >
                                                {item.name}
                                            </span>
                                        </div>
                                    </TableCell>
                                    <TableCell className="text-right text-red-500 tabular-nums">
                                        {formatAbsAmount(item.total)}
                                    </TableCell>
                                    <TableCell className="text-right text-muted-foreground tabular-nums">
                                        {item.percentage.toFixed(1)}%
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>

                    {/* Show children under each parent when in parent-only mode */}
                    {!showSubcategories &&
                        data.parents.some((p) => p.children.length > 0) && (
                            <div className="mt-4 space-y-3 border-t pt-4">
                                <p className="text-xs font-medium text-muted-foreground uppercase">
                                    Breakdown by subcategory
                                </p>
                                {data.parents
                                    .filter((p) => p.children.length > 0)
                                    .map((parent, parentIndex) => (
                                        <div
                                            key={parent.id}
                                            className="space-y-1"
                                        >
                                            <p className="text-sm font-medium">
                                                {parent.name}
                                            </p>
                                            {parent.children.map(
                                                (child, childIndex) => (
                                                    <div
                                                        key={child.id}
                                                        className="flex items-center justify-between pl-4 text-sm"
                                                    >
                                                        <div className="flex items-center gap-2">
                                                            <span
                                                                className="inline-block size-2 shrink-0 rounded-full"
                                                                style={{
                                                                    backgroundColor:
                                                                        getCategoryColor(
                                                                            child.color,
                                                                            parentIndex *
                                                                                10 +
                                                                                childIndex,
                                                                        ),
                                                                }}
                                                            />
                                                            <span className="text-muted-foreground">
                                                                {child.name}
                                                            </span>
                                                        </div>
                                                        <div className="flex items-center gap-3">
                                                            <span className="text-red-500 tabular-nums">
                                                                {formatAbsAmount(
                                                                    child.total,
                                                                )}
                                                            </span>
                                                            <span className="w-12 text-right text-muted-foreground tabular-nums">
                                                                {child.percentage.toFixed(
                                                                    1,
                                                                )}
                                                                %
                                                            </span>
                                                        </div>
                                                    </div>
                                                ),
                                            )}
                                        </div>
                                    ))}
                            </div>
                        )}
                </div>
            </div>
        </div>
    );
}

function StatementCyclesSection({
    cycles,
    creditAccounts,
}: {
    cycles: StatementCycle[];
    creditAccounts: CreditAccount[];
}) {
    const [selectedAccountId, setSelectedAccountId] = useState<string>(
        creditAccounts[0]?.id?.toString() ?? 'all',
    );

    const filteredCycles =
        selectedAccountId !== 'all'
            ? cycles.filter((c) => c.account_id === Number(selectedAccountId))
            : cycles;

    // Sort cycles by start_date descending
    const sortedCycles = [...filteredCycles].sort(
        (a, b) =>
            new Date(b.start_date).getTime() - new Date(a.start_date).getTime(),
    );

    return (
        <div className="space-y-4">
            {creditAccounts.length > 1 && (
                <div className="flex items-center gap-3">
                    <Label className="text-sm font-medium">Account</Label>
                    <Select
                        value={selectedAccountId}
                        onValueChange={setSelectedAccountId}
                    >
                        <SelectTrigger className="w-48">
                            <SelectValue placeholder="Select account" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All accounts</SelectItem>
                            {creditAccounts.map((acc) => (
                                <SelectItem
                                    key={acc.id}
                                    value={acc.id.toString()}
                                >
                                    {acc.name}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>
            )}

            {sortedCycles.length === 0 ? (
                <p className="text-sm text-muted-foreground">
                    No statement cycles found.
                </p>
            ) : (
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Cycle start</TableHead>
                            <TableHead>Cycle end</TableHead>
                            {creditAccounts.length > 1 && (
                                <TableHead>Account</TableHead>
                            )}
                            <TableHead className="text-right">Total</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {sortedCycles.map((cycle, i) => (
                            <TableRow key={i}>
                                <TableCell className="text-muted-foreground">
                                    {formatDate(cycle.start_date)}
                                </TableCell>
                                <TableCell className="text-muted-foreground">
                                    {formatDate(cycle.end_date)}
                                </TableCell>
                                {creditAccounts.length > 1 && (
                                    <TableCell>{cycle.account_name}</TableCell>
                                )}
                                <TableCell
                                    className={`text-right font-semibold tabular-nums ${
                                        cycle.total >= 0
                                            ? 'text-green-600 dark:text-green-400'
                                            : 'text-red-500'
                                    }`}
                                >
                                    {cycle.total < 0 ? '–' : ''}
                                    {formatAbsAmount(Math.abs(cycle.total))}
                                </TableCell>
                            </TableRow>
                        ))}
                    </TableBody>
                </Table>
            )}
        </div>
    );
}

function PayeeBreakdownSection({ data }: { data: PayeeBreakdownItem[] }) {
    if (data.length === 0) {
        return (
            <div className="flex h-48 items-center justify-center text-sm text-muted-foreground">
                No payee data for this period.
            </div>
        );
    }

    return (
        <Table>
            <TableHeader>
                <TableRow>
                    <TableHead>Payee</TableHead>
                    <TableHead className="text-right">Amount</TableHead>
                    <TableHead className="text-right">%</TableHead>
                </TableRow>
            </TableHeader>
            <TableBody>
                {data.map((item, index) => (
                    <TableRow key={`payee-${item.id ?? 'none'}-${index}`}>
                        <TableCell>
                            <span
                                className={
                                    item.id === null
                                        ? 'text-muted-foreground italic'
                                        : ''
                                }
                            >
                                {item.name}
                            </span>
                        </TableCell>
                        <TableCell className="text-right text-red-500 tabular-nums">
                            {formatAbsAmount(item.total)}
                        </TableCell>
                        <TableCell className="text-right text-muted-foreground tabular-nums">
                            {item.percentage.toFixed(1)}%
                        </TableCell>
                    </TableRow>
                ))}
            </TableBody>
        </Table>
    );
}

// ─── Page ─────────────────────────────────────────────────────────────────────

export default function ReportsIndex({
    ledger,
    monthlyTrend,
    categoryBreakdown,
    payeeBreakdown,
    statementCycles,
    creditAccounts,
    allAccounts,
    dateRange,
}: {
    ledger: Ledger;
    monthlyTrend: MonthlyTrend[];
    categoryBreakdown: CategoryBreakdownResponse;
    payeeBreakdown: PayeeBreakdownItem[];
    statementCycles: StatementCycle[];
    creditAccounts: CreditAccount[];
    allAccounts: ReportAccount[];
    dateRange: DateRange;
}) {
    const [compareEnabled, setCompareEnabled] = useState(false);

    const breadcrumbs: BreadcrumbItem[] = [
        { title: ledger.name, href: ledgerDashboard.url(ledger.id) },
        { title: 'Reports', href: reportsIndex.url(ledger.id) },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`${ledger.name} reports`} />

            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                {/* Header */}
                <div>
                    <h1 className="text-2xl font-semibold tracking-tight">
                        Reports
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Monthly trends, category totals, and credit statement
                        cycles.
                    </p>
                </div>

                {/* Date range selector */}
                <DateRangeSelector
                    ledger={ledger}
                    dateRange={dateRange}
                    allAccounts={allAccounts}
                    compareEnabled={compareEnabled}
                    onCompareToggle={() => setCompareEnabled((prev) => !prev)}
                />

                {/* Period comparison placeholder */}
                {compareEnabled && (
                    <Card>
                        <CardContent className="flex items-center justify-center py-8">
                            <p className="text-sm text-muted-foreground">
                                Period comparison coming soon.
                            </p>
                        </CardContent>
                    </Card>
                )}

                {/* Two-column layout on large screens */}
                <div className="grid gap-6 lg:grid-cols-[1.4fr,1fr]">
                    {/* Monthly trend */}
                    <Card>
                        <CardHeader>
                            <CardTitle>Monthly trend</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <MonthlyTrendChart data={monthlyTrend} />
                        </CardContent>
                    </Card>

                    {/* Category breakdown */}
                    <Card>
                        <CardHeader>
                            <CardTitle>Category breakdown</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <CategoryBreakdownSection
                                data={categoryBreakdown}
                            />
                        </CardContent>
                    </Card>
                </div>

                {/* Payee breakdown */}
                <Card>
                    <CardHeader>
                        <CardTitle>Payee breakdown</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <PayeeBreakdownSection data={payeeBreakdown} />
                    </CardContent>
                </Card>

                {/* Credit statement cycles */}
                {creditAccounts.length > 0 && (
                    <Card>
                        <CardHeader>
                            <CardTitle>Statement cycles</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <StatementCyclesSection
                                cycles={statementCycles}
                                creditAccounts={creditAccounts}
                            />
                        </CardContent>
                    </Card>
                )}
            </div>
        </AppLayout>
    );
}
