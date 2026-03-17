import { Head, Link } from '@inertiajs/react';
import { PlusCircle } from 'lucide-react';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import { create, index, dashboard } from '@/routes/ledgers';

type Ledger = {
    id: number;
    name: string;
    currency_code: string;
};

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Workspaces',
        href: index.url(),
    },
];

export default function LedgersIndex({ ledgers }: { ledgers: Ledger[] }) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Workspaces" />

            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6 lg:p-8">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">
                            Workspaces
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Choose the workspace you want to work in. Each
                            workspace acts as its own financial space.
                        </p>
                    </div>

                    <Link
                        href={create()}
                        className="inline-flex items-center gap-2 rounded-md bg-foreground px-4 py-2 text-sm font-medium text-background"
                    >
                        <PlusCircle className="size-4" />
                        New workspace
                    </Link>
                </div>

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
                                    Workspace currency: {ledger.currency_code}
                                </p>
                            </div>

                            <p className="mt-4 text-sm font-medium text-foreground/80">
                                Open dashboard
                            </p>
                        </Link>
                    ))}
                </div>
            </div>
        </AppLayout>
    );
}
