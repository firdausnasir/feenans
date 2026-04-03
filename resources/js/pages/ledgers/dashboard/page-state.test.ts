import assert from 'node:assert/strict';
import test from 'node:test';

const { buildDashboardUrl, shouldShowUpcomingRecurring } = await import(
    new URL('./page-state.ts', import.meta.url).href,
);

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
                due: [{ id: 1 }],
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
