import { Head } from '@inertiajs/react';
import Heading from '@/components/heading';
import { Card, CardContent } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { dashboard as ledgerDashboard } from '@/routes/ledgers';
import type { BreadcrumbItem, Ledger } from '@/types';

type ActivityItem = {
    id: number;
    action: string;
    subject_type: string;
    created_at: string;
    user?: { name: string } | null;
};

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

            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <Heading
                    title="Activity"
                    description="Recent create, update, delete, and restore events for this ledger."
                />

                <div className="grid gap-3">
                    {activity.length === 0 ? (
                        <p className="text-sm text-muted-foreground">
                            No activity yet.
                        </p>
                    ) : (
                        activity.map((entry) => (
                            <Card key={entry.id}>
                                <CardContent className="flex items-center justify-between gap-4 py-4">
                                    <div>
                                        <p className="font-medium">
                                            {entry.action}
                                        </p>
                                        <p className="text-sm text-muted-foreground">
                                            {entry.subject_type}
                                        </p>
                                    </div>
                                    <p className="text-sm text-muted-foreground">
                                        {entry.user?.name ?? 'System'}
                                    </p>
                                </CardContent>
                            </Card>
                        ))
                    )}
                </div>
            </div>
        </AppLayout>
    );
}
