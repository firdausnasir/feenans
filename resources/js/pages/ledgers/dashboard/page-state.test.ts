import assert from 'node:assert/strict';
import test from 'node:test';
import type { shouldShowUpcomingRecurring as ShouldShowUpcomingRecurring } from './page-state';

const { buildDashboardUrl, shouldShowUpcomingRecurring } = await import(
    new URL('./page-state.ts', import.meta.url).href
);

type UpcomingBills = NonNullable<
    Parameters<typeof ShouldShowUpcomingRecurring>[0]['bills']
>;

type AssertDashboardBill<
    T extends {
        id: number;
        name: string;
        next_due_date: string;
        transaction_type: 'expense' | 'income' | 'transfer';
    },
> = T;

type DashboardBill = AssertDashboardBill<UpcomingBills['due'][number]>;

const dueBill: DashboardBill = {
    id: 1,
    ledger_id: 1,
    account_id: 1,
    to_account_id: null,
    category_id: null,
    payee_id: null,
    name: 'Rent',
    transaction_type: 'expense',
    amount: 80,
    recurrence_type: 'monthly',
    recurrence_interval: 1,
    recurrence_day: null,
    next_due_date: '2026-01-01',
    auto_create: false,
    end_type: null,
    end_date: null,
    end_after_occurrences: null,
    occurrences_count: 0,
    is_active: true,
    notify_email: true,
    created_at: '2026-01-01T00:00:00Z',
    updated_at: '2026-01-01T00:00:00Z',
};

test('shouldShowUpcomingRecurring stays hidden while the loader is still processing', () => {
    assert.equal(
        shouldShowUpcomingRecurring({
            hasResolvedInitialLoad: false,
            processing: true,
            bills: null,
        }),
        false,
    );
});

test('shouldShowUpcomingRecurring stays hidden after an empty response resolves', () => {
    assert.equal(
        shouldShowUpcomingRecurring({
            hasResolvedInitialLoad: true,
            processing: false,
            bills: {
                due: [],
                missed: [],
                upcoming: [],
            },
        }),
        false,
    );
});

test('shouldShowUpcomingRecurring becomes visible once at least one bill group has data', () => {
    assert.equal(
        shouldShowUpcomingRecurring({
            hasResolvedInitialLoad: true,
            processing: false,
            bills: {
                due: [dueBill],
                missed: [],
                upcoming: [],
            },
        }),
        true,
    );
});

test('buildDashboardUrl removes stale offset params when returning to the current cycle', () => {
    assert.equal(
        buildDashboardUrl('https://feenans.test/ledgers/1?offset=-2', 0),
        'https://feenans.test/ledgers/1',
    );
});

test('buildDashboardUrl applies the selected cycle offset without disturbing other params', () => {
    assert.equal(
        buildDashboardUrl('https://feenans.test/ledgers/1?tab=overview', -3),
        'https://feenans.test/ledgers/1?tab=overview&offset=-3',
    );
});
