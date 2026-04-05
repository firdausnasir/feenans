import { Head, Link, useHttp } from '@inertiajs/react';
import { PlusCircle } from 'lucide-react';
import { useEffect, useState } from 'react';
import { index as ledgersLoader } from '@/actions/App/Http/Controllers/Api/V1/LedgerController';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import AppLayout from '@/layouts/app-layout';
import { create, index, dashboard } from '@/routes/ledgers';
import type { BreadcrumbItem } from '@/types';
import { BoneSkeleton } from '@/components/ui/bone-skeleton';

type Ledger = {
    id: number;
    name: string;
    currency_code: string;
};

type ApiEnvelope<T> = { data: T };

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Workspaces',
        href: index.url(),
    },
];

function LedgersLoadingSkeleton() {
    return (
        <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            {Array.from({ length: 3 }).map((_, i) => (
                <div
                    key={i}
                    className="rounded-xl border border-sidebar-border/70 bg-card p-5"
                >
                    <div className="space-y-2">
                        <Skeleton className="h-5 w-32" />
                        <Skeleton className="h-4 w-48" />
                    </div>
                    <Skeleton className="mt-4 h-4 w-24" />
                </div>
            ))}
        </div>
    );
}

function LedgersErrorState({ onRetry }: { readonly onRetry: () => void }) {
    return (
        <Card>
            <CardContent className="flex flex-col gap-3 py-4">
                <p className="text-sm text-muted-foreground">
                    Failed to load workspaces.
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

export default function LedgersIndex() {
    const loaderState = useHttp<Record<string, never>, ApiEnvelope<Ledger[]>>(
        {},
    );
    const [hasLoaded, setHasLoaded] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const ledgers = loaderState.response?.data ?? [];

    async function loadLedgers(): Promise<boolean> {
        let cancelled = false;

        loaderState.cancel();
        setError(null);

        try {
            await loaderState.get(ledgersLoader.url(), {
                onCancel: () => {
                    cancelled = true;
                },
            });

            return true;
        } catch {
            if (!cancelled) {
                setError('Failed to load workspaces.');
            }

            return false;
        } finally {
            if (!cancelled) {
                setHasLoaded(true);
            }
        }
    }

    useEffect(() => {
        void loadLedgers();

        return () => {
            loaderState.cancel();
        };
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Workspaces" />

            <div className="flex h-full flex-1 flex-col gap-4 p-4 md:p-6">
                <div className="flex items-center justify-between">
                    <p className="text-sm text-muted-foreground">
                        Choose the workspace you want to work in. Each workspace
                        acts as its own financial space.
                    </p>

                    <Link
                        href={create()}
                        className="inline-flex items-center gap-2 rounded-md bg-foreground px-4 py-2 text-sm font-medium text-background"
                    >
                        <PlusCircle className="size-4" />
                        New workspace
                    </Link>
                </div>

                <BoneSkeleton
                    name="ledgers-list"
                    loading={!hasLoaded}
                    fallback={<LedgersLoadingSkeleton />}
                >
                    {error ? (
                        <LedgersErrorState
                            onRetry={() => void loadLedgers()}
                        />
                    ) : (
                        <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                            {ledgers.map((ledger) => (
                                <Link
                                    key={ledger.id}
                                    href={dashboard(ledger.id)}
                                    className="rounded-xl border border-sidebar-border/70 bg-card p-5 shadow-sm transition hover:border-foreground/20"
                                >
                                    <div className="space-y-1">
                                        <p className="text-lg font-semibold">
                                            {ledger.name}
                                        </p>
                                        <p className="text-sm text-muted-foreground">
                                            Workspace currency:{' '}
                                            {ledger.currency_code}
                                        </p>
                                    </div>

                                    <p className="mt-4 text-sm font-medium text-foreground/80">
                                        Open dashboard
                                    </p>
                                </Link>
                            ))}
                        </div>
                    )}
                </BoneSkeleton>
            </div>
        </AppLayout>
    );
}
