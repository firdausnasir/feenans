import { Head, useHttp, usePage } from '@inertiajs/react';
import { ClipboardList } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { index as activityLoader } from '@/actions/App/Http/Controllers/Api/V1/Ledger/ActivityLogController';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { EmptyState } from '@/components/ui/empty-state';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Skeleton } from '@/components/ui/skeleton';
import { usePrivacyMode } from '@/contexts/privacy-mode-context';
import AppLayout from '@/layouts/app-layout';
import { MASKED_AMOUNT } from '@/lib/format';
import {
    ALL_FILTER,
    getActivityFilterSelectState,
    shouldResetActivityState
    
} from '@/pages/ledgers/activity/page-state';
import type {ActivityFilters} from '@/pages/ledgers/activity/page-state';
import { dashboard as ledgerDashboard } from '@/routes/ledgers';
import { index as ledgerActivityIndex } from '@/routes/ledgers/activity';
import type { BreadcrumbItem } from '@/types';

type ActivityItem = {
    id: number;
    action: string;
    subject_type: string;
    subject_id: number;
    old_values: Record<string, unknown>;
    new_values: Record<string, unknown>;
    created_at: string;
    user?: { name: string } | null;
};

type ActivityResponse = {
    data: ActivityItem[];
    meta: {
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
    };
};

const SUBJECT_TYPES = [
    'Account',
    'AccountType',
    'Budget',
    'Bill',
    'Category',
    'Payee',
    'Tag',
    'Transaction',
];

const ACTIONS = ['created', 'updated', 'deleted', 'restored'];

function actionVariant(
    action: string,
): 'default' | 'secondary' | 'destructive' | 'outline' {
    switch (action) {
        case 'created':
            return 'default';
        case 'updated':
            return 'secondary';
        case 'deleted':
            return 'destructive';
        case 'restored':
            return 'outline';
        default:
            return 'secondary';
    }
}

const AMOUNT_FIELDS = new Set([
    'amount',
    'initial_balance',
    'current_balance',
    'balance',
    'estimated_amount',
    'split_amount',
]);

function formatValue(
    value: unknown,
    fieldName?: string,
    privacyMode?: boolean,
): string {
    if (value === null || value === undefined) {
        return '(empty)';
    }

    if (typeof value === 'boolean') {
        return value ? 'Yes' : 'No';
    }

    if (privacyMode && fieldName && AMOUNT_FIELDS.has(fieldName)) {
        return MASKED_AMOUNT;
    }

    return String(value);
}

function formatFieldName(field: string): string {
    return field
        .replace(/_id$/, '')
        .replace(/_/g, ' ')
        .replace(/\b\w/g, (c) => c.toUpperCase());
}

function buildActivityQuery(filters: ActivityFilters): Record<string, string> {
    const query: Record<string, string> = {};

    if (filters.subject_type) {
        query.subject_type = filters.subject_type;
    }

    if (filters.action) {
        query.action = filters.action;
    }

    if (filters.page > 1) {
        query.page = String(filters.page);
    }

    return query;
}

function updateActivityUrl(ledgerId: number, filters: ActivityFilters): void {
    if (typeof window === 'undefined') {
        return;
    }

    const url = new URL(ledgerActivityIndex.url(ledgerId), window.location.origin);

    for (const [key, value] of Object.entries(buildActivityQuery(filters))) {
        url.searchParams.set(key, value);
    }

    window.history.replaceState(window.history.state, '', `${url.pathname}${url.search}`);
}

