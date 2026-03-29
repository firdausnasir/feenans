import assert from 'node:assert/strict';
import test from 'node:test';
import { mergeDataIntoQueryString } from '@inertiajs/core';

const { buildQueryParams, deriveSelectionState, EMPTY_FILTERS } = await import(
    new URL('./query-params.ts', import.meta.url).href,
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

test('deriveSelectionState respects excluded ids when all pages are selected', () => {
    const selection = deriveSelectionState({
        allVisibleIds: [11, 22, 33],
        selectedIds: [],
        excludedIds: [22],
        allAcrossPages: true,
    });

    assert.deepEqual(selection, {
        allSelected: false,
        someSelected: true,
    });
});
