import { Head, usePage } from '@inertiajs/react';
import { ClipboardList } from 'lucide-react';
import { useState } from 'react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
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
import { useApiQuery } from '@/hooks/use-api-query';
import AppLayout from '@/layouts/app-layout';
import { dashboard as ledgerDashboard } from '@/routes/ledgers';
import type { BreadcrumbItem, Pagination } from '@/types';

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

const ALL_FILTER = '__all__';

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

function formatValue(value: unknown): string {
    if (value === null || value === undefined) {
        return '(empty)';
    }

    if (typeof value === 'boolean') {
        return value ? 'Yes' : 'No';
    }

    return String(value);
}

function formatFieldName(field: string): string {
    return field
        .replace(/_id$/, '')
        .replace(/_/g, ' ')
        .replace(/\b\w/g, (c) => c.toUpperCase());
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
                            {formatValue(oldValues[key])}
                        </span>
                        {' → '}
                        <span className="text-green-600 dark:text-green-400">
                            {formatValue(newValues[key])}
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
                            {formatValue(value)}
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
                            {formatValue(value)}
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
                                {entry.subject_id
                                    ? ` #${entry.subject_id}`
                                    : ''}
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

export default function ActivityIndex() {
    const { currentLedger } = usePage().props;
    const ledger = currentLedger!;
    const base = `/api/v1/ledgers/${ledger.id}`;

    const [filterType, setFilterType] = useState<string>(ALL_FILTER);
    const [filterAction, setFilterAction] = useState<string>(ALL_FILTER);
    const [page, setPage] = useState(1);

    const { data: activityResult, loading } = useApiQuery<
        Pagination<ActivityItem>
    >(`${base}/activity`, {
        params: {
            per_page: 50,
            page,
            subject_type:
                filterType !== ALL_FILTER ? filterType : undefined,
            action: filterAction !== ALL_FILTER ? filterAction : undefined,
        },
        deps: [filterType, filterAction, page],
    });

    const activity = activityResult?.data ?? [];

    const breadcrumbs: BreadcrumbItem[] = [
        { title: ledger.name, href: ledgerDashboard.url(ledger.id) },
        { title: 'Activity', href: '#' },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`${ledger.name} activity`} />

            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6 lg:p-8">
                <Heading
                    title="Activity"
                    description="Recent create, update, delete, and restore events for this workspace."
                />

                {/* Filters */}
                <div className="flex flex-wrap items-center gap-3">
                    <Select
                        value={filterType}
                        onValueChange={(val) => {
                            setFilterType(val);
                            setPage(1);
                        }}
                    >
                        <SelectTrigger className="w-44">
                            <SelectValue placeholder="All types" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value={ALL_FILTER}>
                                All types
                            </SelectItem>
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
                            setPage(1);
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

                {loading ? (
                    <ActivityLoadingSkeleton />
                ) : (
                    <div className="grid gap-3">
                        {activity.length === 0 ? (
                            <EmptyState
                                icon={<ClipboardList className="size-6" />}
                                title="No activity yet"
                                description="Changes to your workspace will appear here."
                            />
                        ) : (
                            activity.map((entry) => (
                                <ActivityEntry key={entry.id} entry={entry} />
                            ))
                        )}
                    </div>
                )}

                {/* Pagination */}
                {activityResult && activityResult.last_page > 1 && (
                    <div className="flex items-center justify-center gap-2">
                        <button
                            type="button"
                            className="rounded px-3 py-1 text-sm text-muted-foreground hover:bg-muted disabled:opacity-50"
                            disabled={page <= 1}
                            onClick={() => setPage((p) => p - 1)}
                        >
                            Previous
                        </button>
                        <span className="text-sm text-muted-foreground">
                            Page {activityResult.current_page} of{' '}
                            {activityResult.last_page}
                        </span>
                        <button
                            type="button"
                            className="rounded px-3 py-1 text-sm text-muted-foreground hover:bg-muted disabled:opacity-50"
                            disabled={page >= activityResult.last_page}
                            onClick={() => setPage((p) => p + 1)}
                        >
                            Next
                        </button>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
