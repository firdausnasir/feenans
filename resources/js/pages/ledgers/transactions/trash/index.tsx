import { Head } from '@inertiajs/react';
import Heading from '@/components/heading';
import { Card, CardContent } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { formatDate } from '@/lib/format';
import { dashboard as ledgerDashboard } from '@/routes/ledgers';
import { index as transactionsIndex } from '@/routes/ledgers/transactions';
import type { BreadcrumbItem, Ledger, Pagination, Transaction } from '@/types';

export default function TransactionTrashIndex({
    ledger,
    transactions,
}: {
    ledger: Ledger;
    transactions: Pagination<Transaction>;
}) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: ledger.name, href: ledgerDashboard.url(ledger.id) },
        { title: 'Transactions', href: transactionsIndex.url(ledger.id) },
        { title: 'Trash', href: '#' },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`${ledger.name} transaction trash`} />

            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <Heading
                    title="Transaction Trash"
                    description="Recently deleted transactions that can still be restored."
                />

                <div className="grid gap-3">
                    {transactions.data.length === 0 ? (
                        <p className="text-sm text-muted-foreground">
                            No deleted transactions.
                        </p>
                    ) : (
                        transactions.data.map((transaction) => (
                            <Card key={transaction.id}>
                                <CardContent className="flex items-center justify-between gap-4 py-4">
                                    <div>
                                        <p className="font-medium">
                                            {transaction.description ??
                                                'Untitled transaction'}
                                        </p>
                                        <p className="text-sm text-muted-foreground">
                                            {formatDate(
                                                transaction.transaction_date,
                                            )}
                                        </p>
                                    </div>
                                </CardContent>
                            </Card>
                        ))
                    )}
                </div>
            </div>
        </AppLayout>
    );
}
