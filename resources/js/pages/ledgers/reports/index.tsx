import { Head, usePage } from '@inertiajs/react';
import {
    ArrowDown,
    ArrowUp,
    BarChart3,
    Minus,
    SlidersHorizontal,
    ChevronDown,
} from 'lucide-react';
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
import { toast } from 'sonner';
import Heading from '@/components/heading';
import { ReportViewSelect } from '@/components/report-view-select';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { DatePicker } from '@/components/ui/date-picker';
import { EmptyState } from '@/components/ui/empty-state';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Skeleton } from '@/components/ui/skeleton';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { useApiQuery } from '@/hooks/use-api-query';
import AppLayout from '@/layouts/app-layout';
import { formatAbsAmount, formatDate } from '@/lib/format';
import { dashboard as ledgerDashboard } from '@/routes/ledgers';
import { index as reportsIndex } from '@/routes/ledgers/reports';
import type { BreadcrumbItem } from '@/types';

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

type ReportAccount = { id: number; name: string };

type PayeeBreakdownItem = {
    id: number | null;
    name: string;
    total: number;
    percentage: number;
};

type HeatmapDay = {
    date: string;
    amount: number;
};

type CategoryDelta = {
    name: string;
    current: number;
    previous: number;
    delta: number;
    percentage_change: number;
};

type TrendOverlayItem = {
    index: number;
    current_month: string | null;
    compare_month: string | null;
    current_expense: number;
    compare_expense: number;
    current_income: number;
    compare_income: number;
};

type ComparisonSummary = {
    current_expense: number;
    compare_expense: number;
    expense_delta: number;
    expense_percentage_change: number;
    current_income: number;
    compare_income: number;
    income_delta: number;
    income_percentage_change: number;
    biggest_change: CategoryDelta | null;
};

type ComparisonData = {
    current_period: { from: string; to: string };
    compare_period: { from: string; to: string };
    categoryDeltas: CategoryDelta[];
    trendOverlay: TrendOverlayItem[];
    summary: ComparisonSummary;
};

type SpendingReportResponse = {
    data: {
        monthly_trends: MonthlyTrend[];
        category_breakdown: CategoryBreakdownResponse;
        payee_breakdown: PayeeBreakdownItem[];
        income_category_breakdown: CategoryBreakdownResponse;
        income_payee_breakdown: PayeeBreakdownItem[];
        spending_heatmap: HeatmapDay[];
        summary: {
            total_income: number;
            total_expense: number;
            net: number;
            transaction_count: number;
        };
        date_range: {
            date_from: string;
            date_to: string;
            preset: string;
            account_id: string | null;
        };
        all_accounts: ReportAccount[];
        comparison: ComparisonData | null;
    };
};

type Filters = {
    date_from: string;
    date_to: string;
    preset: string;
    account_id: string | null;
    compare_start: string | null;
    compare_end: string | null;
};

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
        label: '3 months',
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
        label: '6 months',
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

// ─── Skeleton Components ─────────────────────────────────────────────────────

function ChartSkeleton() {
    return (
        <div className="space-y-3">
            <Skeleton className="h-[250px] w-full rounded" />
        </div>
    );
}

function TableSkeleton({ rows = 5 }: { rows?: number }) {
    return (
        <div className="space-y-2">
            {Array.from({ length: rows }).map((_, i) => (
                <Skeleton key={i} className="h-8 w-full rounded" />
            ))}
        </div>
    );
}

function SummaryCardsSkeleton() {
    return (
        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            {Array.from({ length: 3 }).map((_, i) => (
                <Card key={i}>
                    <CardContent className="pt-6">
                        <Skeleton className="mb-2 h-3 w-24 rounded" />
                        <Skeleton className="h-8 w-32 rounded" />
                    </CardContent>
                </Card>
            ))}
        </div>
    );
}

// ─── Components ───────────────────────────────────────────────────────────────

