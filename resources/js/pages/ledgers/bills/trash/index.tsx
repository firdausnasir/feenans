import { Head } from '@inertiajs/react';
import Heading from '@/components/heading';
import { Card, CardContent } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { dashboard as ledgerDashboard } from '@/routes/ledgers';
import { index as billsIndex } from '@/routes/ledgers/bills';
import type { Bill, BreadcrumbItem, Ledger } from '@/types';

export default function BillTrashIndex({
    ledger,
    bills,
}: {
    ledger: Ledger;
    bills: Bill[];
}) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: ledger.name, href: ledgerDashboard.url(ledger.id) },
        { title: 'Bills', href: billsIndex.url(ledger.id) },
        { title: 'Trash', href: '#' },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`${ledger.name} bill trash`} />

            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <Heading
                    title="Bill Trash"
                    description="Deleted bills remain here until restored or permanently removed."
                />

                <div className="grid gap-3">
                    {bills.length === 0 ? (
                        <p className="text-sm text-muted-foreground">
                            No deleted bills.
                        </p>
                    ) : (
                        bills.map((bill) => (
                            <Card key={bill.id}>
                                <CardContent className="py-4">
                                    <p className="font-medium">{bill.name}</p>
                                </CardContent>
                            </Card>
                        ))
                    )}
                </div>
            </div>
        </AppLayout>
    );
}
