import { Deferred, Head, usePage } from '@inertiajs/react';
import { BarChart3, TrendingDown, TrendingUp, Wallet } from 'lucide-react';
import {
    Area,
    AreaChart,
    Bar,
    BarChart,
    CartesianGrid,
    Legend,
    ResponsiveContainer,
    Tooltip,
    XAxis,
    YAxis,
} from 'recharts';
import { ReportViewSelect } from '@/components/report-view-select';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { EmptyState } from '@/components/ui/empty-state';
import { Skeleton } from '@/components/ui/skeleton';
import { usePrivacyMode } from '@/contexts/privacy-mode-context';
import AppLayout from '@/layouts/app-layout';
import { formatAbsAmount, formatAmount } from '@/lib/format';
import { dashboard as ledgerDashboard } from '@/routes/ledgers';
import {
    index as reportsIndex,
    financialHealth as financialHealthRoute,
} from '@/routes/ledgers/reports';
import type { BreadcrumbItem } from '@/types';

// ─── Types ───────────────────────────────────────────────────────────────────

type NetWorthEntry = {
    month: string;
    assets: number;
    liabilities: number;
    net_worth: number;
};

type SavingsRateEntry = {
    month: string;
    income: number;
    expense: number;
    savings: number;
    rate: number;
};

type CurrentSnapshot = {
    assets: number;
    liabilities: number;
    net_worth: number;
    debt_to_asset_ratio: number;
};

// ─── Helpers ─────────────────────────────────────────────────────────────────

function formatMonthLabel(month: string): string {
    const [year, m] = month.split('-');
    const date = new Date(Number(year), Number(m) - 1, 1);
    const shortMonth = date.toLocaleDateString('en-MY', { month: 'short' });

    return `${shortMonth} ${String(year).slice(2)}`;
}

// ─── Components ──────────────────────────────────────────────────────────────

function SnapshotCards({ snapshot }: { snapshot: CurrentSnapshot }) {
    const { privacyMode } = usePrivacyMode();

    return (
        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <Card>
                <CardContent className="pt-6">
                    <div className="flex items-center gap-2">
                        <TrendingUp className="size-4 text-foreground" />
                        <p className="text-xs font-medium text-muted-foreground uppercase">
                            Total assets
                        </p>
                    </div>
                    <p className="mt-2 text-2xl font-semibold text-foreground tabular-nums">
                        {formatAbsAmount(snapshot.assets, privacyMode)}
                    </p>
                </CardContent>
            </Card>

            <Card>
                <CardContent className="pt-6">
                    <div className="flex items-center gap-2">
                        <TrendingDown className="size-4 text-red-500" />
                        <p className="text-xs font-medium text-muted-foreground uppercase">
                            Total liabilities
                        </p>
                    </div>
                    <p className="mt-2 text-2xl font-semibold text-red-500 tabular-nums">
                        {formatAbsAmount(snapshot.liabilities, privacyMode)}
                    </p>
                </CardContent>
            </Card>

            <Card>
                <CardContent className="pt-6">
                    <div className="flex items-center gap-2">
                        <Wallet className="size-4 text-foreground" />
                        <p className="text-xs font-medium text-muted-foreground uppercase">
                            Net worth
                        </p>
                    </div>
                    <p
                        className={`mt-2 text-2xl font-semibold tabular-nums ${
                            snapshot.net_worth >= 0
                                ? 'text-foreground'
                                : 'text-red-500'
                        }`}
                    >
                        {snapshot.net_worth < 0 ? '-' : ''}
                        {formatAbsAmount(
                            Math.abs(snapshot.net_worth),
                            privacyMode,
                        )}
                    </p>
                </CardContent>
            </Card>

            <Card>
                <CardContent className="pt-6">
                    <div className="flex items-center gap-2">
                        <BarChart3 className="size-4 text-foreground" />
                        <p className="text-xs font-medium text-muted-foreground uppercase">
                            Debt-to-asset ratio
                        </p>
                    </div>
                    <p className="mt-2 text-2xl font-semibold tabular-nums">
                        {privacyMode
                            ? '***'
                            : snapshot.debt_to_asset_ratio.toFixed(2)}
                    </p>
                    <p className="mt-1 text-xs text-muted-foreground">
                        {privacyMode
                            ? '\u00A0'
                            : snapshot.debt_to_asset_ratio <= 0.5
                              ? 'Healthy'
                              : snapshot.debt_to_asset_ratio <= 0.8
                                ? 'Moderate'
                                : 'High'}
                    </p>
                </CardContent>
            </Card>
        </div>
    );
}

