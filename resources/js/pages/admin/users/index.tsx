import { Head } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';

export default function AdminUsers() {
    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Admin', href: '/admin' },
                { title: 'Users', href: '/admin/users' },
            ]}
        >
            <Head title="Admin - Users" />
            <div className="p-4">Users page placeholder</div>
        </AppLayout>
    );
}