function ChangeDiff({
    oldValues,
    newValues,
    action,
}: {
    oldValues: Record<string, unknown>;
    newValues: Record<string, unknown>;
    action: string;
}) {
    const { privacyMode } = usePrivacyMode();

    if (action === 'updated') {
        const keys = [
            ...new Set([...Object.keys(oldValues), ...Object.keys(newValues)]),
        ];

        if (keys.length === 0) {
            return (
                <p className="text-xs text-muted-foreground italic">
                    No field changes recorded.
                </p>
            );
        }

        return (
            <div className="space-y-1">
                {keys.map((key) => (
                    <div key={key} className="text-xs">
                        <span className="font-medium">
                            {formatFieldName(key)}:
                        </span>{' '}
                        <span className="text-red-600 line-through dark:text-red-400">
                            {formatValue(oldValues[key], key, privacyMode)}
                        </span>
                        {' -> '}
                        <span className="text-green-600 dark:text-green-400">
                            {formatValue(newValues[key], key, privacyMode)}
                        </span>
                    </div>
                ))}
            </div>
        );
    }

    if (action === 'created') {
        const entries = Object.entries(newValues);
        const displayEntries = entries.filter(
            ([key]) => !['id', 'ledger_id'].includes(key),
        );

        if (displayEntries.length === 0) {
            return null;
        }

        return (
            <div className="space-y-1">
                {displayEntries.map(([key, value]) => (
                    <div key={key} className="text-xs">
                        <span className="font-medium">
                            {formatFieldName(key)}:
                        </span>{' '}
                        <span className="text-green-600 dark:text-green-400">
                            {formatValue(value, key, privacyMode)}
                        </span>
                    </div>
                ))}
            </div>
        );
    }

    if (action === 'deleted') {
        const entries = Object.entries(oldValues);
        const displayEntries = entries.filter(
            ([key]) => !['id', 'ledger_id'].includes(key),
        );

        if (displayEntries.length === 0) {
            return null;
        }

        return (
            <div className="space-y-1">
                {displayEntries.map(([key, value]) => (
                    <div key={key} className="text-xs">
                        <span className="font-medium">
                            {formatFieldName(key)}:
                        </span>{' '}
                        <span className="text-red-600 line-through dark:text-red-400">
                            {formatValue(value, key, privacyMode)}
                        </span>
                    </div>
                ))}
            </div>
        );
    }

    return null;
}

function ActivityEntry({ entry }: { entry: ActivityItem }) {
    const [expanded, setExpanded] = useState(false);
    const hasDetails =
        Object.keys(entry.old_values).length > 0 ||
        Object.keys(entry.new_values).length > 0;

    return (
        <Card>
            <CardContent className="py-4">
                <button
                    type="button"
                    className="flex w-full items-center justify-between gap-4 text-left"
                    onClick={() => setExpanded((prev) => !prev)}
                    disabled={!hasDetails}
                >
                    <div className="flex items-center gap-3">
                        <Badge variant={actionVariant(entry.action)}>
                            {entry.action}
                        </Badge>
                        <div>
                            <p className="text-sm font-medium">
                                {entry.subject_type}
                                {entry.subject_id ? ` #${entry.subject_id}` : ''}
                            </p>
                        </div>
                    </div>
                    <div className="flex items-center gap-3 text-right">
                        <div>
                            <p className="text-xs text-muted-foreground">
                                {entry.user?.name ?? 'System'}
                            </p>
                            <p className="text-xs text-muted-foreground">
                                {new Date(entry.created_at).toLocaleDateString(
                                    undefined,
                                    {
                                        year: 'numeric',
                                        month: 'short',
                                        day: 'numeric',
                                        hour: '2-digit',
                                        minute: '2-digit',
                                    },
                                )}
                            </p>
                        </div>
                        {hasDetails && (
                            <span className="text-xs text-muted-foreground">
                                {expanded ? '▲' : '▼'}
                            </span>
                        )}
                    </div>
                </button>

                {expanded && hasDetails && (
                    <div className="mt-3 rounded-md bg-muted/50 p-3">
                        <ChangeDiff
                            oldValues={entry.old_values}
                            newValues={entry.new_values}
                            action={entry.action}
                        />
                    </div>
                )}
            </CardContent>
        </Card>
    );
}

function ActivityLoadingSkeleton() {
    return (
        <div className="grid gap-3">
            {Array.from({ length: 6 }).map((_, i) => (
                <Card key={i}>
                    <CardContent className="py-4">
                        <div className="flex items-center justify-between gap-4">
                            <div className="flex items-center gap-3">
                                <Skeleton className="h-5 w-16" />
                                <Skeleton className="h-4 w-32" />
                            </div>
                            <div className="flex flex-col items-end gap-1">
                                <Skeleton className="h-3 w-16" />
                                <Skeleton className="h-3 w-24" />
                            </div>
                        </div>
                    </CardContent>
                </Card>
            ))}
        </div>
    );
}