function NetWorthChart({ data }: { data: NetWorthEntry[] }) {
    const { privacyMode } = usePrivacyMode();

    if (data.length === 0) {
        return (
            <EmptyState
                icon={<BarChart3 className="size-6" />}
                title="No data"
                description="Not enough data to show net worth history."
            />
        );
    }

    const chartData = data.map((entry) => ({
        ...entry,
        monthLabel: formatMonthLabel(entry.month),
    }));

    return (
        <ResponsiveContainer width="100%" height={280}>
            <AreaChart
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
                    tickFormatter={(v) => formatAbsAmount(v, privacyMode)}
                />
                <Tooltip
                    formatter={(value: any, name: any) => [
                        formatAmount(Number(value), privacyMode),
                        String(name)
                            .replace('_', ' ')
                            .replace(/\b\w/g, (c) => c.toUpperCase()),
                    ]}
                />
                <Legend
                    formatter={(value: string) =>
                        value
                            .replace('_', ' ')
                            .replace(/\b\w/g, (c) => c.toUpperCase())
                    }
                />
                <Area
                    type="monotone"
                    dataKey="assets"
                    stroke="var(--color-chart-3)"
                    fill="var(--color-chart-3)"
                    fillOpacity={0.15}
                    strokeWidth={2}
                    name="assets"
                />
                <Area
                    type="monotone"
                    dataKey="liabilities"
                    stroke="var(--color-chart-1)"
                    fill="var(--color-chart-1)"
                    fillOpacity={0.15}
                    strokeWidth={2}
                    name="liabilities"
                />
                <Area
                    type="monotone"
                    dataKey="net_worth"
                    stroke="var(--color-chart-4)"
                    fill="var(--color-chart-4)"
                    fillOpacity={0.1}
                    strokeWidth={2}
                    name="net_worth"
                />
            </AreaChart>
        </ResponsiveContainer>
    );
}

function SavingsRateChart({ data }: { data: SavingsRateEntry[] }) {
    const { privacyMode } = usePrivacyMode();

    if (data.length === 0) {
        return (
            <EmptyState
                icon={<BarChart3 className="size-6" />}
                title="No data"
                description="Not enough data to show savings rate."
            />
        );
    }

    const chartData = data.map((entry) => ({
        ...entry,
        monthLabel: formatMonthLabel(entry.month),
    }));

    return (
        <ResponsiveContainer width="100%" height={280}>
            <BarChart
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
                    unit="%"
                    tickFormatter={(v) => (privacyMode ? '***' : `${v}`)}
                />
                <Tooltip
                    formatter={(value: any, name: string) => {
                        if (name === 'rate') {
                            return [
                                privacyMode
                                    ? '***'
                                    : `${Number(value).toFixed(1)}%`,
                                'Savings rate',
                            ];
                        }

                        return [
                            formatAmount(Number(value), privacyMode),
                            name.charAt(0).toUpperCase() + name.slice(1),
                        ];
                    }}
                />
                <Legend />
                <Bar
                    dataKey="rate"
                    name="Savings rate"
                    barSize={20}
                    fill="var(--color-chart-3)"
                    radius={[4, 4, 0, 0]}
                />
            </BarChart>
        </ResponsiveContainer>
    );
}

// ─── Page ────────────────────────────────────────────────────────────────────

function FinancialHealthSkeleton() {
    return (
        <div className="space-y-6">
            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                {[1, 2, 3, 4].map((i) => (
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
                    <CardTitle>Net worth over time</CardTitle>
                </CardHeader>
                <CardContent>
                    <Skeleton className="h-[280px] w-full rounded" />
                </CardContent>
            </Card>
            <Card>
                <CardHeader>
                    <CardTitle>Monthly savings rate</CardTitle>
                </CardHeader>
                <CardContent>
                    <Skeleton className="h-[280px] w-full rounded" />
                </CardContent>
            </Card>
        </div>
    );
}

export default function FinancialHealthPage() {
    const { currentLedger, health } = usePage<{
        health?: {
            net_worth_history: NetWorthEntry[];
            savings_rate_history: SavingsRateEntry[];
            current_snapshot: CurrentSnapshot;
        };
    }>().props;
    const ledger = currentLedger!;

    const netWorthHistory = health?.net_worth_history ?? [];
    const savingsRateHistory = health?.savings_rate_history ?? [];
    const currentSnapshot = health?.current_snapshot ?? {
        assets: 0,
        liabilities: 0,
        net_worth: 0,
        debt_to_asset_ratio: 0,
    };

    const breadcrumbs: BreadcrumbItem[] = [
        { title: ledger.name, href: ledgerDashboard.url(ledger.id) },
        { title: 'Reports', href: reportsIndex.url(ledger.id) },
        {
            title: 'Financial Health',
            href: financialHealthRoute.url(ledger.id),
        },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`${ledger.name} - Financial Health`} />

            <div className="flex h-full flex-1 flex-col gap-4 p-4 md:p-6">
                {/* Toolbar */}
                <div className="flex flex-wrap items-center gap-2">
                    <ReportViewSelect
                        ledgerId={ledger.id}
                        currentView="financial-health"
                        className="shrink-0"
                    />
                    <Button
                        variant="outline"
                        size="sm"
                        className="w-full sm:ml-auto sm:w-auto"
                        asChild
                    >
                        <a href={`/ledgers/${ledger.id}/reports/export-pdf`}>
                            Export PDF
                        </a>
                    </Button>
                </div>

                <Deferred data="health" fallback={<FinancialHealthSkeleton />}>
                    {/* Current snapshot cards */}
                    <SnapshotCards snapshot={currentSnapshot} />

                    {/* Net worth history */}
                    <Card>
                        <CardHeader>
                            <CardTitle>Net worth over time</CardTitle>
                        </CardHeader>
                        <CardContent className="min-w-0 overflow-hidden">
                            <NetWorthChart data={netWorthHistory} />
                        </CardContent>
                    </Card>

                    {/* Savings rate */}
                    <Card>
                        <CardHeader>
                            <CardTitle>Monthly savings rate</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <SavingsRateChart data={savingsRateHistory} />
                        </CardContent>
                    </Card>
                </Deferred>
            </div>
        </AppLayout>
    );
}
