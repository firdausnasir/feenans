import { Head } from '@inertiajs/react';
import Heading from '@/components/heading';
import { Card, CardContent } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { dashboard as ledgerDashboard } from '@/routes/ledgers';
import { index as categoriesIndex } from '@/routes/ledgers/categories';
import type { BreadcrumbItem, Category, Ledger } from '@/types';

export default function CategoryTrashIndex({
    ledger,
    categories,
}: {
    ledger: Ledger;
    categories: Category[];
}) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: ledger.name, href: ledgerDashboard.url(ledger.id) },
        { title: 'Categories', href: categoriesIndex.url(ledger.id) },
        { title: 'Trash', href: '#' },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`${ledger.name} category trash`} />

            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6 lg:p-8">
                <Heading
                    title="Category Trash"
                    description="Recently deleted categories that can still be recovered."
                />

                <div className="grid gap-3">
                    {categories.length === 0 ? (
                        <p className="text-sm text-muted-foreground">
                            No deleted categories.
                        </p>
                    ) : (
                        categories.map((category) => (
                            <Card key={category.id}>
                                <CardContent className="py-4">
                                    <p className="font-medium">
                                        {category.name}
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
