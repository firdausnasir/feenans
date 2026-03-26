import { Head } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Premium', href: '/premium' }];

export default function PremiumIndex() {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Premium" />
            <div className="p-4">
                <h1>Premium placeholder</h1>
            </div>
        </AppLayout>
    );
}