function DateRangeSelector({
    cycleStartDay,
    filters,
    allAccounts,
    compareEnabled,
    onCompareToggle,
    onFiltersChange,
}: {
    cycleStartDay: number;
    filters: Filters;
    allAccounts: ReportAccount[];
    compareEnabled: boolean;
    onCompareToggle: () => void;
    onFiltersChange: (newFilters: Partial<Filters>) => void;
}) {
    const today = new Date();
    const csd = cycleStartDay;

    const [customFrom, setCustomFrom] = useState(filters.date_from);
    const [customTo, setCustomTo] = useState(filters.date_to);
    const [compareFrom, setCompareFrom] = useState(
        filters.compare_start ?? '',
    );
    const [compareTo, setCompareTo] = useState(filters.compare_end ?? '');

    function applyPreset(preset: Preset) {
        const range = preset.compute(today, csd);
        onFiltersChange({
            date_from: range.date_from,
            date_to: range.date_to,
            preset: preset.key,
        });
    }

    function applyCustomRange() {
        if (!customFrom || !customTo) {
            return;
        }

        onFiltersChange({
            date_from: customFrom,
            date_to: customTo,
            preset: 'custom',
        });
    }

    function applyComparison() {
        if (!compareFrom || !compareTo) {
            return;
        }

        onFiltersChange({
            compare_start: compareFrom,
            compare_end: compareTo,
        });
    }

    function clearComparison() {
        onFiltersChange({
            compare_start: null,
            compare_end: null,
        });
    }

    function handleCompareToggle() {
        if (compareEnabled && filters.compare_start) {
            clearComparison();
        }

        onCompareToggle();
    }

    function handleAccountChange(value: string) {
        const accountId = value === 'all' ? null : value;
        onFiltersChange({ account_id: accountId });
    }

    /** Suggest the previous period with same duration as the current period */
    function suggestPreviousPeriod() {
        const from = new Date(filters.date_from + 'T00:00:00');
        const to = new Date(filters.date_to + 'T00:00:00');
        const durationMs = to.getTime() - from.getTime();
        const prevEnd = new Date(from.getTime() - 86400000); // day before current start
        const prevStart = new Date(prevEnd.getTime() - durationMs);
        setCompareFrom(toDateString(prevStart));
        setCompareTo(toDateString(prevEnd));
    }

    const [filtersOpen, setFiltersOpen] = useState(false);

    return (
        <Card
            className={filtersOpen ? '' : 'cursor-pointer'}
            onClick={() => {
                if (!filtersOpen) {
                    setFiltersOpen(true);
                }
            }}
        >
            <CardContent className="px-4 py-2">
                <div className="flex flex-col gap-3">
                    {/* Filter toggle */}
                    <button
                        type="button"
                        className="flex w-full items-center justify-between"
                        onClick={(e) => {
                            e.stopPropagation();
                            setFiltersOpen(!filtersOpen);
                        }}
                    >
                        <div className="flex min-w-0 items-center gap-2">
                            <SlidersHorizontal className="size-4 shrink-0 text-muted-foreground" />
                            <span className="truncate text-sm font-medium">
                                {[
                                    `${formatDate(filters.date_from)} – ${formatDate(filters.date_to)}`,
                                    ...(filters.account_id
                                        ? [
                                              allAccounts.find(
                                                  (a) =>
                                                      a.id.toString() ===
                                                      filters.account_id,
                                              )?.name ?? 'Account',
                                          ]
                                        : []),
                                    ...(filters.compare_start &&
                                    filters.compare_end
                                        ? [
                                              `vs ${formatDate(filters.compare_start)} – ${formatDate(filters.compare_end)}`,
                                          ]
                                        : []),
                                ].join(' · ')}
                            </span>
                        </div>
                        <ChevronDown
                            className={`size-4 text-muted-foreground transition-transform ${filtersOpen ? 'rotate-180' : ''}`}
                        />
                    </button>

                    <div className={`space-y-3 ${filtersOpen ? '' : 'hidden'}`}>
                        <div className="flex flex-wrap items-center gap-1.5">
                            {PRESETS.map((preset) => (
                                <Button
                                    key={preset.key}
                                    size="sm"
                                    variant={
                                        filters.preset === preset.key
                                            ? 'default'
                                            : 'outline'
                                    }
                                    className="h-7 px-2.5 text-xs"
                                    onClick={() => applyPreset(preset)}
                                >
                                    {preset.label}
                                </Button>
                            ))}

                            <Button
                                size="sm"
                                variant={compareEnabled ? 'default' : 'outline'}
                                className="h-7 px-2.5 text-xs"
                                onClick={handleCompareToggle}
                            >
                                Compare
                            </Button>
                        </div>

                        <div className="flex flex-col gap-2 sm:flex-row sm:flex-wrap sm:items-end">
                            {allAccounts.length > 0 && (
                                <div className="grid gap-1">
                                    <Label className="text-xs">Account</Label>
                                    <Select
                                        value={filters.account_id ?? 'all'}
                                        onValueChange={handleAccountChange}
                                    >
                                        <SelectTrigger className="h-8 w-full text-xs sm:w-40">
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
                            <div className="grid grid-cols-2 gap-2 sm:flex sm:items-end sm:gap-2">
                                <div className="grid gap-1">
                                    <Label className="text-xs">From</Label>
                                    <DatePicker
                                        value={customFrom}
                                        onChange={(date) => setCustomFrom(date)}
                                        placeholder="Start date"
                                        className="h-8 text-xs"
                                    />
                                </div>
                                <div className="grid gap-1">
                                    <Label className="text-xs">To</Label>
                                    <DatePicker
                                        value={customTo}
                                        onChange={(date) => setCustomTo(date)}
                                        placeholder="End date"
                                        className="h-8 text-xs"
                                    />
                                </div>
                            </div>
                            <Button
                                size="sm"
                                className="w-full sm:w-auto"
                                onClick={applyCustomRange}
                            >
                                Apply
                            </Button>
                        </div>

                        {/* Comparison date picker row */}
                        {compareEnabled && (
                            <div className="flex flex-col gap-2 border-t pt-3 sm:flex-row sm:flex-wrap sm:items-end">
                                <p className="text-xs font-medium text-muted-foreground sm:self-center">
                                    Compare with:
                                </p>
                                <div className="grid grid-cols-2 gap-2 sm:flex sm:items-end sm:gap-2">
                                    <div className="grid gap-1">
                                        <Label className="text-xs">From</Label>
                                        <DatePicker
                                            value={compareFrom}
                                            onChange={(date) =>
                                                setCompareFrom(date)
                                            }
                                            placeholder="Start date"
                                            className="h-8 text-xs"
                                        />
                                    </div>
                                    <div className="grid gap-1">
                                        <Label className="text-xs">To</Label>
                                        <DatePicker
                                            value={compareTo}
                                            onChange={(date) =>
                                                setCompareTo(date)
                                            }
                                            placeholder="End date"
                                            className="h-8 text-xs"
                                        />
                                    </div>
                                </div>
                                <div className="flex gap-2">
                                    <Button
                                        size="sm"
                                        className="flex-1 sm:flex-initial"
                                        onClick={applyComparison}
                                    >
                                        Compare
                                    </Button>
                                    <Button
                                        size="sm"
                                        variant="ghost"
                                        className="flex-1 sm:flex-initial"
                                        onClick={suggestPreviousPeriod}
                                    >
                                        Previous period
                                    </Button>
                                </div>
                            </div>
                        )}
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
            <EmptyState
                icon={<BarChart3 className="size-6" />}
                title="No data yet"
                description="Add some transactions first to see your spending insights."
            />
        );
    }

    return (
        <ResponsiveContainer width="100%" height={250}>
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
            <EmptyState
                icon={<BarChart3 className="size-6" />}
                title="No category data"
                description="No expense categories found for this period."
            />
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
                    {/* Mobile card list */}
                    <div className="divide-y sm:hidden">
                        {displayData.map((item, index) => (
                            <div
                                key={`${item.id}-${index}`}
                                className="flex items-center justify-between gap-3 py-2.5"
                            >
                                <div className="flex min-w-0 items-center gap-2">
                                    <span
                                        className="inline-block size-2.5 shrink-0 rounded-full"
                                        style={{
                                            backgroundColor: getCategoryColor(
                                                item.color,
                                                index,
                                            ),
                                        }}
                                    />
                                    <span
                                        className={`truncate text-sm ${
                                            item.parent_id !== null
                                                ? 'pl-3 text-muted-foreground'
                                                : 'font-medium'
                                        }`}
                                    >
                                        {item.name}
                                    </span>
                                </div>
                                <div className="flex shrink-0 items-center gap-3">
                                    <span className="text-sm text-red-500 tabular-nums">
                                        {formatAbsAmount(item.total)}
                                    </span>
                                    <span className="w-12 text-right text-xs text-muted-foreground tabular-nums">
                                        {item.percentage.toFixed(1)}%
                                    </span>
                                </div>
                            </div>
                        ))}
                    </div>

                    <Table className="hidden sm:table">
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

function PayeeBreakdownSection({
    data,
    amountClassName = 'text-red-500',
}: {
    data: PayeeBreakdownItem[];
    amountClassName?: string;
}) {
    if (data.length === 0) {
        return (
            <EmptyState
                icon={<BarChart3 className="size-6" />}
                title="No payee data"
                description="No payee data found for this period."
            />
        );
    }

    return (
        <>
            {/* Mobile card list */}
            <div className="divide-y sm:hidden">
                {data.map((item, index) => (
                    <div
                        key={`payee-${item.id ?? 'none'}-${index}`}
                        className="flex items-center justify-between gap-3 py-2.5"
                    >
                        <span
                            className={`min-w-0 truncate text-sm ${
                                item.id === null
                                    ? 'text-muted-foreground italic'
                                    : 'font-medium'
                            }`}
                        >
                            {item.name}
                        </span>
                        <div className="flex shrink-0 items-center gap-3">
                            <span
                                className={`text-sm tabular-nums ${amountClassName}`}
                            >
                                {formatAbsAmount(item.total)}
                            </span>
                            <span className="w-12 text-right text-xs text-muted-foreground tabular-nums">
                                {item.percentage.toFixed(1)}%
                            </span>
                        </div>
                    </div>
                ))}
            </div>

            <Table className="hidden sm:table">
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
                            <TableCell
                                className={`text-right tabular-nums ${amountClassName}`}
                            >
                                {formatAbsAmount(item.total)}
                            </TableCell>
                            <TableCell className="text-right text-muted-foreground tabular-nums">
                                {item.percentage.toFixed(1)}%
                            </TableCell>
                        </TableRow>
                    ))}
                </TableBody>
            </Table>
        </>
    );
}

