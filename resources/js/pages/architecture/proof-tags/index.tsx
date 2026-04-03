import { Head, usePage } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import { dashboard as ledgerDashboard } from '@/routes/ledgers';

type ProofPageProps = {
    currentLedger: {
        id: number;
        name: string;
    };
    flash: {
        success: string | null;
    };
    proof: {
        ledger_id: number;
        user_id: number | null;
    };
};

export default function ProofTagsIndex() {
    const { currentLedger, flash, proof } = usePage<ProofPageProps>().props;

    const breadcrumbs: BreadcrumbItem[] = [
        {
            title: currentLedger.name,
            href: ledgerDashboard.url(currentLedger.id),
        },
        {
            title: 'Architecture Proof',
            href: '#',
        },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Architecture Proof - ${currentLedger.name}`} />

            <div className="flex h-full flex-1 flex-col gap-4 p-4 md:p-6">
                <div className="rounded-xl border border-dashed border-border bg-card p-6">
                    <div className="space-y-2">
                        <p className="text-sm font-medium uppercase tracking-[0.2em] text-muted-foreground">
                            Temporary architecture proof
                        </p>
                        <h1 className="text-2xl font-semibold text-foreground">
                            Shared request pipeline spike
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            This page exists only for the Phase 1 proof route.
                        </p>
                    </div>

                    <dl className="mt-6 grid gap-3 text-sm sm:grid-cols-2">
                        <div className="rounded-lg bg-muted/50 p-3">
                            <dt className="text-muted-foreground">Ledger ID</dt>
                            <dd className="font-medium text-foreground">
                                {proof.ledger_id}
                            </dd>
                        </div>
                        <div className="rounded-lg bg-muted/50 p-3">
                            <dt className="text-muted-foreground">User ID</dt>
                            <dd className="font-medium text-foreground">
                                {proof.user_id ?? 'guest'}
                            </dd>
                        </div>
                    </dl>

                    {flash.success ? (
                        <div className="mt-6 rounded-lg border border-border bg-background p-4 text-sm text-foreground">
                            {flash.success}
                        </div>
                    ) : null}
                </div>
            </div>
        </AppLayout>
    );
}
