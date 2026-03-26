import { Head } from '@inertiajs/react';
import { useCallback, useEffect, useState } from 'react';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Skeleton } from '@/components/ui/skeleton';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { overview } from '@/routes/admin';
import { pages } from '@/routes/admin/analytics';
import { index as usersIndex } from '@/routes/admin/users';
import { update as updateMembership } from '@/routes/admin/users/membership';

type OverviewData = {
    users: { total: number; verified: number };
    memberships: {
        by_tier: Record<string, number>;
        by_status: Record<string, number>;
    };
    analytics: { today_hits: number; last_30_days_hits: number };
};

type AnalyticsData = {
    days: number;
    daily_trend: { date: string; hits: number }[];
    top_pages: { page_key: string; hits: number }[];
    by_audience: Record<string, number>;
};

type UserMembership = {
    tier: string;
    status: string;
};

type AdminUser = {
    id: number;
    name: string;
    email: string;
    email_verified_at: string | null;
    created_at: string;
    membership: UserMembership;
};

type UsersData = {
    data: AdminUser[];
    filters: { search: string; tier: string; status: string };
};

type EditingUser = {
    user: AdminUser;
    tier: string;
    status: string;
    reason: string;
};

function tierBadgeVariant(tier: string) {
    return tier === 'premium' ? 'default' : 'secondary';
}

function statusBadgeVariant(status: string) {
    switch (status) {
        case 'active':
            return 'default';
        case 'trialing':
            return 'secondary';
        case 'past_due':
        case 'canceled':
            return 'destructive';
        default:
            return 'outline';
    }
}

function OverviewSkeleton() {
    return (
        <div className="grid grid-cols-2 gap-3 md:grid-cols-3 lg:grid-cols-6">
            {Array.from({ length: 6 }).map((_, i) => (
                <Card key={i}>
                    <CardHeader>
                        <Skeleton className="h-4 w-20" />
                    </CardHeader>
                    <CardContent>
                        <Skeleton className="h-8 w-16" />
                    </CardContent>
                </Card>
            ))}
        </div>
    );
}

function AnalyticsSkeleton() {
    return (
        <Card>
            <CardHeader>
                <Skeleton className="h-5 w-32" />
            </CardHeader>
            <CardContent className="space-y-4">
                <Skeleton className="h-48 w-full" />
                <Skeleton className="h-32 w-full" />
            </CardContent>
        </Card>
    );
}

function UsersSkeleton() {
    return (
        <Card>
            <CardHeader>
                <Skeleton className="h-5 w-40" />
            </CardHeader>
            <CardContent className="space-y-3">
                {Array.from({ length: 5 }).map((_, i) => (
                    <Skeleton key={i} className="h-12 w-full" />
                ))}
            </CardContent>
        </Card>
    );
}

function OverviewSection({ data }: { data: OverviewData }) {
    const stats = [
        { label: 'Total Users', value: data.users.total },
        { label: 'Verified', value: data.users.verified },
        { label: 'Free', value: data.memberships.by_tier.free ?? 0 },
        { label: 'Premium', value: data.memberships.by_tier.premium ?? 0 },
        { label: 'Hits Today', value: data.analytics.today_hits },
        { label: 'Hits (30d)', value: data.analytics.last_30_days_hits },
    ];

    return (
        <div className="grid grid-cols-2 gap-3 md:grid-cols-3 lg:grid-cols-6">
            {stats.map((stat) => (
                <Card key={stat.label}>
                    <CardHeader>
                        <CardDescription>{stat.label}</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <p className="text-2xl font-bold tabular-nums">{stat.value.toLocaleString()}</p>
                    </CardContent>
                </Card>
            ))}
        </div>
    );
}