function IncomeCategoryBreakdownSection({
    data,
}: {
    data: CategoryBreakdownResponse;
}) {
    const [showSubcategories, setShowSubcategories] = useState(false);

    const isEmpty = data.items.length === 0 && data.parents.length === 0;

    const displayData = showSubcategories ? data.items : data.parents;

    const pieData = displayData.map((item, index) => ({
        name: item.name,
        value: item.total,
        color: getCategoryColor(item.color, index),
    }));

    if (isEmpty) {
        return (
            <EmptyState
                icon={<BarChart3 className="size-6" />}
                title="No category data"
                description="No income categories found for this period."
            />
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
                    {/* Mobile card list */}
                    <div className="divide-y sm:hidden">
                        {displayData.map((item, index) => (
                            <div
                                key={`${item.id}-${index}`}
                                className="flex items-center justify-between gap-3 py-2.5"
                            >
                                <div className="flex min-w-0 items-center gap-2">
                                    <span
                                        className="inline-block size-2.5 shrink-0 rounded-full"
                                        style={{
                                            backgroundColor: getCategoryColor(
                                                item.color,
                                                index,
                                            ),
                                        }}
                                    />
                                    <span
                                        className={`truncate text-sm ${
                                            item.parent_id !== null
                                                ? 'pl-3 text-muted-foreground'
                                                : 'font-medium'
                                        }`}
                                    >
                                        {item.name}
                                    </span>
                                </div>
                                <div className="flex shrink-0 items-center gap-3">
                                    <span className="text-sm text-green-600 tabular-nums dark:text-green-400">
                                        {formatAbsAmount(item.total)}
                                    </span>
                                    <span className="w-12 text-right text-xs text-muted-foreground tabular-nums">
                                        {item.percentage.toFixed(1)}%
                                    </span>
                                </div>
                            </div>
                        ))}
                    </div>

                    <Table className="hidden sm:table">
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
                                    <TableCell className="text-right text-green-600 tabular-nums dark:text-green-400">
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
                                                            <span className="text-green-600 tabular-nums dark:text-green-400">
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

// ─── Spending Heatmap ─────────────────────────────────────────────────────────

function SpendingHeatmap({ data }: { data: HeatmapDay[] }) {
    if (data.length === 0) {
        return (
            <EmptyState
                icon={<BarChart3 className="size-6" />}
                title="No spending data"
                description="No transactions found for the heatmap."
            />
        );
    }

    const maxAmount = Math.max(...data.map((d) => d.amount), 1);

    function getIntensity(amount: number): string {
        if (amount === 0) {
            return 'bg-muted';
        }

        const ratio = amount / maxAmount;

        if (ratio < 0.2) {
            return 'bg-orange-200 dark:bg-orange-900/30';
        }

        if (ratio < 0.4) {
            return 'bg-orange-300 dark:bg-orange-800/40';
        }

        if (ratio < 0.6) {
            return 'bg-red-300 dark:bg-red-700/40';
        }

        if (ratio < 0.8) {
            return 'bg-red-400 dark:bg-red-600/50';
        }

        return 'bg-red-500 dark:bg-red-500/60';
    }

    // Build a lookup map for quick access
    const dayMap = new Map(data.map((d) => [d.date, d.amount]));

    // Determine date range and limit to last 3 months if too large
    const sortedDates = data
        .map((d) => new Date(d.date + 'T00:00:00'))
        .sort((a, b) => a.getTime() - b.getTime());
    const rawStart = sortedDates[0];
    const rawEnd = sortedDates[sortedDates.length - 1];

    // If range > 6 months, show only last 3 months
    const sixMonthsMs = 6 * 30 * 24 * 60 * 60 * 1000;
    const effectiveStart =
        rawEnd.getTime() - rawStart.getTime() > sixMonthsMs
            ? new Date(rawEnd.getFullYear(), rawEnd.getMonth() - 2, 1)
            : rawStart;

    // Group days by month
    type MonthData = {
        key: string;
        label: string;
        year: number;
        month: number;
        days: { day: number; amount: number; dateStr: string }[];
        firstDayOfWeek: number;
        totalDays: number;
    };

    const months: MonthData[] = [];
    const cursor = new Date(
        effectiveStart.getFullYear(),
        effectiveStart.getMonth(),
        1,
    );

    while (cursor <= rawEnd) {
        const year = cursor.getFullYear();
        const month = cursor.getMonth();
        const totalDays = new Date(year, month + 1, 0).getDate();
        const firstDayOfWeek = new Date(year, month, 1).getDay(); // 0=Sun
        const label = new Date(year, month, 1).toLocaleDateString('en-MY', {
            month: 'short',
            year: '2-digit',
        });
        const days: MonthData['days'] = [];

        for (let d = 1; d <= totalDays; d++) {
            const dateStr = `${year}-${String(month + 1).padStart(2, '0')}-${String(d).padStart(2, '0')}`;
            days.push({
                day: d,
                amount: dayMap.get(dateStr) ?? 0,
                dateStr,
            });
        }

        months.push({
            key: `${year}-${month}`,
            label,
            year,
            month,
            days,
            firstDayOfWeek,
            totalDays,
        });

        cursor.setMonth(cursor.getMonth() + 1);
    }

    const weekdayLabels = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];

    return (
        <div className="space-y-4">
            <div className="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                {months.map((m) => {
                    // Build a 6-row x 7-col grid (Mon-Sun)
                    // Convert Sunday-based firstDayOfWeek to Monday-based
                    const mondayStart =
                        m.firstDayOfWeek === 0 ? 6 : m.firstDayOfWeek - 1;
                    const cells: ({
                        day: number;
                        amount: number;
                        dateStr: string;
                    } | null)[] = [];

                    // Pad empty cells before the 1st
                    for (let i = 0; i < mondayStart; i++) {
                        cells.push(null);
                    }

                    // Add actual days
                    for (const day of m.days) {
                        cells.push(day);
                    }

                    // Pad to complete the last week
                    while (cells.length % 7 !== 0) {
                        cells.push(null);
                    }

                    // Build rows
                    const rows: (typeof cells)[] = [];

                    for (let i = 0; i < cells.length; i += 7) {
                        rows.push(cells.slice(i, i + 7));
                    }

                    return (
                        <div key={m.key}>
                            <p className="mb-2 text-xs font-semibold tracking-wider text-muted-foreground uppercase">
                                {m.label}
                            </p>
                            <div className="grid grid-cols-7 gap-1">
                                {weekdayLabels.map((wd) => (
                                    <div
                                        key={wd}
                                        className="flex h-5 items-center justify-center text-[10px] text-muted-foreground"
                                    >
                                        {wd.charAt(0)}
                                    </div>
                                ))}
                                {rows.flatMap((row, ri) =>
                                    row.map((cell, ci) =>
                                        cell ? (
                                            <div
                                                key={`${ri}-${ci}`}
                                                className={`flex h-5 w-full items-center justify-center rounded-sm text-[9px] font-medium ${getIntensity(cell.amount)} ${cell.amount > 0 ? 'text-foreground/70' : 'text-muted-foreground/50'}`}
                                                title={`${cell.dateStr}: ${formatAbsAmount(cell.amount)}`}
                                            >
                                                {cell.day}
                                            </div>
                                        ) : (
                                            <div
                                                key={`${ri}-${ci}`}
                                                className="h-5 w-full"
                                            />
                                        ),
                                    ),
                                )}
                            </div>
                        </div>
                    );
                })}
            </div>

            {/* Legend */}
            <div className="flex items-center gap-3 text-xs text-muted-foreground">
                <span>Less</span>
                <div className="flex gap-1">
                    <div className="h-4 w-4 rounded-sm bg-muted" />
                    <div className="h-4 w-4 rounded-sm bg-orange-200 dark:bg-orange-900/30" />
                    <div className="h-4 w-4 rounded-sm bg-orange-300 dark:bg-orange-800/40" />
                    <div className="h-4 w-4 rounded-sm bg-red-300 dark:bg-red-700/40" />
                    <div className="h-4 w-4 rounded-sm bg-red-400 dark:bg-red-600/50" />
                    <div className="h-4 w-4 rounded-sm bg-red-500 dark:bg-red-500/60" />
                </div>
                <span>More</span>
            </div>
        </div>
    );
}

