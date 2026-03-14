import { router, usePage } from '@inertiajs/react';
import { Bell } from 'lucide-react';
import { useEffect, useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';

type NotificationItem = {
    id: string;
    data: {
        bill_id?: number;
        bill_name?: string;
        type?: string;
    };
};

export function NotificationBell() {
    const page = usePage();
    const unreadCount = Number(page.props.unread_notifications_count ?? 0);
    const [notifications, setNotifications] = useState<NotificationItem[]>([]);
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
            .then((payload) => setNotifications(payload.data ?? []));
    }, [open]);

    function markAllRead() {
        router.patch('/notifications/read-all', {}, { preserveScroll: true });
        setNotifications([]);
    }

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
                        {notifications.slice(0, 10).map((notification) => (
                            <div
                                key={notification.id}
                                className="rounded-md border p-2 text-sm"
                            >
                                {notification.data.bill_name ?? 'Notification'}
                            </div>
                        ))}
                    </div>
                )}
            </PopoverContent>
        </Popover>
    );
}