function ActivityErrorState({ onRetry }: { onRetry: () => void }) {
    return (
        <Card>
            <CardContent className="flex flex-col gap-3 py-4">
                <p className="text-sm text-muted-foreground">
                    Failed to load activity.
                </p>
                <div>
                    <Button variant="outline" size="sm" onClick={onRetry}>
                        Retry
                    </Button>
                </div>
            </CardContent>
        </Card>
    );
}

export default function ActivityIndex() {
    const { currentLedger, filters } = usePage<{
        currentLedger: { id: number; name: string } | null;
        filters: ActivityFilters;
    }>().props;
    const ledger = currentLedger!;

    const activityLoaderState = useHttp<Record<string, never>, ActivityResponse>({});

    const initialFilterSelectState = getActivityFilterSelectState(filters);

    const [filterType, setFilterType] = useState<string>(
        initialFilterSelectState.filterType,
    );
    const [filterAction, setFilterAction] = useState<string>(
        initialFilterSelectState.filterAction,
    );
    const [activeFilters, setActiveFilters] = useState<ActivityFilters>(filters);
    const [activityResponse, setActivityResponse] =
        useState<ActivityResponse | null>(null);
    const [activityError, setActivityError] = useState<string | null>(null);
    const [hasLoadedOnce, setHasLoadedOnce] = useState(false);
    const latestRequestRef = useRef(0);
    const latestFiltersRef = useRef<ActivityFilters>(filters);
    const previousLedgerIdRef = useRef(ledger.id);
    const previousFiltersRef = useRef<ActivityFilters>(filters);

    const breadcrumbs: BreadcrumbItem[] = [
        { title: ledger.name, href: ledgerDashboard.url(ledger.id) },
        { title: 'Activity', href: ledgerActivityIndex.url(ledger.id) },
    ];

    async function loadActivity(nextFilters: ActivityFilters): Promise<boolean> {
        let cancelled = false;
        const requestId = latestRequestRef.current + 1;

        latestRequestRef.current = requestId;
        latestFiltersRef.current = nextFilters;

        activityLoaderState.cancel();
        setActivityError(null);
        setActiveFilters(nextFilters);
        updateActivityUrl(ledger.id, nextFilters);

        try {
            await activityLoaderState.get(
                activityLoader.url(
                    { ledger: ledger.id },
                    { query: buildActivityQuery(nextFilters) },
                ),
                {
                    onCancel: () => {
                        cancelled = true;
                    },
                },
            );

            if (!cancelled && latestRequestRef.current === requestId) {
                setActivityResponse(activityLoaderState.response ?? null);
            }

            return true;
        } catch {
            if (!cancelled && latestRequestRef.current === requestId) {
                setActivityError('Failed to load activity.');
            }

            return false;
        } finally {
            if (!cancelled && latestRequestRef.current === requestId) {
                setHasLoadedOnce(true);
            }
        }
    }

    useEffect(() => {
        if (
            !shouldResetActivityState(
                previousLedgerIdRef.current,
                ledger.id,
                previousFiltersRef.current,
                filters,
            )
        ) {
            return;
        }

        previousLedgerIdRef.current = ledger.id;
        previousFiltersRef.current = filters;

        const nextFilterSelectState = getActivityFilterSelectState(filters);

        activityLoaderState.cancel();
        latestRequestRef.current += 1;
        latestFiltersRef.current = filters;
        setFilterType(nextFilterSelectState.filterType);
        setFilterAction(nextFilterSelectState.filterAction);
        setActiveFilters(filters);
        setActivityResponse(null);
        setActivityError(null);
        setHasLoadedOnce(false);
    }, [activityLoaderState, filters, ledger.id]);

    useEffect(() => {
        void loadActivity(filters);

        return () => {
            activityLoaderState.cancel();
        };
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [filters, ledger.id]);

    const activityEntries = activityResponse?.data ?? [];
    const activityMeta = activityResponse?.meta ?? null;
    const showInitialLoading = activityLoaderState.processing && !hasLoadedOnce;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`${ledger.name} activity`} />

            <div className="flex h-full flex-1 flex-col gap-4 p-4 md:p-6">
                <div className="flex flex-wrap items-center gap-3">
                    <Select
                        value={filterType}
                        onValueChange={(val) => {
                            setFilterType(val);

                            void loadActivity({
                                subject_type:
                                    val === ALL_FILTER ? null : val,
                                action:
                                    filterAction === ALL_FILTER
                                        ? null
                                        : filterAction,
                                page: 1,
                            });
                        }}
                    >
                        <SelectTrigger className="w-44">
                            <SelectValue placeholder="All types" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value={ALL_FILTER}>All types</SelectItem>
                            {SUBJECT_TYPES.map((type) => (
                                <SelectItem key={type} value={type}>
                                    {type}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>

                    <Select
                        value={filterAction}
                        onValueChange={(val) => {
                            setFilterAction(val);

                            void loadActivity({
                                subject_type:
                                    filterType === ALL_FILTER
                                        ? null
                                        : filterType,
                                action: val === ALL_FILTER ? null : val,
                                page: 1,
                            });
                        }}
                    >
                        <SelectTrigger className="w-40">
                            <SelectValue placeholder="All actions" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value={ALL_FILTER}>
                                All actions
                            </SelectItem>
                            {ACTIONS.map((action) => (
                                <SelectItem key={action} value={action}>
                                    {action.charAt(0).toUpperCase() +
                                        action.slice(1)}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>

                {showInitialLoading ? (
                    <ActivityLoadingSkeleton />
                ) : activityError && activityEntries.length === 0 ? (
                    <ActivityErrorState
                        onRetry={() => {
                            void loadActivity(latestFiltersRef.current);
                        }}
                    />
                ) : (
                    <div className="grid gap-3">
                        {activityEntries.length === 0 ? (
                            <EmptyState
                                icon={<ClipboardList className="size-6" />}
                                title="No activity yet"
                                description="Changes to your workspace will appear here."
                            />
                        ) : (
                            activityEntries.map((entry) => (
                                <ActivityEntry key={entry.id} entry={entry} />
                            ))
                        )}
                    </div>
                )}

                {activityError && activityEntries.length > 0 ? (
                    <div className="flex items-center justify-end gap-2">
                        <p className="text-sm text-muted-foreground">
                            Refresh failed.
                        </p>
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={() => {
                                void loadActivity(latestFiltersRef.current);
                            }}
                        >
                            Retry
                        </Button>
                    </div>
                ) : null}

                {activityMeta && activityMeta.last_page > 1 ? (
                    <div className="flex items-center justify-center gap-2">
                        <button
                            type="button"
                            className="rounded px-3 py-1 text-sm text-muted-foreground hover:bg-muted disabled:opacity-50"
                            disabled={
                                activityLoaderState.processing ||
                                activityMeta.current_page <= 1
                            }
                            onClick={() => {
                                void loadActivity({
                                    ...activeFilters,
                                    page: activityMeta.current_page - 1,
                                });
                            }}
                        >
                            Previous
                        </button>
                        <span className="text-sm text-muted-foreground">
                            Page {activityMeta.current_page} of{' '}
                            {activityMeta.last_page}
                        </span>
                        <button
                            type="button"
                            className="rounded px-3 py-1 text-sm text-muted-foreground hover:bg-muted disabled:opacity-50"
                            disabled={
                                activityLoaderState.processing ||
                                activityMeta.current_page >= activityMeta.last_page
                            }
                            onClick={() => {
                                void loadActivity({
                                    ...activeFilters,
                                    page: activityMeta.current_page + 1,
                                });
                            }}
                        >
                            Next
                        </button>
                    </div>
                ) : null}
            </div>
        </AppLayout>
    );
}
