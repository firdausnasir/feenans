import { Head } from '@inertiajs/react';
import { ClipboardList } from 'lucide-react';
import { useState } from 'react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent } from '@/components/ui/card';
import { EmptyState } from '@/components/ui/empty-state';
import AppLayout from '@/layouts/app-layout';
import { dashboard as ledgerDashboard } from '@/routes/ledgers';
import type { BreadcrumbItem, Ledger } from '@/types';

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

export default function ActivityIndex({
    ledger,
    activity,
}: {
    ledger: Ledger;
    activity: ActivityItem[];
}) {
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
            </div>
        </AppLayout>
    );
}