// ─── Comparison ──────────────────────────────────────────────────────────────

function DeltaIndicator({
    value,
    isExpense = true,
}: {
    value: number;
    isExpense?: boolean;
}) {
    if (value === 0) {
        return (
            <span className="inline-flex items-center gap-0.5 text-xs text-muted-foreground">
                <Minus className="size-3" />
                0%
            </span>
        );
    }

    // For expenses: increase is bad (red), decrease is good (green)
    // For income: increase is good (green), decrease is bad (red)
    const isPositive = value > 0;
    const isGood = isExpense ? !isPositive : isPositive;

    return (
        <span
            className={`inline-flex items-center gap-0.5 text-xs font-medium ${
                isGood ? 'text-green-600 dark:text-green-400' : 'text-red-500'
            }`}
        >
            {isPositive ? (
                <ArrowUp className="size-3" />
            ) : (
                <ArrowDown className="size-3" />
            )}
            {Math.abs(value).toFixed(1)}%
        </span>
    );
}

function ComparisonSummaryCards({ summary }: { summary: ComparisonSummary }) {
    return (
        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <Card>
                <CardContent className="pt-6">
                    <p className="text-xs font-medium text-muted-foreground uppercase">
                        Total expenses
                    </p>
                    <div className="mt-1 flex items-baseline gap-2">
                        <span className="text-2xl font-semibold tabular-nums">
                            {formatAbsAmount(summary.current_expense)}
                        </span>
                        <DeltaIndicator
                            value={summary.expense_percentage_change}
                            isExpense={true}
                        />
                    </div>
                    <p className="mt-1 text-xs text-muted-foreground">
                        vs {formatAbsAmount(summary.compare_expense)} previous
                    </p>
                </CardContent>
            </Card>

            <Card>
                <CardContent className="pt-6">
                    <p className="text-xs font-medium text-muted-foreground uppercase">
                        Total income
                    </p>
                    <div className="mt-1 flex items-baseline gap-2">
                        <span className="text-2xl font-semibold tabular-nums">
                            {formatAbsAmount(summary.current_income)}
                        </span>
                        <DeltaIndicator
                            value={summary.income_percentage_change}
                            isExpense={false}
                        />
                    </div>
                    <p className="mt-1 text-xs text-muted-foreground">
                        vs {formatAbsAmount(summary.compare_income)} previous
                    </p>
                </CardContent>
            </Card>

            {summary.biggest_change && (
                <Card>
                    <CardContent className="pt-6">
                        <p className="text-xs font-medium text-muted-foreground uppercase">
                            Biggest change
                        </p>
                        <div className="mt-1 flex items-baseline gap-2">
                            <span className="text-lg font-semibold">
                                {summary.biggest_change.name}
                            </span>
                            <DeltaIndicator
                                value={summary.biggest_change.percentage_change}
                                isExpense={true}
                            />
                        </div>
                        <p className="mt-1 text-xs text-muted-foreground">
                            {formatAbsAmount(summary.biggest_change.current)} vs{' '}
                            {formatAbsAmount(summary.biggest_change.previous)}{' '}
                            previous
                        </p>
                    </CardContent>
                </Card>
            )}
        </div>
    );
}

