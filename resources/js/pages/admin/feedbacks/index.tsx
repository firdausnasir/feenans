import { Head } from '@inertiajs/react';
import { Bug, Lightbulb, MessageSquare } from 'lucide-react';
import { useCallback, useEffect, useState } from 'react';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
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
import type { BreadcrumbItem } from '@/types';

type FeedbackUser = {
    id: number;
    name: string;
    email: string;
};

type FeedbackItem = {
    id: number;
    type: 'general' | 'bug' | 'feature';
    message: string;
    user: FeedbackUser;
    created_at: string;
};

type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

type FeedbacksResponse = {
    data: FeedbackItem[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    links: PaginationLink[];
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Admin', href: '/admin' },
    { title: 'Feedbacks', href: '/admin/feedbacks' },
];

const typeConfig = {
    general: {
        label: 'General',
        icon: MessageSquare,
        variant: 'secondary' as const,
    },
    bug: { label: 'Bug', icon: Bug, variant: 'destructive' as const },
    feature: {
        label: 'Feature',
        icon: Lightbulb,
        variant: 'default' as const,
    },
};

async function apiFetch<T>(url: string): Promise<T> {
    const response = await fetch(url, {
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        },
        credentials: 'same-origin',
    });

    if (!response.ok) {
        throw new Error(`API request failed: ${response.status}`);
    }

    return response.json() as Promise<T>;
}

