import { Head } from '@inertiajs/react';
import { CheckCircle2, XCircle } from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';
import { toast } from 'sonner';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
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
    is_admin?: boolean;
    membership: UserMembership;
};

type PaginationMeta = {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
};

type UsersResponse = {
    data: AdminUser[];
    meta: PaginationMeta;
    filters: { search: string | null };
};

function getXsrfToken(): string | undefined {
    const cookie = document.cookie
        .split('; ')
        .find((entry) => entry.startsWith('XSRF-TOKEN='))
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

    if (response.status === 204) {
        return undefined as T;
    }

    return response.json() as Promise<T>;
}

function buildUrl(search: string, page: number): string {
    const params = new URLSearchParams();

    if (search) {
        params.set('search', search);
    }

    if (page > 1) {
        params.set('page', String(page));
    }

    const query = params.toString();

    return `/api/admin/users${query ? `?${query}` : ''}`;
}

function formatDate(dateString: string): string {
    return new Date(dateString).toLocaleDateString();
}

function UserCardSkeleton() {
    return (
        <Card>
            <CardContent className="space-y-3 p-4">
                <div className="flex items-start justify-between gap-2">
                    <div className="min-w-0 space-y-2">
                        <Skeleton className="h-5 w-32" />
                        <Skeleton className="h-4 w-48" />
                    </div>
                    <Skeleton className="h-5 w-5 shrink-0 rounded-full" />
                </div>
                <div className="flex items-center gap-2">
                    <Skeleton className="h-5 w-16" />
                    <Skeleton className="h-4 w-24" />
                </div>
            </CardContent>
        </Card>
    );
}

function UserTableSkeleton() {
    return (
        <Table>
            <TableHeader>
                <TableRow>
                    <TableHead>Name</TableHead>
                    <TableHead>Email</TableHead>
                    <TableHead>Verified</TableHead>
                    <TableHead>Membership</TableHead>
                    <TableHead>Joined</TableHead>
                </TableRow>
            </TableHeader>
            <TableBody>
                {Array.from({ length: 5 }).map((_, i) => (
                    <TableRow key={i}>
                        <TableCell>
                            <Skeleton className="h-4 w-28" />
                        </TableCell>
                        <TableCell>
                            <Skeleton className="h-4 w-40" />
                        </TableCell>
                        <TableCell>
                            <Skeleton className="h-5 w-5 rounded-full" />
                        </TableCell>
                        <TableCell>
                            <Skeleton className="h-5 w-16" />
                        </TableCell>
                        <TableCell>
                            <Skeleton className="h-4 w-24" />
                        </TableCell>
                    </TableRow>
                ))}
            </TableBody>
        </Table>
    );
}

function UserCard({
    user,
    onDelete,
}: {
    user: AdminUser;
    onDelete: (user: AdminUser) => void;
}) {
    return (
        <Card>
            <CardContent className="space-y-3 p-4">
                <div className="flex items-start justify-between gap-2">
                    <div className="min-w-0">
                        <p className="truncate font-medium">{user.name}</p>
                        <p className="truncate text-sm text-muted-foreground">
                            {user.email}
                        </p>
                    </div>
                    {user.email_verified_at ? (
                        <CheckCircle2 className="size-5 shrink-0 text-green-500" />
                    ) : (
                        <XCircle className="size-5 shrink-0 text-muted-foreground" />
                    )}
                </div>
                <div className="flex items-center gap-2">
                    <Badge
                        variant={
                            user.membership.tier === 'premium'
                                ? 'default'
                                : 'secondary'
                        }
                    >
                        {user.membership.tier}
                    </Badge>
                    <span className="text-sm text-muted-foreground">
                        {formatDate(user.created_at)}
                    </span>
                </div>
                {!user.is_admin && (
                    <div className="flex justify-end">
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            className="min-h-10 text-destructive hover:text-destructive"
                            onClick={() => onDelete(user)}
                        >
                            Delete
                        </Button>
                    </div>
                )}
            </CardContent>
        </Card>
    );
}

