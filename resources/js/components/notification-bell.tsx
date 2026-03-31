import { router, usePage } from '@inertiajs/react';
import { BarChart3, Bell, Check } from 'lucide-react';
import { useEffect, useState } from 'react';
import {
    markAllRead as markAllReadRoute,
    markRead as markReadRoute,
} from '@/actions/App/Http/Controllers/NotificationController';
import {
    getNotificationCopy
    
} from '@/components/notification-bell-copy';
import type {NotificationData} from '@/components/notification-bell-copy';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';

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
    const page = usePage<{
        unread_notifications_count: number;
        notifications?: {
            data: NotificationItem[];
            meta: NotificationMeta;
        } | null;
    }>();
    const unreadCount = Number(page.props.unread_notifications_count ?? 0);
    const [open, setOpen] = useState(false);
    const notifications = page.props.notifications?.data ?? [];
    const totalCount = page.props.notifications?.meta?.total ?? 0;

    useEffect(() => {
        if (!open) {
            return;
        }

        router.reload({
            only: ['notifications', 'unread_notifications_count'],
        });
    }, [open]);

    function markAllRead() {
        router.patch(markAllReadRoute.url(), {}, {
            only: ['notifications', 'unread_notifications_count'],
            preserveState: true,
            preserveScroll: true,
        });
    }

    function markOneRead(id: string) {
        router.patch(markReadRoute.url(id), {}, {
            only: ['notifications', 'unread_notifications_count'],
            preserveState: true,
            preserveScroll: true,
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
                                {(() => {
                                    const copy = getNotificationCopy(notification.data);

                                    return (
                                        <>
                                            <div className="mt-0.5">
                                                {notificationIcon(notification.data.type)}
                                            </div>
                                            <div className="min-w-0 flex-1">
                                                <p className="leading-tight font-medium">
                                                    {copy.title}
                                                </p>
                                                <p className="truncate text-xs text-muted-foreground">
                                                    {copy.body}
                                                </p>
                                                <p className="mt-0.5 text-[10px] text-muted-foreground">
                                                    {relativeTime(notification.created_at)}
                                                </p>
                                            </div>
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="icon"
                                                className="size-6 shrink-0"
                                                onClick={() => markOneRead(notification.id)}
                                                title="Mark as read"
                                            >
                                                <Check className="size-3" />
                                            </Button>
                                        </>
                                    );
                                })()}
                            </div>
                        ))}
                    </div>
                )}

                {hasMore && (
                    <p className="text-center text-xs text-muted-foreground">
                        View all ({totalCount} total)
                    </p>
                )}
            </PopoverContent>
        </Popover>
    );
}
