import assert from 'node:assert/strict';
import test from 'node:test';

const { buildAccountSelectOptions } = await import(
    new URL('./account-select-options.ts', import.meta.url).href
);

test('buildAccountSelectOptions groups included accounts before savings while preserving order within each group', () => {
    const options = buildAccountSelectOptions([
        {
            id: 2,
            name: 'Rainy Day',
            color: '#22c55e',
            include_in_totals: false,
        },
        {
            id: 1,
            name: 'Checking',
            color: '#3b82f6',
            include_in_totals: true,
        },
        {
            id: 3,
            name: 'Cash',
            color: null,
            include_in_totals: true,
        },
    ]);

    assert.deepEqual(options, [
        {
            value: '1',
            label: 'Checking',
            color: '#3b82f6',
            group: 'Included in totals',
        },
        {
            value: '3',
            label: 'Cash',
            color: null,
            group: 'Included in totals',
        },
        {
            value: '2',
            label: 'Rainy Day',
            color: '#22c55e',
            group: 'Savings',
        },
    ]);
});

test('buildAccountSelectOptions excludes the selected source account from destination options', () => {
    const options = buildAccountSelectOptions(
        [
            {
                id: 1,
                name: 'Checking',
                color: '#3b82f6',
                include_in_totals: true,
            },
            {
                id: 2,
                name: 'Rainy Day',
                color: '#22c55e',
                include_in_totals: false,
            },
        ],
        '1',
    );

    assert.deepEqual(options, [
        {
            value: '2',
            label: 'Rainy Day',
            color: '#22c55e',
            group: 'Savings',
        },
    ]);
});
