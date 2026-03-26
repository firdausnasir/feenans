import { Head } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';

export default function AdminMemberships() {
    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Admin', href: '/admin' },
                { title: 'Memberships', href: '/admin/memberships' },
            ]}
        >
            <Head title="Admin - Memberships" />
            <div className="p-4">Memberships page placeholder</div>
        </AppLayout>
    );
}