function ComparisonTrendChart({ data }: { data: TrendOverlayItem[] }) {
    if (data.length === 0) {
        return (
            <EmptyState
                icon={<BarChart3 className="size-6" />}
                title="No data"
                description="Not enough data to compare trends."
            />
        );
    }

    const chartData = data.map((item) => ({
        ...item,
        label: `Month ${item.index}`,
    }));

    return (
        <ResponsiveContainer width="100%" height={250}>
            <ComposedChart
                data={chartData}
                margin={{ top: 8, right: 16, left: 0, bottom: 0 }}
            >
                <CartesianGrid
                    strokeDasharray="3 3"
                    className="stroke-border"
                />
                <XAxis
                    dataKey="label"
                    tick={{ fontSize: 11 }}
                    className="text-muted-foreground"
                />
                <YAxis
                    tick={{ fontSize: 11 }}
                    className="text-muted-foreground"
                />
                <Tooltip
                    formatter={(value: number, name: string) => [
                        formatAbsAmount(value),
                        name,
                    ]}
                />
                <Legend />
                <Line
                    type="monotone"
                    dataKey="current_expense"
                    stroke="var(--color-chart-1)"
                    strokeWidth={2}
                    dot={{ r: 3 }}
                    name="Current expense"
                />
                <Line
                    type="monotone"
                    dataKey="compare_expense"
                    stroke="var(--color-chart-1)"
                    strokeWidth={2}
                    strokeDasharray="5 5"
                    dot={{ r: 3 }}
                    name="Previous expense"
                />
                <Line
                    type="monotone"
                    dataKey="current_income"
                    stroke="var(--color-chart-3)"
                    strokeWidth={2}
                    dot={{ r: 3 }}
                    name="Current income"
                />
                <Line
                    type="monotone"
                    dataKey="compare_income"
                    stroke="var(--color-chart-3)"
                    strokeWidth={2}
                    strokeDasharray="5 5"
                    dot={{ r: 3 }}
                    name="Previous income"
                />
            </ComposedChart>
        </ResponsiveContainer>
    );
}

