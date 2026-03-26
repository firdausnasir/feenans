import { Head } from '@inertiajs/react';
import { useCallback, useEffect, useRef, useState } from 'react';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
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
import AppLayout from '@/layouts/app-layout';

type UserMembership = {
    tier: 'free' | 'premium';
    status: 'active' | 'trialing' | 'past_due' | 'canceled';
};

type AdminUser = {
    id: number;
    name: string;
    email: string;
    email_verified_at: string | null;
    created_at: string;
    membership: UserMembership;
};

type PaginationMeta = {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
};

type MembershipsResponse = {
    data: AdminUser[];
    meta: PaginationMeta;
    filters: {
        search: string | null;
        tier: string | null;
        status: string | null;
    };
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

function formatStatus(status: string) {
    return status.replace('_', ' ').replace(/\b\w/g, (c) => c.toUpperCase());
}

function getXsrfToken(): string | undefined {
    const cookie = document.cookie
        .split('; ')
        .find((c) => c.startsWith('XSRF-TOKEN='))
        ?.split('=')[1];

    return cookie ? decodeURIComponent(cookie) : undefined;
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

function MembershipsSkeleton() {
    return (
        <>
            {/* Mobile skeleton */}
            <div className="space-y-3 md:hidden">
                {Array.from({ length: 5 }).map((_, i) => (
                    <Card key={i}>
                        <CardContent className="space-y-3 p-4">
                            <div className="flex items-start justify-between gap-2">
                                <div className="min-w-0 flex-1 space-y-2">
                                    <Skeleton className="h-5 w-32" />
                                    <Skeleton className="h-4 w-48" />
                                </div>
                                <Skeleton className="h-8 w-14" />
                            </div>
                            <div className="flex gap-2">
                                <Skeleton className="h-5 w-16" />
                                <Skeleton className="h-5 w-16" />
                            </div>
                        </CardContent>
                    </Card>
                ))}
            </div>

            {/* Desktop skeleton */}
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
                        {Array.from({ length: 5 }).map((_, i) => (
                            <TableRow key={i}>
                                <TableCell>
                                    <Skeleton className="h-5 w-28" />
                                </TableCell>
                                <TableCell>
                                    <Skeleton className="h-5 w-40" />
                                </TableCell>
                                <TableCell>
                                    <Skeleton className="h-5 w-16" />
                                </TableCell>
                                <TableCell>
                                    <Skeleton className="h-5 w-16" />
                                </TableCell>
                                <TableCell>
                                    <Skeleton className="h-5 w-24" />
                                </TableCell>
                                <TableCell>
                                    <Skeleton className="h-8 w-14" />
                                </TableCell>
                            </TableRow>
                        ))}
                    </TableBody>
                </Table>
            </div>
        </>
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
                        Update membership for {editing.user.name} (
                        {editing.user.email})
                    </DialogDescription>
                </DialogHeader>
                <div className="space-y-4 py-4">
                    <div className="space-y-2">
                        <Label>Tier</Label>
                        <Select
                            value={editing.tier}
                            onValueChange={(v) => onChange('tier', v)}
                        >
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
                        <Select
                            value={editing.status}
                            onValueChange={(v) => onChange('status', v)}
                        >
                            <SelectTrigger>
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="active">Active</SelectItem>
                                <SelectItem value="trialing">
                                    Trialing
                                </SelectItem>
                                <SelectItem value="past_due">
                                    Past Due
                                </SelectItem>
                                <SelectItem value="canceled">
                                    Canceled
                                </SelectItem>
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

export default function AdminMemberships() {
    const [data, setData] = useState<MembershipsResponse | null>(null);
    const [loading, setLoading] = useState(true);
    const [filters, setFilters] = useState({
        search: '',
        tier: '',
        status: '',
    });
    const [page, setPage] = useState(1);
    const [editing, setEditing] = useState<EditingUser | null>(null);
    const [saving, setSaving] = useState(false);
    const searchTimeoutRef = useRef<ReturnType<typeof setTimeout> | null>(null);

    const fetchMemberships = useCallback(
        async (
            params: { search: string; tier: string; status: string },
            targetPage: number,
        ) => {
            setLoading(true);

            try {
                const query = new URLSearchParams();

                if (params.search) {
                    query.set('search', params.search);
                }

                if (params.tier) {
                    query.set('tier', params.tier);
                }

                if (params.status) {
                    query.set('status', params.status);
                }

                if (targetPage > 1) {
                    query.set('page', String(targetPage));
                }

                const queryString = query.toString();
                const url = `/api/admin/memberships${queryString ? `?${queryString}` : ''}`;
                const response = await apiFetch<MembershipsResponse>(url);

                setData(response);
            } finally {
                setLoading(false);
            }
        },
        [],
    );

    useEffect(() => {
        fetchMemberships(filters, page);
    }, []); // eslint-disable-line react-hooks/exhaustive-deps

    function handleSearch(value: string) {
        if (searchTimeoutRef.current) {
            clearTimeout(searchTimeoutRef.current);
        }

        searchTimeoutRef.current = setTimeout(() => {
            const next = { ...filters, search: value };
            setFilters(next);
            setPage(1);
            fetchMemberships(next, 1);
        }, 300);
    }

    function handleTierFilter(value: string) {
        const tier = value === 'all' ? '' : value;
        const next = { ...filters, tier };
        setFilters(next);
        setPage(1);
        fetchMemberships(next, 1);
    }

    function handleStatusFilter(value: string) {
        const status = value === 'all' ? '' : value;
        const next = { ...filters, status };
        setFilters(next);
        setPage(1);
        fetchMemberships(next, 1);
    }

    function handlePageChange(targetPage: number) {
        setPage(targetPage);
        fetchMemberships(filters, targetPage);
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
            const xsrfToken = getXsrfToken();

            await apiFetch(`/api/admin/users/${editing.user.id}/membership`, {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                    ...(xsrfToken ? { 'X-XSRF-TOKEN': xsrfToken } : {}),
                },
                body: JSON.stringify({
                    tier: editing.tier,
                    status: editing.status,
                    reason: editing.reason || undefined,
                }),
            });

            setEditing(null);
            fetchMemberships(filters, page);
        } finally {
            setSaving(false);
        }
    }

    const isLoading = loading && !data;
    const hasResults = data && data.data.length > 0;
    const isEmpty = data && data.data.length === 0;
    const meta = data?.meta;

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Admin', href: '/admin' },
                { title: 'Memberships', href: '/admin/memberships' },
            ]}
        >
            <Head title="Admin - Memberships" />

            <div className="mx-auto max-w-7xl space-y-6 p-4 md:p-6">
                <h1 className="text-2xl font-bold">Memberships</h1>

                {/* Filters */}
                <div className="flex flex-col gap-3 sm:flex-row">
                    <Input
                        placeholder="Search name or email..."
                        defaultValue={filters.search}
                        onChange={(e) => handleSearch(e.target.value)}
                        className="sm:max-w-xs"
                    />
                    <Select
                        value={filters.tier || 'all'}
                        onValueChange={handleTierFilter}
                    >
                        <SelectTrigger className="sm:w-32">
                            <SelectValue placeholder="Tier" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All Tiers</SelectItem>
                            <SelectItem value="free">Free</SelectItem>
                            <SelectItem value="premium">Premium</SelectItem>
                        </SelectContent>
                    </Select>
                    <Select
                        value={filters.status || 'all'}
                        onValueChange={handleStatusFilter}
                    >
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

                {/* Loading skeleton */}
                {isLoading && <MembershipsSkeleton />}

                {/* Empty state */}
                {isEmpty && (
                    <p className="py-8 text-center text-sm text-muted-foreground">
                        No users match your filters.
                    </p>
                )}

                {/* Results */}
                {hasResults && (
                    <>
                        {/* Mobile card layout */}
                        <div className="space-y-3 md:hidden">
                            {data.data.map((user) => (
                                <Card key={user.id}>
                                    <CardContent className="p-4">
                                        <div className="flex items-start justify-between gap-2">
                                            <div className="min-w-0">
                                                <p className="truncate font-medium">
                                                    {user.name}
                                                </p>
                                                <p className="truncate text-sm text-muted-foreground">
                                                    {user.email}
                                                </p>
                                            </div>
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                onClick={() => handleEdit(user)}
                                            >
                                                Edit
                                            </Button>
                                        </div>
                                        <div className="mt-2 flex items-center gap-2">
                                            <Badge
                                                variant={tierBadgeVariant(
                                                    user.membership.tier,
                                                )}
                                            >
                                                {user.membership.tier}
                                            </Badge>
                                            <Badge
                                                variant={statusBadgeVariant(
                                                    user.membership.status,
                                                )}
                                            >
                                                {formatStatus(
                                                    user.membership.status,
                                                )}
                                            </Badge>
                                        </div>
                                    </CardContent>
                                </Card>
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
                                            <TableCell className="font-medium">
                                                {user.name}
                                            </TableCell>
                                            <TableCell>{user.email}</TableCell>
                                            <TableCell>
                                                <Badge
                                                    variant={tierBadgeVariant(
                                                        user.membership.tier,
                                                    )}
                                                >
                                                    {user.membership.tier}
                                                </Badge>
                                            </TableCell>
                                            <TableCell>
                                                <Badge
                                                    variant={statusBadgeVariant(
                                                        user.membership.status,
                                                    )}
                                                >
                                                    {formatStatus(
                                                        user.membership.status,
                                                    )}
                                                </Badge>
                                            </TableCell>
                                            <TableCell className="text-sm text-muted-foreground">
                                                {new Date(
                                                    user.created_at,
                                                ).toLocaleDateString()}
                                            </TableCell>
                                            <TableCell>
                                                <Button
                                                    variant="outline"
                                                    size="sm"
                                                    onClick={() =>
                                                        handleEdit(user)
                                                    }
                                                >
                                                    Edit
                                                </Button>
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </div>

                        {/* Pagination */}
                        {meta && meta.last_page > 1 && (
                            <div className="flex items-center justify-between pt-2">
                                <p className="text-sm text-muted-foreground tabular-nums">
                                    Page {meta.current_page} of {meta.last_page}
                                </p>
                                <div className="flex gap-2">
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        disabled={meta.current_page <= 1}
                                        onClick={() =>
                                            handlePageChange(
                                                meta.current_page - 1,
                                            )
                                        }
                                    >
                                        Previous
                                    </Button>
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        disabled={
                                            meta.current_page >= meta.last_page
                                        }
                                        onClick={() =>
                                            handlePageChange(
                                                meta.current_page + 1,
                                            )
                                        }
                                    >
                                        Next
                                    </Button>
                                </div>
                            </div>
                        )}
                    </>
                )}
            </div>

            {editing && (
                <EditMembershipDialog
                    editing={editing}
                    onClose={() => setEditing(null)}
                    onChange={handleEditChange}
                    onSave={handleSave}
                    saving={saving}
                />
            )}
        </AppLayout>
    );
}
