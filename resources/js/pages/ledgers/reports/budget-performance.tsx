import { Head, usePage } from '@inertiajs/react';
import { AlertTriangle, BarChart3, CheckCircle, XCircle } from 'lucide-react';
import Heading from '@/components/heading';
import { ReportViewSelect } from '@/components/report-view-select';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { EmptyState } from '@/components/ui/empty-state';
import { Progress } from '@/components/ui/progress';
import { Skeleton } from '@/components/ui/skeleton';
import { useApiQuery } from '@/hooks/use-api-query';
import AppLayout from '@/layouts/app-layout';
import { formatAbsAmount } from '@/lib/format';
import { dashboard as ledgerDashboard } from '@/routes/ledgers';
import {
    index as reportsIndex,
    budgetPerformance as budgetPerformanceRoute,
} from '@/routes/ledgers/reports';
import type { BreadcrumbItem } from '@/types';

// ─── Types ───────────────────────────────────────────────────────────────────

type BudgetStat = {
    id: number;
    category_name: string;
    amount: number;
    spent: number;
    remaining: number;
    percentage: number;
    period: string;
    status: 'good' | 'warning' | 'danger' | 'over';
};

type BudgetPerformanceResponse = {
    data: {
        budget_stats: BudgetStat[];
        period_label: string;
    };
};

// ─── Helpers ─────────────────────────────────────────────────────────────────

function statusColor(status: BudgetStat['status']): string {
    switch (status) {
        case 'good':
            return 'text-green-600 dark:text-green-400';
        case 'warning':
            return 'text-yellow-600 dark:text-yellow-400';
        case 'danger':
            return 'text-orange-600 dark:text-orange-400';
        case 'over':
            return 'text-red-600 dark:text-red-400';
    }
}

function statusProgressColor(status: BudgetStat['status']): string {
    switch (status) {
        case 'good':
            return '[&_[data-slot=progress-indicator]]:bg-green-500';
        case 'warning':
            return '[&_[data-slot=progress-indicator]]:bg-yellow-500';
        case 'danger':
            return '[&_[data-slot=progress-indicator]]:bg-orange-500';
        case 'over':
            return '[&_[data-slot=progress-indicator]]:bg-red-500';
    }
}

function StatusIcon({ status }: { status: BudgetStat['status'] }) {
    switch (status) {
        case 'good':
            return (
                <CheckCircle className="size-4 text-green-600 dark:text-green-400" />
            );
        case 'warning':
            return (
                <AlertTriangle className="size-4 text-yellow-600 dark:text-yellow-400" />
            );
        case 'danger':
            return (
                <AlertTriangle className="size-4 text-orange-600 dark:text-orange-400" />
            );
        case 'over':
            return (
                <XCircle className="size-4 text-red-600 dark:text-red-400" />
            );
    }
}

// ─── Skeleton Components ─────────────────────────────────────────────────────

function BudgetPerformanceSkeleton() {
    return (
        <div className="space-y-6">
            {/* Summary cards skeleton */}
            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                {Array.from({ length: 4 }).map((_, i) => (
                    <Card key={i}>
                        <CardContent className="pt-6">
                            <Skeleton className="mb-2 h-3 w-24 rounded" />
                            <Skeleton className="h-8 w-20 rounded" />
                        </CardContent>
                    </Card>
                ))}
            </div>

            {/* Budget cards skeleton */}
            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                {Array.from({ length: 6 }).map((_, i) => (
                    <Card key={i}>
                        <CardContent className="pt-6">
                            <Skeleton className="mb-2 h-4 w-32 rounded" />
                            <Skeleton className="mb-3 h-3 w-16 rounded" />
                            <Skeleton className="mb-3 h-2 w-full rounded" />
                            <div className="flex justify-between">
                                <Skeleton className="h-3 w-20 rounded" />
                                <Skeleton className="h-3 w-20 rounded" />
                            </div>
                        </CardContent>
                    </Card>
                ))}
            </div>
        </div>
    );
}

// ─── Components ──────────────────────────────────────────────────────────────

