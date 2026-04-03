import assert from 'node:assert/strict';
import test from 'node:test';

const { buildReportsUrl, getNextReportsFilters } = await import(
    new URL('./page-state.ts', import.meta.url).href,
);

test('getNextReportsFilters clears comparison filters when compare is disabled', () => {
    const next = getNextReportsFilters(
        {
            date_from: '2026-03-01',
            date_to: '2026-03-31',
            preset: 'this_month',
            account_id: null,
            compare_start: '2026-02-01',
            compare_end: '2026-02-29',
        },
        {
            compare_start: null,
            compare_end: null,
        },
    );

    assert.deepEqual(next, {
        date_from: '2026-03-01',
        date_to: '2026-03-31',
        preset: 'this_month',
        account_id: null,
        compare_start: null,
        compare_end: null,
    });
});

test('buildReportsUrl removes stale comparison params when compare is cleared', () => {
    const url = buildReportsUrl(
        'https://feenans.test/ledgers/1/reports?compare_start=2026-02-01&compare_end=2026-02-29',
        {
        date_from: '2026-03-01',
        date_to: '2026-03-31',
        preset: 'this_month',
        account_id: '5',
        compare_start: null,
        compare_end: null,
    },
    );

    assert.equal(
        url,
        'https://feenans.test/ledgers/1/reports?date_from=2026-03-01&date_to=2026-03-31&account_id=5',
    );
});