function CategoryDeltasTable({ deltas }: { deltas: CategoryDelta[] }) {
    if (deltas.length === 0) {
        return (
            <p className="text-sm text-muted-foreground">
                No category data to compare.
            </p>
        );
    }

    return (
        <>
            {/* Mobile card list */}
            <div className="divide-y sm:hidden">
                {deltas.map((item) => (
                    <div key={item.name} className="space-y-1 py-2.5">
                        <div className="flex items-center justify-between gap-3">
                            <span className="min-w-0 truncate text-sm font-medium">
                                {item.name}
                            </span>
                            <div className="flex shrink-0 items-center gap-2">
                                <span className="text-sm tabular-nums">
                                    {item.delta > 0 ? '+' : ''}
                                    {formatAbsAmount(Math.abs(item.delta))}
                                </span>
                                <DeltaIndicator
                                    value={item.percentage_change}
                                    isExpense={true}
                                />
                            </div>
                        </div>
                        <div className="flex items-center gap-3 text-xs text-muted-foreground">
                            <span className="tabular-nums">
                                Current: {formatAbsAmount(item.current)}
                            </span>
                            <span className="tabular-nums">
                                Previous: {formatAbsAmount(item.previous)}
                            </span>
                        </div>
                    </div>
                ))}
            </div>

            <Table className="hidden sm:table">
                <TableHeader>
                    <TableRow>
                        <TableHead>Category</TableHead>
                        <TableHead className="text-right">Current</TableHead>
                        <TableHead className="text-right">Previous</TableHead>
                        <TableHead className="text-right">Change</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    {deltas.map((item) => (
                        <TableRow key={item.name}>
                            <TableCell className="font-medium">
                                {item.name}
                            </TableCell>
                            <TableCell className="text-right tabular-nums">
                                {formatAbsAmount(item.current)}
                            </TableCell>
                            <TableCell className="text-right text-muted-foreground tabular-nums">
                                {formatAbsAmount(item.previous)}
                            </TableCell>
                            <TableCell className="text-right">
                                <div className="flex items-center justify-end gap-2">
                                    <span className="tabular-nums">
                                        {item.delta > 0 ? '+' : ''}
                                        {formatAbsAmount(Math.abs(item.delta))}
                                    </span>
                                    <DeltaIndicator
                                        value={item.percentage_change}
                                        isExpense={true}
                                    />
                                </div>
                            </TableCell>
                        </TableRow>
                    ))}
                </TableBody>
            </Table>
        </>
    );
}