function formatDate(dateString: string): string {
    return new Date(dateString).toLocaleDateString(undefined, {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

function FeedbackCardSkeleton() {
    return (
        <Card>
            <CardContent className="space-y-3 p-4">
                <div className="flex items-start justify-between gap-2">
                    <div className="min-w-0 space-y-2">
                        <Skeleton className="h-5 w-32" />
                        <Skeleton className="h-4 w-48" />
                    </div>
                    <Skeleton className="h-5 w-16 rounded-full" />
                </div>
                <Skeleton className="h-12 w-full" />
                <Skeleton className="h-4 w-24" />
            </CardContent>
        </Card>
    );
}

function FeedbackTableSkeleton() {
    return (
        <Table>
            <TableHeader>
                <TableRow>
                    <TableHead>Type</TableHead>
                    <TableHead>User</TableHead>
                    <TableHead>Message</TableHead>
                    <TableHead>Date</TableHead>
                </TableRow>
            </TableHeader>
            <TableBody>
                {Array.from({ length: 5 }).map((_, i) => (
                    <TableRow key={i}>
                        <TableCell>
                            <Skeleton className="h-5 w-16" />
                        </TableCell>
                        <TableCell>
                            <Skeleton className="h-4 w-28" />
                        </TableCell>
                        <TableCell>
                            <Skeleton className="h-4 w-64" />
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

function TypeBadge({ type }: { type: FeedbackItem['type'] }) {
    const config = typeConfig[type];
    const Icon = config.icon;

    return (
        <Badge variant={config.variant} className="gap-1">
            <Icon className="size-3" />
            {config.label}
        </Badge>
    );
}

function FeedbackCard({ feedback }: { feedback: FeedbackItem }) {
    return (
        <Card>
            <CardContent className="space-y-3 p-4">
                <div className="flex items-start justify-between gap-2">
                    <div className="min-w-0">
                        <p className="truncate font-medium">
                            {feedback.user.name}
                        </p>
                        <p className="truncate text-sm text-muted-foreground">
                            {feedback.user.email}
                        </p>
                    </div>
                    <TypeBadge type={feedback.type} />
                </div>
                <p className="whitespace-pre-wrap text-sm">
                    {feedback.message}
                </p>
                <p className="text-xs text-muted-foreground">
                    {formatDate(feedback.created_at)}
                </p>
            </CardContent>
        </Card>
    );
}

export default function AdminFeedbacks() {
    const [response, setResponse] = useState<FeedbacksResponse | null>(null);
    const [page, setPage] = useState(1);
    const [loading, setLoading] = useState(true);

    const fetchFeedbacks = useCallback(async (pageNumber: number) => {
        setLoading(true);

        try {
            const params = new URLSearchParams();

            if (pageNumber > 1) {
params.set('page', String(pageNumber));
}

            const query = params.toString();
            const url = `/api/admin/feedbacks${query ? `?${query}` : ''}`;
            const data = await apiFetch<FeedbacksResponse>(url);
            setResponse(data);
        } finally {
            setLoading(false);
        }
    }, []);

    useEffect(() => {
        fetchFeedbacks(page);
    }, []); // eslint-disable-line react-hooks/exhaustive-deps

    function handlePrevPage() {
        if (!response || response.current_page <= 1) {
return;
}

        const prevPage = response.current_page - 1;
        setPage(prevPage);
        fetchFeedbacks(prevPage);
    }

    function handleNextPage() {
        if (!response || response.current_page >= response.last_page) {
return;
}

        const nextPage = response.current_page + 1;
        setPage(nextPage);
        fetchFeedbacks(nextPage);
    }

    const hasData = response !== null;
    const feedbacks = response?.data ?? [];
    const isEmpty = hasData && !loading && feedbacks.length === 0;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Admin - Feedbacks" />
            <div className="mx-auto max-w-7xl space-y-4 p-4 md:p-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h2 className="text-lg font-semibold">
                            User Feedback
                        </h2>
                        {response && (
                            <p className="text-sm text-muted-foreground">
                                {response.total} total feedback
                                {response.total !== 1 ? 's' : ''}
                            </p>
                        )}
                    </div>
                </div>

                {loading && !hasData && (
                    <>
                        <div className="space-y-3 md:hidden">
                            {Array.from({ length: 5 }).map((_, i) => (
                                <FeedbackCardSkeleton key={i} />
                            ))}
                        </div>
                        <div className="hidden md:block">
                            <FeedbackTableSkeleton />
                        </div>
                    </>
                )}

                {isEmpty && (
                    <p className="py-12 text-center text-sm text-muted-foreground">
                        No feedback received yet.
                    </p>
                )}

                {hasData && feedbacks.length > 0 && (
                    <>
                        <div className="space-y-3 md:hidden">
                            {feedbacks.map((feedback) => (
                                <FeedbackCard
                                    key={feedback.id}
                                    feedback={feedback}
                                />
                            ))}
                        </div>

                        <div className="hidden md:block">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead className="w-28">
                                            Type
                                        </TableHead>
                                        <TableHead className="w-48">
                                            User
                                        </TableHead>
                                        <TableHead>Message</TableHead>
                                        <TableHead className="w-44">
                                            Date
                                        </TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {feedbacks.map((feedback) => (
                                        <TableRow key={feedback.id}>
                                            <TableCell>
                                                <TypeBadge
                                                    type={feedback.type}
                                                />
                                            </TableCell>
                                            <TableCell>
                                                <div>
                                                    <p className="font-medium">
                                                        {feedback.user.name}
                                                    </p>
                                                    <p className="text-sm text-muted-foreground">
                                                        {feedback.user.email}
                                                    </p>
                                                </div>
                                            </TableCell>
                                            <TableCell className="max-w-md">
                                                <p className="line-clamp-2 whitespace-pre-wrap text-sm">
                                                    {feedback.message}
                                                </p>
                                            </TableCell>
                                            <TableCell className="text-sm text-muted-foreground">
                                                {formatDate(
                                                    feedback.created_at,
                                                )}
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </div>

                        {response && response.last_page > 1 && (
                            <div className="flex items-center justify-between gap-4">
                                <p className="text-sm text-muted-foreground tabular-nums">
                                    Page {response.current_page} of{' '}
                                    {response.last_page}
                                </p>
                                <div className="flex gap-2">
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        onClick={handlePrevPage}
                                        disabled={
                                            response.current_page <= 1
                                        }
                                    >
                                        Prev
                                    </Button>
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        onClick={handleNextPage}
                                        disabled={
                                            response.current_page >=
                                            response.last_page
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
        </AppLayout>
    );
}
