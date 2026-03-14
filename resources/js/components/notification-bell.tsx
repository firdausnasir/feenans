import { router, usePage } from '@inertiajs/react';
import { BarChart3, Bell, Check } from 'lucide-react';
import { useEffect, useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';

type NotificationData = {
    type?: string;
    bill_id?: number;
    bill_name?: string;
    budget_id?: number;
    budget_name?: string;
    due_date?: string;
    amount?: number;
    percentage?: number;
    spent?: number;
    limit?: number;
};

type NotificationItem = {
    id: string;
    data: NotificationData;
    created_at: string;
};

type NotificationMeta = {
    total: number;
};

const DISPLAY_LIMIT = 10;

function isBudgetNotification(type: string | undefined): boolean {
    return type === 'budget_threshold' || type === 'budget_exceeded';
}

function notificationIcon(type: string | undefined) {
    if (isBudgetNotification(type)) {
        return <BarChart3 className="size-4 shrink-0 text-amber-500" />;
    }

    return <Bell className="size-4 shrink-0 text-blue-500" />;
}

function notificationTitle(data: NotificationData): string {
    switch (data.type) {
        case 'bill_due_reminder':
            return 'Bill Due Soon';
        case 'bill_overdue':
            return 'Bill Overdue';
        case 'budget_threshold':
            return 'Budget Warning';
        case 'budget_exceeded':
            return 'Budget Exceeded';
        default:
            return 'Notification';
    }
}

function notificationBody(data: NotificationData): string {
    switch (data.type) {
        case 'bill_due_reminder':
            return `${data.bill_name} is due on ${data.due_date}`;
        case 'bill_overdue':
            return `${data.bill_name} was due on ${data.due_date}`;
        case 'budget_threshold':
            return `${data.budget_name} budget is at ${data.percentage}%`;
        case 'budget_exceeded':
            return `${data.budget_name} budget exceeded (${data.percentage}%)`;
        default:
            return data.bill_name ?? data.budget_name ?? '';
    }
}

function relativeTime(dateString: string): string {
    const now = new Date();
    const date = new Date(dateString);
    const diffMs = now.getTime() - date.getTime();
    const diffMinutes = Math.floor(diffMs / 60000);

    if (diffMinutes < 1) {
        return 'just now';
    }

    if (diffMinutes < 60) {
        return `${diffMinutes}m ago`;
    }

    const diffHours = Math.floor(diffMinutes / 60);

    if (diffHours < 24) {
        return `${diffHours}h ago`;
    }

    const diffDays = Math.floor(diffHours / 24);

    return `${diffDays}d ago`;
}

export function NotificationBell() {
    const page = usePage();
    const unreadCount = Number(page.props.unread_notifications_count ?? 0);
    const [notifications, setNotifications] = useState<NotificationItem[]>([]);
    const [totalCount, setTotalCount] = useState(0);
    const [open, setOpen] = useState(false);

    useEffect(() => {
        if (!open) {
            return;
        }

        fetch('/notifications', {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        })
            .then((response) => response.json())
            .then((payload) => {
                setNotifications(payload.data ?? []);
                setTotalCount((payload.meta as NotificationMeta)?.total ?? 0);
            });
    }, [open]);

    function markAllRead() {
        router.patch('/notifications/read-all', {}, { preserveScroll: true });
        setNotifications([]);
        setTotalCount(0);
    }

    function markOneRead(id: string) {
        fetch(`/notifications/${id}/read`, {
            method: 'PATCH',
            headers: {
                Accept: 'application/json',
                'X-XSRF-TOKEN': decodeURIComponent(
                    document.cookie
                        .split('; ')
                        .find((row) => row.startsWith('XSRF-TOKEN='))
                        ?.split('=')[1] ?? '',
                ),
            },
            credentials: 'same-origin',
        }).then(() => {
            setNotifications((prev) => prev.filter((n) => n.id !== id));
            setTotalCount((prev) => Math.max(0, prev - 1));
            router.reload({ only: ['unread_notifications_count'] });
        });
    }

    const displayed = notifications.slice(0, DISPLAY_LIMIT);
    const hasMore = totalCount > DISPLAY_LIMIT;

    return (
        <Popover open={open} onOpenChange={setOpen}>
            <PopoverTrigger asChild>
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    className="relative"
                >
                    <Bell className="size-4" />
                    {unreadCount > 0 && (
                        <Badge className="absolute -top-1 -right-1 min-w-5 justify-center px-1 py-0 text-[10px]">
                            {unreadCount}
                        </Badge>
                    )}
                </Button>
            </PopoverTrigger>
            <PopoverContent align="end" className="w-80 space-y-3 p-3">
                <div className="flex items-center justify-between gap-2">
                    <p className="text-sm font-medium">Notifications</p>
                    {notifications.length > 0 && (
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            onClick={markAllRead}
                        >
                            Mark all read
                        </Button>
                    )}
                </div>

                {notifications.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        No unread notifications.
                    </p>
                ) : (
                    <div className="space-y-2">
                        {displayed.map((notification) => (
                            <div
                                key={notification.id}
                                className={`flex items-start gap-2 rounded-md border p-2 text-sm ${
                                    isBudgetNotification(notification.data.type)
                                        ? 'border-amber-200 bg-amber-50 dark:border-amber-900 dark:bg-amber-950'
                                        : 'border-blue-200 bg-blue-50 dark:border-blue-900 dark:bg-blue-950'
                                }`}
                            >
                                <div className="mt-0.5">
                                    {notificationIcon(notification.data.type)}
                                </div>
                                <div className="min-w-0 flex-1">
                                    <p className="font-medium leading-tight">
                                        {notificationTitle(notification.data)}
                                    </p>
                                    <p className="text-muted-foreground truncate text-xs">
                                        {notificationBody(notification.data)}
                                    </p>
                                    <p className="text-muted-foreground mt-0.5 text-[10px]">
                                        {relativeTime(notification.created_at)}
                                    </p>
                                </div>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    className="size-6 shrink-0"
                                    onClick={() =>
                                        markOneRead(notification.id)
                                    }
                                    title="Mark as read"
                                >
                                    <Check className="size-3" />
                                </Button>
                            </div>
                        ))}
                    </div>
                )}

                {hasMore && (
                    <p className="text-muted-foreground text-center text-xs">
                        View all ({totalCount} total)
                    </p>
                )}
            </PopoverContent>
        </Popover>
    );
}
