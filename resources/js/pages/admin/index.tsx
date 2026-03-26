import { Head } from '@inertiajs/react';
import {
    Activity,
    BookOpen,
    CreditCard,
    Crown,
    Receipt,
    UserCheck,
    UserPlus,
    Users,
} from 'lucide-react';
import { useCallback, useEffect, useState } from 'react';

import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
} from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

type OverviewData = {
    users: {
        total: number;
        verified: number;
        new_today: number;
        new_this_week: number;
        active_last_7d: number;
    };
    memberships: { by_tier: Record<string, number> };
    ledgers: { total: number };
    transactions: { created_today: number; created_this_week: number };
};

type StatCardProps = {
    icon: React.ElementType;
    label: string;
    value: number;
};

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Dashboard', href: '/admin' }];

function StatCard({ icon: Icon, label, value }: StatCardProps) {
    return (
        <Card>
            <CardHeader>
                <CardDescription className="flex items-center gap-1.5">
                    <Icon className="size-3.5" />
                    {label}
                </CardDescription>
            </CardHeader>
            <CardContent>
                <p className="text-2xl font-bold tabular-nums">
                    {value.toLocaleString()}
                </p>
            </CardContent>
        </Card>
    );
}

function DashboardSkeleton() {
    return (
        <div className="grid grid-cols-2 gap-3 md:grid-cols-3 lg:grid-cols-4">
            {Array.from({ length: 10 }).map((_, i) => (
                <Card key={i}>
                    <CardHeader>
                        <Skeleton className="h-4 w-24" />
                    </CardHeader>
                    <CardContent>
                        <Skeleton className="h-8 w-16" />
                    </CardContent>
                </Card>
            ))}
        </div>
    );
}

export default function AdminDashboard() {
    const [data, setData] = useState<OverviewData | null>(null);

    const fetchOverview = useCallback(async () => {
        const response = await fetch('/api/admin/overview', {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
        });

        if (response.ok) {
            setData(await response.json());
        }
    }, []);

    useEffect(() => {
        fetchOverview();
    }, [fetchOverview]);

    const stats: StatCardProps[] = data
        ? [
              { icon: Users, label: 'Total Users', value: data.users.total },
              {
                  icon: UserCheck,
                  label: 'Verified Users',
                  value: data.users.verified,
              },
              {
                  icon: UserPlus,
                  label: 'New Today',
                  value: data.users.new_today,
              },
              {
                  icon: UserPlus,
                  label: 'New This Week',
                  value: data.users.new_this_week,
              },
              {
                  icon: Activity,
                  label: 'Active (7d)',
                  value: data.users.active_last_7d,
              },
              {
                  icon: BookOpen,
                  label: 'Total Ledgers',
                  value: data.ledgers.total,
              },
              {
                  icon: CreditCard,
                  label: 'Free Members',
                  value: data.memberships.by_tier.free ?? 0,
              },
              {
                  icon: Crown,
                  label: 'Premium Members',
                  value: data.memberships.by_tier.premium ?? 0,
              },
              {
                  icon: Receipt,
                  label: 'Transactions Today',
                  value: data.transactions.created_today,
              },
              {
                  icon: Receipt,
                  label: 'Transactions This Week',
                  value: data.transactions.created_this_week,
              },
          ]
        : [];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Admin - Dashboard" />
            <div className="space-y-6 p-4 md:p-6">
                {data ? (
                    <div className="grid grid-cols-2 gap-3 md:grid-cols-3 lg:grid-cols-4">
                        {stats.map((stat) => (
                            <StatCard key={stat.label} {...stat} />
                        ))}
                    </div>
                ) : (
                    <DashboardSkeleton />
                )}
            </div>
        </AppLayout>
    );
}
