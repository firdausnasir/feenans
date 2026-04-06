import assert from 'node:assert/strict';
import test from 'node:test';

const {
    ALL_FILTER,
    getActivityFilterSelectState,
    shouldResetActivityState,
} = await import(new URL('./page-state.ts', import.meta.url).href);

test('getActivityFilterSelectState maps null filters to the all option', () => {
    assert.deepEqual(
        getActivityFilterSelectState({
            subject_type: null,
            action: null,
            page: 1,
        }),
        {
            filterType: ALL_FILTER,
            filterAction: ALL_FILTER,
        },
    );
});

test('shouldResetActivityState returns true when the ledger changes', () => {
    assert.equal(
        shouldResetActivityState(
            1,
            2,
            { subject_type: null, action: null, page: 1 },
            { subject_type: null, action: null, page: 1 },
        ),
        true,
    );
});

test('shouldResetActivityState returns true when incoming filters change', () => {
    assert.equal(
        shouldResetActivityState(
            1,
            1,
            { subject_type: null, action: null, page: 1 },
            { subject_type: 'Budget', action: 'updated', page: 2 },
        ),
        true,
    );
});

test('shouldResetActivityState stays false when ledger and filters are unchanged', () => {
    assert.equal(
        shouldResetActivityState(
            1,
            1,
            { subject_type: 'Budget', action: 'updated', page: 2 },
            { subject_type: 'Budget', action: 'updated', page: 2 },
        ),
        false,
    );
});
