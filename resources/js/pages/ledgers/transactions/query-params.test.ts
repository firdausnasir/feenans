import assert from 'node:assert/strict';
import test from 'node:test';
import { mergeDataIntoQueryString } from '@inertiajs/core';

const { buildQueryParams, deriveSelectionState, EMPTY_FILTERS } = await import(
    new URL('./query-params.ts', import.meta.url).href
);

test('buildQueryParams keeps array keys compatible with Inertia GET serialization', () => {
    const params = buildQueryParams({
        ...EMPTY_FILTERS,
        transaction_types: ['transfer'],
    });

    assert.deepEqual(params, {
        transaction_types: ['transfer'],
    });

    const [url] = mergeDataIntoQueryString(
        'get',
        'https://example.test/ledgers/1/transactions',
        params,
    );

    assert.equal(new URL(url).search, '?transaction_types[]=transfer');
});

test('deriveSelectionState only considers loaded selected ids', () => {
    const selection = deriveSelectionState({
        allVisibleIds: [11, 22, 33],
        selectedIds: [11, 33],
    });

    assert.deepEqual(selection, {
        allSelected: false,
        someSelected: true,
    });
});

test('deriveSelectionState marks all loaded rows as selected when every visible id is selected', () => {
    const selection = deriveSelectionState({
        allVisibleIds: [11, 22, 33],
        selectedIds: [33, 22, 11],
    });

    assert.deepEqual(selection, {
        allSelected: true,
        someSelected: false,
    });
});