function BudgetSummary({ stats }: { stats: BudgetStat[] }) {
    const totalBudget = stats.reduce((sum, s) => sum + s.amount, 0);
    const totalSpent = stats.reduce((sum, s) => sum + s.spent, 0);
    const overBudgetCount = stats.filter((s) => s.status === 'over').length;
    const onTrackCount = stats.filter((s) => s.status === 'good').length;

    return (
        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <Card>
                <CardContent className="pt-6">
                    <p className="text-xs font-medium text-muted-foreground uppercase">
                        Total budgeted
                    </p>
                    <p className="mt-2 text-2xl font-semibold tabular-nums">
                        {formatAbsAmount(totalBudget)}
                    </p>
                </CardContent>
            </Card>
            <Card>
                <CardContent className="pt-6">
                    <p className="text-xs font-medium text-muted-foreground uppercase">
                        Total spent
                    </p>
                    <p className="mt-2 text-2xl font-semibold text-red-500 tabular-nums">
                        {formatAbsAmount(totalSpent)}
                    </p>
                </CardContent>
            </Card>
            <Card>
                <CardContent className="pt-6">
                    <p className="text-xs font-medium text-muted-foreground uppercase">
                        On track
                    </p>
                    <p className="mt-2 text-2xl font-semibold text-green-600 tabular-nums dark:text-green-400">
                        {onTrackCount}
                    </p>
                </CardContent>
            </Card>
            <Card>
                <CardContent className="pt-6">
                    <p className="text-xs font-medium text-muted-foreground uppercase">
                        Over budget
                    </p>
                    <p className="mt-2 text-2xl font-semibold text-red-500 tabular-nums">
                        {overBudgetCount}
                    </p>
                </CardContent>
            </Card>
        </div>
    );
}

function BudgetCard({ stat }: { stat: BudgetStat }) {
    return (
        <Card>
            <CardContent className="pt-6">
                <div className="flex items-start justify-between gap-3">
                    <div className="min-w-0">
                        <div className="flex items-center gap-2">
                            <StatusIcon status={stat.status} />
                            <h3 className="truncate text-sm font-medium">
                                {stat.category_name}
                            </h3>
                        </div>
                        <p className="mt-1 text-xs text-muted-foreground capitalize">
                            {stat.period}
                        </p>
                    </div>
                    <span
                        className={`shrink-0 text-sm font-semibold tabular-nums ${statusColor(stat.status)}`}
                    >
                        {stat.percentage.toFixed(1)}%
                    </span>
                </div>

                <div className="mt-3">
                    <Progress
                        value={Math.min(stat.percentage, 100)}
                        className={`h-2 ${statusProgressColor(stat.status)}`}
                    />
                </div>

                <div className="mt-3 flex items-center justify-between text-xs text-muted-foreground">
                    <span className="tabular-nums">
                        Spent:{' '}
                        <span className="text-foreground">
                            {formatAbsAmount(stat.spent)}
                        </span>
                    </span>
                    <span className="tabular-nums">
                        Budget: {formatAbsAmount(stat.amount)}
                    </span>
                </div>

                <div className="mt-1 text-xs">
                    {stat.remaining >= 0 ? (
                        <span className="text-green-600 tabular-nums dark:text-green-400">
                            {formatAbsAmount(stat.remaining)} remaining
                        </span>
                    ) : (
                        <span className="text-red-500 tabular-nums">
                            {formatAbsAmount(Math.abs(stat.remaining))} over
                            budget
                        </span>
                    )}
                </div>
            </CardContent>
        </Card>
    );
}

// ─── Page ────────────────────────────────────────────────────────────────────

export default function BudgetPerformancePage() {
    const { currentLedger } = usePage().props;
    const ledger = currentLedger!;
    const base = `/api/v1/ledgers/${ledger.id}`;

    const { data: result, loading } = useApiQuery<BudgetPerformanceResponse>(
        `${base}/reports/budget-performance`,
    );

    const budgetStats = result?.data?.budget_stats ?? [];
    const periodLabel = result?.data?.period_label ?? 'current period';

    const breadcrumbs: BreadcrumbItem[] = [
        { title: ledger.name, href: ledgerDashboard.url(ledger.id) },
        { title: 'Reports', href: reportsIndex.url(ledger.id) },
        {
            title: 'Budget Performance',
            href: budgetPerformanceRoute.url(ledger.id),
        },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`${ledger.name} - Budget Performance`} />

            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6 lg:p-8">
                {/* Header */}
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <Heading
                        title="Reports"
                        description={`Budget performance for ${periodLabel}.`}
                    />
                    <div className="flex items-center gap-2">
                        <ReportViewSelect
                            ledgerId={ledger.id}
                            currentView="budget-performance"
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

                {loading ? (
                    <BudgetPerformanceSkeleton />
                ) : budgetStats.length === 0 ? (
                    <Card>
                        <CardContent className="py-12">
                            <EmptyState
                                icon={<BarChart3 className="size-6" />}
                                title="No active budgets"
                                description="Create budgets to track your spending against targets."
                            />
                        </CardContent>
                    </Card>
                ) : (
                    <>
                        {/* Summary */}
                        <BudgetSummary stats={budgetStats} />

                        {/* Budget cards grid */}
                        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                            {budgetStats.map((stat) => (
                                <BudgetCard key={stat.id} stat={stat} />
                            ))}
                        </div>
                    </>
                )}
            </div>
        </AppLayout>
    );
}