function ComparisonSection({ comparison }: { comparison: ComparisonData }) {
    const { summary } = comparison;

    // Build a human-readable summary sentence
    const summaryText = buildSummarySentence(summary);

    return (
        <div className="space-y-6">
            {/* Summary sentence */}
            {summaryText && (
                <Card>
                    <CardContent className="pt-6">
                        <p className="text-sm">{summaryText}</p>
                        <p className="mt-1 text-xs text-muted-foreground">
                            {formatDate(comparison.current_period.from)} &ndash;{' '}
                            {formatDate(comparison.current_period.to)}
                            {' vs '}
                            {formatDate(
                                comparison.compare_period.from,
                            )} &ndash;{' '}
                            {formatDate(comparison.compare_period.to)}
                        </p>
                    </CardContent>
                </Card>
            )}

            {/* Summary cards */}
            <ComparisonSummaryCards summary={summary} />

            <div className="grid gap-6 lg:grid-cols-2">
                {/* Trend overlay */}
                <Card>
                    <CardHeader>
                        <CardTitle>Trend comparison</CardTitle>
                    </CardHeader>
                    <CardContent className="min-w-0 overflow-hidden">
                        <ComparisonTrendChart data={comparison.trendOverlay} />
                    </CardContent>
                </Card>

                {/* Category deltas */}
                <Card>
                    <CardHeader>
                        <CardTitle>Category changes</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <CategoryDeltasTable
                            deltas={comparison.categoryDeltas}
                        />
                    </CardContent>
                </Card>
            </div>
        </div>
    );
}

function buildSummarySentence(summary: ComparisonSummary): string | null {
    const parts: string[] = [];

    if (summary.expense_delta !== 0) {
        const direction = summary.expense_delta > 0 ? 'more' : 'less';
        parts.push(
            `You spent ${Math.abs(summary.expense_percentage_change).toFixed(1)}% ${direction} overall`,
        );
    }

    if (summary.biggest_change && summary.biggest_change.delta !== 0) {
        const direction = summary.biggest_change.delta > 0 ? 'more' : 'less';
        parts.push(
            `${Math.abs(summary.biggest_change.percentage_change).toFixed(1)}% ${direction} on ${summary.biggest_change.name}`,
        );
    }

    if (parts.length === 0) {
        return null;
    }

    return parts.join(', with ') + ' compared to the previous period.';
}

// ─── Page ─────────────────────────────────────────────────────────────────────

function computeDefaultDates(cycleStartDay: number): {
    date_from: string;
    date_to: string;
} {
    const today = new Date();
    const { start, end } = getCycleBounds(today, cycleStartDay);

    return {
        date_from: toDateString(start),
        date_to: toDateString(end),
    };
}

