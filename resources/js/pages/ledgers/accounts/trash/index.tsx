import { Head } from '@inertiajs/react';
import Heading from '@/components/heading';
import { Card, CardContent } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { dashboard as ledgerDashboard } from '@/routes/ledgers';
import { index as accountsIndex } from '@/routes/ledgers/accounts';
import type { Account, BreadcrumbItem, Ledger } from '@/types';

export default function AccountTrashIndex({
    ledger,
    accounts,
}: {
    ledger: Ledger;
    accounts: Account[];
}) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: ledger.name, href: ledgerDashboard.url(ledger.id) },
        { title: 'Accounts', href: accountsIndex.url(ledger.id) },
        { title: 'Trash', href: '#' },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`${ledger.name} account trash`} />

            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <Heading
                    title="Account Trash"
                    description="Recently deleted accounts available for restore or permanent deletion."
                />

                <div className="grid gap-3">
                    {accounts.length === 0 ? (
                        <p className="text-sm text-muted-foreground">
                            No deleted accounts.
                        </p>
                    ) : (
                        accounts.map((account) => (
                            <Card key={account.id}>
                                <CardContent className="py-4">
                                    <p className="font-medium">
                                        {account.name}
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
