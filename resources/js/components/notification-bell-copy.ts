export type NotificationData = {
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
    upcoming_count?: number;
    due_today_count?: number;
    overdue_count?: number;
    total_bills?: number;
};

function countLabel(count: number | undefined, label: string): string | null {
    if (!count) {
        return null;
    }

    return `${count} ${label}`;
}

function billSummaryBody(data: NotificationData): string {
    const parts = [
        countLabel(data.overdue_count, 'overdue'),
        countLabel(data.due_today_count, 'due today'),
        countLabel(data.upcoming_count, 'upcoming'),
    ].filter((part): part is string => part !== null);

    if (parts.length > 0) {
        return parts.join(', ');
    }

    if (data.total_bills) {
        return `${data.total_bills} bill${data.total_bills === 1 ? '' : 's'} require${data.total_bills === 1 ? 's' : ''} attention`;
    }

    return 'Bills require attention';
}

export function getNotificationCopy(data: NotificationData): { title: string; body: string } {
    switch (data.type) {
        case 'bill_due_reminder':
            return {
                title: 'Bill Due Soon',
                body: `${data.bill_name} is due on ${data.due_date}`,
            };
        case 'bill_summary_reminder':
            return {
                title: 'Bills Requiring Attention',
                body: billSummaryBody(data),
            };
        case 'bill_overdue':
            return {
                title: 'Bill Overdue',
                body: `${data.bill_name} was due on ${data.due_date}`,
            };
        case 'budget_threshold':
            return {
                title: 'Budget Warning',
                body: `${data.budget_name} budget is at ${data.percentage}%`,
            };
        case 'budget_exceeded':
            return {
                title: 'Budget Exceeded',
                body: `${data.budget_name} budget exceeded (${data.percentage}%)`,
            };
        default:
            return {
                title: 'Notification',
                body: data.bill_name ?? data.budget_name ?? '',
            };
    }
}