export default function ReportsIndex() {
    const { currentLedger } = usePage().props;
    const ledger = currentLedger!;
    const base = `/api/v1/ledgers/${ledger.id}`;

    const defaultDates = computeDefaultDates(ledger.cycle_start_day);

    const [filters, setFilters] = useState<Filters>({
        date_from: defaultDates.date_from,
        date_to: defaultDates.date_to,
        preset: 'this_month',
        account_id: null,
        compare_start: null,
        compare_end: null,
    });

    const [compareEnabled, setCompareEnabled] = useState(false);
    const [isExporting, setIsExporting] = useState(false);

    const apiParams: Record<string, string | null | undefined> = {
        date_from: filters.date_from || undefined,
        date_to: filters.date_to || undefined,
        account_id: filters.account_id || undefined,
        compare_start: filters.compare_start || undefined,
        compare_end: filters.compare_end || undefined,
    };

    const { data: result, loading } = useApiQuery<SpendingReportResponse>(
        `${base}/reports/spending`,
        { params: apiParams, deps: [filters] },
    );

    const report = result?.data ?? null;
    const monthlyTrend = report?.monthly_trends ?? [];
    const categoryBreakdown = report?.category_breakdown ?? {
        items: [],
        parents: [],
    };
    const payeeBreakdown = report?.payee_breakdown ?? [];
    const incomeCategoryBreakdown = report?.income_category_breakdown ?? {
        items: [],
        parents: [],
    };
    const incomePayeeBreakdown = report?.income_payee_breakdown ?? [];
    const spendingHeatmap = report?.spending_heatmap ?? [];
    const allAccounts = report?.all_accounts ?? [];
    const comparison = report?.comparison ?? null;
    const dateRange = report?.date_range ?? {
        date_from: filters.date_from,
        date_to: filters.date_to,
        preset: filters.preset,
        account_id: filters.account_id,
    };

    const handleFiltersChange = (newFilters: Partial<Filters>) => {
        setFilters((prev) => ({ ...prev, ...newFilters }));
    };

    const breadcrumbs: BreadcrumbItem[] = [
        { title: ledger.name, href: ledgerDashboard.url(ledger.id) },
        { title: 'Reports', href: reportsIndex.url(ledger.id) },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`${ledger.name} reports`} />

            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6 lg:p-8">
                {/* Header */}
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <Heading
                        title="Reports"
                        description="Analyse your finances with detailed breakdowns and trends."
                    />
                    <div className="flex items-center gap-2">
                        <ReportViewSelect
                            ledgerId={ledger.id}
                            currentView="income-expense"
                        />
                        <Button
                            variant="outline"
                            size="sm"
                            disabled={isExporting}
                            onClick={() => {
                                setIsExporting(true);

                                const link = document.createElement('a');
                                link.href = `/ledgers/${ledger.id}/reports/export-pdf?date_from=${dateRange.date_from}&date_to=${dateRange.date_to}`;
                                link.click();

                                setTimeout(() => {
                                    setIsExporting(false);
                                    toast.success(
                                        'Report exported successfully.',
                                    );
                                }, 2000);
                            }}
                        >
                            {isExporting ? 'Exporting...' : 'Export PDF'}
                        </Button>
                    </div>
                </div>

                {/* Date range selector */}
                <DateRangeSelector
                    cycleStartDay={ledger.cycle_start_day}
                    filters={{
                        ...filters,
                        preset: dateRange.preset ?? filters.preset,
                    }}
                    allAccounts={allAccounts}
                    compareEnabled={compareEnabled}
                    onCompareToggle={() => setCompareEnabled((prev) => !prev)}
                    onFiltersChange={handleFiltersChange}
                />

                {loading ? (
                    <div className="space-y-6">
                        <SummaryCardsSkeleton />
                        <div className="grid gap-6 lg:grid-cols-[1.4fr,1fr]">
                            <Card>
                                <CardHeader>
                                    <CardTitle>Monthly trend</CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <ChartSkeleton />
                                </CardContent>
                            </Card>
                            <Card>
                                <CardHeader>
                                    <CardTitle>Expense by category</CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <TableSkeleton />
                                </CardContent>
                            </Card>
                        </div>
                        <Card>
                            <CardHeader>
                                <CardTitle>Expense by payee</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <TableSkeleton />
                            </CardContent>
                        </Card>
                    </div>
                ) : (
                    <>
                        {/* Period comparison */}
                        {compareEnabled && comparison && (
                            <ComparisonSection comparison={comparison} />
                        )}
                        {compareEnabled && !comparison && (
                            <Card>
                                <CardContent className="flex items-center justify-center py-8">
                                    <p className="text-sm text-muted-foreground">
                                        Select a comparison period above and
                                        click &quot;Compare&quot; to see
                                        differences.
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
                                <CardContent className="min-w-0 overflow-hidden">
                                    <MonthlyTrendChart data={monthlyTrend} />
                                </CardContent>
                            </Card>

                            {/* Category breakdown */}
                            <Card>
                                <CardHeader>
                                    <CardTitle>Expense by category</CardTitle>
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
                                <CardTitle>Expense by payee</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <PayeeBreakdownSection
                                    data={payeeBreakdown}
                                />
                            </CardContent>
                        </Card>

                        {/* Income breakdown */}
                        <div className="grid gap-6 lg:grid-cols-[1.4fr,1fr]">
                            {/* Income by category */}
                            <Card>
                                <CardHeader>
                                    <CardTitle>Income by category</CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <IncomeCategoryBreakdownSection
                                        data={incomeCategoryBreakdown}
                                    />
                                </CardContent>
                            </Card>

                            {/* Income by payee */}
                            <Card>
                                <CardHeader>
                                    <CardTitle>Income by payee</CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <PayeeBreakdownSection
                                        data={incomePayeeBreakdown}
                                        amountClassName="text-green-600 dark:text-green-400"
                                    />
                                </CardContent>
                            </Card>
                        </div>

                        {/* Spending heatmap */}
                        <Card>
                            <CardHeader>
                                <CardTitle>Spending heatmap</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <SpendingHeatmap data={spendingHeatmap} />
                            </CardContent>
                        </Card>
                    </>
                )}
            </div>
        </AppLayout>
    );
}
