import assert from 'node:assert/strict';
import test from 'node:test';

const { resolveDeferredArray } = await import(
    new URL('./deferred-data.ts', import.meta.url).href
);

test('resolveDeferredArray returns an empty list before deferred props load', () => {
    assert.deepEqual(resolveDeferredArray(undefined), []);
});

test('resolveDeferredArray preserves loaded groups', () => {
    const groups = [
        {
            group: 'included',
            label: 'Included',
            accounts: [],
            total_balance: '0.00',
        },
    ];

    assert.deepEqual(resolveDeferredArray(groups), groups);
});