export default function AdminUsers() {
    const [response, setResponse] = useState<UsersResponse | null>(null);
    const [search, setSearch] = useState('');
    const [page, setPage] = useState(1);
    const [loading, setLoading] = useState(true);
    const [deleteUser, setDeleteUser] = useState<AdminUser | null>(null);
    const [deleteProcessing, setDeleteProcessing] = useState(false);
    const debounceRef = useRef<ReturnType<typeof setTimeout>>(null);

    const fetchUsers = useCallback(
        async (searchTerm: string, pageNumber: number) => {
            setLoading(true);

            try {
                const data = await apiFetch<UsersResponse>(
                    buildUrl(searchTerm, pageNumber),
                );
                setResponse(data);
            } finally {
                setLoading(false);
            }
        },
        [],
    );

    useEffect(() => {
        fetchUsers(search, page);
    }, []); // eslint-disable-line react-hooks/exhaustive-deps

    function handleSearchChange(value: string) {
        setSearch(value);

        if (debounceRef.current) {
            clearTimeout(debounceRef.current);
        }

        debounceRef.current = setTimeout(() => {
            setPage(1);
            fetchUsers(value, 1);
        }, 300);
    }

    function handlePrevPage() {
        if (!response || response.meta.current_page <= 1) {
            return;
        }

        const prevPage = response.meta.current_page - 1;
        setPage(prevPage);
        fetchUsers(search, prevPage);
    }

    function handleNextPage() {
        if (
            !response ||
            response.meta.current_page >= response.meta.last_page
        ) {
            return;
        }

        const nextPage = response.meta.current_page + 1;
        setPage(nextPage);
        fetchUsers(search, nextPage);
    }

    async function handleConfirmDelete() {
        if (!deleteUser) {
            return;
        }

        setDeleteProcessing(true);

        try {
            const xsrfToken = getXsrfToken();

            await apiFetch<void>(`/api/admin/users/${deleteUser.id}`, {
                method: 'DELETE',
                headers: {
                    ...(xsrfToken ? { 'X-XSRF-TOKEN': xsrfToken } : {}),
                },
            });

            let shouldFetchPreviousPage = false;

            setResponse((current) => {
                if (!current) {
                    return current;
                }

                const nextUsers = current.data.filter(
                    (user) => user.id !== deleteUser.id,
                );

                shouldFetchPreviousPage =
                    nextUsers.length === 0 && current.meta.current_page > 1;

                return {
                    ...current,
                    data: nextUsers,
                    meta: {
                        ...current.meta,
                        total: Math.max(current.meta.total - 1, 0),
                    },
                };
            });

            setDeleteUser(null);
            toast.success('User deleted');

            if (shouldFetchPreviousPage && response) {
                const previousPage = response.meta.current_page - 1;
                setPage(previousPage);
                fetchUsers(search, previousPage);
            }
        } catch {
            toast.error('Failed to delete user');
        } finally {
            setDeleteProcessing(false);
        }
    }

    const hasData = response !== null;
    const users = response?.data ?? [];
    const meta = response?.meta;
    const isEmpty = hasData && !loading && users.length === 0;

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Admin', href: '/admin' },
                { title: 'Users', href: '/admin/users' },
            ]}
        >
            <Head title="Admin - Users" />
            <div className="mx-auto max-w-7xl space-y-4 p-4 md:p-6">
                <Input
                    placeholder="Search by name or email..."
                    value={search}
                    onChange={(e) => handleSearchChange(e.target.value)}
                    className="max-w-sm"
                />

                {/* Loading skeleton */}
                {loading && !hasData && (
                    <>
                        {/* Mobile skeleton */}
                        <div className="space-y-3 md:hidden">
                            {Array.from({ length: 5 }).map((_, i) => (
                                <UserCardSkeleton key={i} />
                            ))}
                        </div>

                        {/* Desktop skeleton */}
                        <div className="hidden md:block">
                            <UserTableSkeleton />
                        </div>
                    </>
                )}

                {/* Empty state */}
                {isEmpty && (
                    <p className="py-12 text-center text-sm text-muted-foreground">
                        No users found.
                    </p>
                )}

                {/* User list */}
                {hasData && users.length > 0 && (
                    <>
                        {/* Mobile card layout */}
                        <div className="space-y-3 md:hidden">
                            {users.map((user) => (
                                <UserCard
                                    key={user.id}
                                    user={user}
                                    onDelete={setDeleteUser}
                                />
                            ))}
                        </div>

                        {/* Desktop table layout */}
                        <div className="hidden md:block">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Name</TableHead>
                                        <TableHead>Email</TableHead>
                                        <TableHead>Verified</TableHead>
                                        <TableHead>Membership</TableHead>
                                        <TableHead>Joined</TableHead>
                                        <TableHead className="w-[1%] text-right">
                                            Actions
                                        </TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {users.map((user) => (
                                        <TableRow key={user.id}>
                                            <TableCell className="font-medium">
                                                {user.name}
                                            </TableCell>
                                            <TableCell>{user.email}</TableCell>
                                            <TableCell>
                                                {user.email_verified_at ? (
                                                    <CheckCircle2 className="size-5 text-green-500" />
                                                ) : (
                                                    <XCircle className="size-5 text-muted-foreground" />
                                                )}
                                            </TableCell>
                                            <TableCell>
                                                <Badge
                                                    variant={
                                                        user.membership.tier ===
                                                        'premium'
                                                            ? 'default'
                                                            : 'secondary'
                                                    }
                                                >
                                                    {user.membership.tier}
                                                </Badge>
                                            </TableCell>
                                            <TableCell className="text-sm text-muted-foreground">
                                                {formatDate(user.created_at)}
                                            </TableCell>
                                            <TableCell className="text-right">
                                                {!user.is_admin && (
                                                    <Button
                                                        type="button"
                                                        variant="ghost"
                                                        size="sm"
                                                        className="min-h-10 text-destructive hover:text-destructive"
                                                        onClick={() =>
                                                            setDeleteUser(user)
                                                        }
                                                    >
                                                        Delete
                                                    </Button>
                                                )}
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </div>

                        {/* Pagination */}
                        {meta && meta.last_page > 1 && (
                            <div className="flex items-center justify-between gap-4">
                                <p className="text-sm text-muted-foreground tabular-nums">
                                    Page {meta.current_page} of {meta.last_page}
                                </p>
                                <div className="flex gap-2">
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        onClick={handlePrevPage}
                                        disabled={meta.current_page <= 1}
                                    >
                                        Prev
                                    </Button>
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        onClick={handleNextPage}
                                        disabled={
                                            meta.current_page >= meta.last_page
                                        }
                                    >
                                        Next
                                    </Button>
                                </div>
                            </div>
                        )}
                    </>
                )}

                <Dialog
                    open={deleteUser !== null}
                    onOpenChange={(open) => {
                        if (!open && !deleteProcessing) {
                            setDeleteUser(null);
                        }
                    }}
                >
                    <DialogContent>
                        <DialogHeader>
                            <DialogTitle>Delete user?</DialogTitle>
                            <DialogDescription>
                                {deleteUser
                                    ? `This will permanently delete ${deleteUser.name} (${deleteUser.email}). This action cannot be undone.`
                                    : 'This action cannot be undone.'}
                            </DialogDescription>
                        </DialogHeader>
                        <DialogFooter>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setDeleteUser(null)}
                                disabled={deleteProcessing}
                            >
                                Cancel
                            </Button>
                            <Button
                                type="button"
                                variant="destructive"
                                onClick={handleConfirmDelete}
                                disabled={deleteProcessing}
                            >
                                {deleteProcessing ? 'Deleting...' : 'Delete'}
                            </Button>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>
            </div>
        </AppLayout>
    );
}