function AnalyticsSection({
    data,
    days,
    onDaysChange,
}: {
    data: AnalyticsData;
    days: number;
    onDaysChange: (days: number) => void;
}) {
    const maxHits = Math.max(...data.daily_trend.map((d) => d.hits), 1);

    return (
        <Card>
            <CardHeader>
                <div className="flex items-center justify-between gap-4">
                    <CardTitle>Page Analytics</CardTitle>
                    <div className="flex gap-1">
                        {[7, 30].map((d) => (
                            <Button key={d} variant={days === d ? 'default' : 'outline'} size="sm" onClick={() => onDaysChange(d)}>
                                {d}d
                            </Button>
                        ))}
                    </div>
                </div>
            </CardHeader>
            <CardContent className="space-y-6">
                {data.daily_trend.length > 0 && (
                    <div className="space-y-2">
                        <p className="text-muted-foreground text-sm font-medium">Daily Hits</p>
                        <div className="flex items-end gap-px overflow-x-auto" style={{ minHeight: 120 }}>
                            {data.daily_trend.map((d) => (
                                <div key={d.date} className="group relative flex min-w-2 flex-1 flex-col items-center justify-end" style={{ height: 120 }}>
                                    <div
                                        className="bg-primary w-full min-w-1.5 rounded-t transition-all group-hover:opacity-80"
                                        style={{ height: `${Math.max((d.hits / maxHits) * 100, 2)}%` }}
                                    />
                                    <span className="text-muted-foreground pointer-events-none absolute -top-5 hidden text-xs group-hover:block">
                                        {d.hits}
                                    </span>
                                </div>
                            ))}
                        </div>
                    </div>
                )}

                {data.top_pages.length > 0 && (
                    <div className="space-y-2">
                        <p className="text-muted-foreground text-sm font-medium">Top Pages</p>
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Page</TableHead>
                                    <TableHead className="text-right">Hits</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {data.top_pages.map((page) => (
                                    <TableRow key={page.page_key}>
                                        <TableCell className="font-mono text-sm">{page.page_key}</TableCell>
                                        <TableCell className="text-right tabular-nums">{page.hits.toLocaleString()}</TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </div>
                )}

                {Object.keys(data.by_audience).length > 0 && (
                    <div className="space-y-2">
                        <p className="text-muted-foreground text-sm font-medium">By Audience</p>
                        <div className="flex gap-4">
                            {Object.entries(data.by_audience).map(([audience, hits]) => (
                                <div key={audience} className="text-sm">
                                    <span className="text-muted-foreground capitalize">{audience}:</span>{' '}
                                    <span className="font-medium tabular-nums">{hits.toLocaleString()}</span>
                                </div>
                            ))}
                        </div>
                    </div>
                )}
            </CardContent>
        </Card>
    );
}

function MembershipsSection({
    data,
    onSearch,
    onTierFilter,
    onStatusFilter,
    onEdit,
}: {
    data: UsersData;
    onSearch: (search: string) => void;
    onTierFilter: (tier: string) => void;
    onStatusFilter: (status: string) => void;
    onEdit: (user: AdminUser) => void;
}) {
    return (
        <Card>
            <CardHeader>
                <CardTitle>Memberships</CardTitle>
            </CardHeader>
            <CardContent className="space-y-4">
                <div className="flex flex-col gap-3 sm:flex-row">
                    <Input
                        placeholder="Search name or email..."
                        defaultValue={data.filters.search}
                        onChange={(e) => onSearch(e.target.value)}
                        className="sm:max-w-xs"
                    />
                    <Select value={data.filters.tier || 'all'} onValueChange={(v) => onTierFilter(v === 'all' ? '' : v)}>
                        <SelectTrigger className="sm:w-32">
                            <SelectValue placeholder="Tier" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All Tiers</SelectItem>
                            <SelectItem value="free">Free</SelectItem>
                            <SelectItem value="premium">Premium</SelectItem>
                        </SelectContent>
                    </Select>
                    <Select value={data.filters.status || 'all'} onValueChange={(v) => onStatusFilter(v === 'all' ? '' : v)}>
                        <SelectTrigger className="sm:w-36">
                            <SelectValue placeholder="Status" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All Statuses</SelectItem>
                            <SelectItem value="active">Active</SelectItem>
                            <SelectItem value="trialing">Trialing</SelectItem>
                            <SelectItem value="past_due">Past Due</SelectItem>
                            <SelectItem value="canceled">Canceled</SelectItem>
                        </SelectContent>
                    </Select>
                </div>

                {/* Mobile card layout */}
                <div className="space-y-3 md:hidden">
                    {data.data.map((user) => (
                        <div key={user.id} className="rounded-lg border p-3">
                            <div className="flex items-start justify-between gap-2">
                                <div className="min-w-0">
                                    <p className="truncate font-medium">{user.name}</p>
                                    <p className="text-muted-foreground truncate text-sm">{user.email}</p>
                                </div>
                                <Button variant="outline" size="sm" onClick={() => onEdit(user)}>
                                    Edit
                                </Button>
                            </div>
                            <div className="mt-2 flex items-center gap-2">
                                <Badge variant={tierBadgeVariant(user.membership.tier)}>{user.membership.tier}</Badge>
                                <Badge variant={statusBadgeVariant(user.membership.status)}>{user.membership.status}</Badge>
                            </div>
                        </div>
                    ))}
                </div>

                {/* Desktop table layout */}
                <div className="hidden md:block">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Name</TableHead>
                                <TableHead>Email</TableHead>
                                <TableHead>Tier</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead>Joined</TableHead>
                                <TableHead />
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {data.data.map((user) => (
                                <TableRow key={user.id}>
                                    <TableCell className="font-medium">{user.name}</TableCell>
                                    <TableCell>{user.email}</TableCell>
                                    <TableCell>
                                        <Badge variant={tierBadgeVariant(user.membership.tier)}>{user.membership.tier}</Badge>
                                    </TableCell>
                                    <TableCell>
                                        <Badge variant={statusBadgeVariant(user.membership.status)}>{user.membership.status}</Badge>
                                    </TableCell>
                                    <TableCell className="text-muted-foreground text-sm">
                                        {new Date(user.created_at).toLocaleDateString()}
                                    </TableCell>
                                    <TableCell>
                                        <Button variant="outline" size="sm" onClick={() => onEdit(user)}>
                                            Edit
                                        </Button>
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </div>

                {data.data.length === 0 && (
                    <p className="text-muted-foreground py-8 text-center text-sm">No users match your filters.</p>
                )}
            </CardContent>
        </Card>
    );
}

function EditMembershipDialog({
    editing,
    onClose,
    onChange,
    onSave,
    saving,
}: {
    editing: EditingUser;
    onClose: () => void;
    onChange: (field: keyof EditingUser, value: string) => void;
    onSave: () => void;
    saving: boolean;
}) {
    return (
        <Dialog open onOpenChange={(open) => !open && onClose()}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Edit Membership</DialogTitle>
                    <DialogDescription>
                        Update membership for {editing.user.name} ({editing.user.email})
                    </DialogDescription>
                </DialogHeader>
                <div className="space-y-4 py-4">
                    <div className="space-y-2">
                        <Label>Tier</Label>
                        <Select value={editing.tier} onValueChange={(v) => onChange('tier', v)}>
                            <SelectTrigger>
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="free">Free</SelectItem>
                                <SelectItem value="premium">Premium</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <div className="space-y-2">
                        <Label>Status</Label>
                        <Select value={editing.status} onValueChange={(v) => onChange('status', v)}>
                            <SelectTrigger>
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="active">Active</SelectItem>
                                <SelectItem value="trialing">Trialing</SelectItem>
                                <SelectItem value="past_due">Past Due</SelectItem>
                                <SelectItem value="canceled">Canceled</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <div className="space-y-2">
                        <Label>Reason (optional)</Label>
                        <Input
                            placeholder="Why is this change being made?"
                            value={editing.reason}
                            onChange={(e) => onChange('reason', e.target.value)}
                        />
                    </div>
                </div>
                <DialogFooter>
                    <DialogClose asChild>
                        <Button variant="outline">Cancel</Button>
                    </DialogClose>
                    <Button onClick={onSave} disabled={saving}>
                        {saving ? 'Saving...' : 'Save'}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

async function apiFetch<T>(url: string, options?: RequestInit): Promise<T> {
    const response = await fetch(url, {
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            ...options?.headers,
        },
        credentials: 'same-origin',
        ...options,
    });

    if (!response.ok) {
        throw new Error(`API request failed: ${response.status}`);
    }

    return response.json() as Promise<T>;
}

export default function AdminIndex() {
    const [overviewData, setOverviewData] = useState<OverviewData | null>(null);
    const [analyticsData, setAnalyticsData] = useState<AnalyticsData | null>(null);
    const [usersData, setUsersData] = useState<UsersData | null>(null);
    const [analyticsDays, setAnalyticsDays] = useState(30);
    const [editing, setEditing] = useState<EditingUser | null>(null);
    const [saving, setSaving] = useState(false);
    const [searchTimeout, setSearchTimeout] = useState<ReturnType<typeof setTimeout> | null>(null);
    const [filters, setFilters] = useState({ search: '', tier: '', status: '' });

    const fetchOverview = useCallback(async () => {
        const data = await apiFetch<OverviewData>(overview.url());
        setOverviewData(data);
    }, []);

    const fetchAnalytics = useCallback(
        async (days: number) => {
            const data = await apiFetch<AnalyticsData>(pages.url({ query: { days } }));
            setAnalyticsData(data);
        },
        [],
    );

    const fetchUsers = useCallback(async (params: { search?: string; tier?: string; status?: string }) => {
        const query: Record<string, string> = {};

        if (params.search) {
query.search = params.search;
}

        if (params.tier) {
query.tier = params.tier;
}

        if (params.status) {
query.status = params.status;
}

        const data = await apiFetch<UsersData>(usersIndex.url({ query }));
        setUsersData(data);
    }, []);

    useEffect(() => {
        fetchOverview();
        fetchAnalytics(analyticsDays);
        fetchUsers(filters);
    }, []); // eslint-disable-line react-hooks/exhaustive-deps

    function handleDaysChange(days: number) {
        setAnalyticsDays(days);
        fetchAnalytics(days);
    }

    function handleSearch(search: string) {
        if (searchTimeout) {
clearTimeout(searchTimeout);
}

        const timeout = setTimeout(() => {
            const next = { ...filters, search };
            setFilters(next);
            fetchUsers(next);
        }, 300);
        setSearchTimeout(timeout);
    }

    function handleTierFilter(tier: string) {
        const next = { ...filters, tier };
        setFilters(next);
        fetchUsers(next);
    }

    function handleStatusFilter(status: string) {
        const next = { ...filters, status };
        setFilters(next);
        fetchUsers(next);
    }

    function handleEdit(user: AdminUser) {
        setEditing({
            user,
            tier: user.membership.tier,
            status: user.membership.status,
            reason: '',
        });
    }

    function handleEditChange(field: keyof EditingUser, value: string) {
        if (!editing) {
return;
}

        setEditing({ ...editing, [field]: value });
    }

    async function handleSave() {
        if (!editing) {
return;
}

        setSaving(true);

        try {
            const xsrfCookie = document.cookie
                .split('; ')
                .find((c) => c.startsWith('XSRF-TOKEN='))
                ?.split('=')[1];

            await apiFetch(updateMembership.url({ user: editing.user.id }), {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                    ...(xsrfCookie ? { 'X-XSRF-TOKEN': decodeURIComponent(xsrfCookie) } : {}),
                },
                body: JSON.stringify({
                    tier: editing.tier,
                    status: editing.status,
                    reason: editing.reason || undefined,
                }),
            });
            setEditing(null);
            fetchUsers(filters);
            fetchOverview();
        } finally {
            setSaving(false);
        }
    }

    return (
        <>
            <Head title="Admin Console" />
            <div className="mx-auto min-h-screen max-w-7xl space-y-6 p-4 md:p-6">
                <h1 className="text-2xl font-bold">Admin Console</h1>

                {overviewData ? <OverviewSection data={overviewData} /> : <OverviewSkeleton />}

                {analyticsData ? (
                    <AnalyticsSection data={analyticsData} days={analyticsDays} onDaysChange={handleDaysChange} />
                ) : (
                    <AnalyticsSkeleton />
                )}

                {usersData ? (
                    <MembershipsSection
                        data={usersData}
                        onSearch={handleSearch}
                        onTierFilter={handleTierFilter}
                        onStatusFilter={handleStatusFilter}
                        onEdit={handleEdit}
                    />
                ) : (
                    <UsersSkeleton />
                )}

                {editing && (
                    <EditMembershipDialog
                        editing={editing}
                        onClose={() => setEditing(null)}
                        onChange={handleEditChange}
                        onSave={handleSave}
                        saving={saving}
                    />
                )}
            </div>
        </>
    );
}
