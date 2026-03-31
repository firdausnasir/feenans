import assert from 'node:assert/strict';
import test from 'node:test';

const { getNotificationCopy } = await import(
    new URL('./notification-bell-copy.ts', import.meta.url).href
);

test('bill summary reminders return a readable title and body', () => {
    assert.deepEqual(
        getNotificationCopy({
            type: 'bill_summary_reminder',
            overdue_count: 1,
            due_today_count: 1,
            upcoming_count: 2,
            total_bills: 4,
        }),
        {
            title: 'Bills Requiring Attention',
            body: '1 overdue, 1 due today, 2 upcoming',
        },
    );
});
