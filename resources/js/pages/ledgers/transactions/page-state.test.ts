import assert from 'node:assert/strict';
import test from 'node:test';

const { EMPTY_FILTERS } = await import(
    new URL('./query-params.ts', import.meta.url).href
);
const {
    buildTransactionsUrl,
    canAppendTransactionsPage,
    mergeTransactionPageData,
    shouldContinueTransactionsReload,
    shouldApplyTransactionsResponse,
    shouldResetTransactionsState,
} = await import(new URL('./page-state.ts', import.meta.url).href);

test('buildTransactionsUrl keeps array query keys compatible with transaction filters', () => {
    const url = buildTransactionsUrl(
        'https://feenans.test/ledgers/1/transactions',
        {
            ...EMPTY_FILTERS,
            account_ids: ['12', '34'],
            transaction_types: ['transfer'],
        },
        2,
    );

    const parsedUrl = new URL(url);

    assert.deepEqual(parsedUrl.searchParams.getAll('account_ids[]'), [
        '12',
        '34',
    ]);
    assert.deepEqual(parsedUrl.searchParams.getAll('transaction_types[]'), [
        'transfer',
    ]);
    assert.equal(parsedUrl.searchParams.get('page'), '2');
});

test('buildTransactionsUrl clears stale filters and page when filters reset', () => {
    const url = buildTransactionsUrl(
        'https://feenans.test/ledgers/1/transactions?search=coffee&transaction_types[]=transfer&page=3',
        EMPTY_FILTERS,
    );

    assert.equal(url, 'https://feenans.test/ledgers/1/transactions');
});

test('shouldResetTransactionsState returns true when applied filters change', () => {
    assert.equal(
        shouldResetTransactionsState(1, 1, EMPTY_FILTERS, {
            ...EMPTY_FILTERS,
            search: 'coffee',
        }),
        true,
    );
});

test('shouldResetTransactionsState stays false when ledger and filters are unchanged', () => {
    assert.equal(
        shouldResetTransactionsState(
            1,
            1,
            {
                ...EMPTY_FILTERS,
                category_ids: ['9'],
                uncategorized: '1',
            },
            {
                ...EMPTY_FILTERS,
                category_ids: ['9'],
                uncategorized: '1',
            },
        ),
        false,
    );
});

test('shouldApplyTransactionsResponse ignores stale or cancelled requests', () => {
    assert.equal(
        shouldApplyTransactionsResponse({
            cancelled: false,
            latestRequestId: 3,
            requestId: 2,
        }),
        false,
    );

    assert.equal(
        shouldApplyTransactionsResponse({
            cancelled: true,
            latestRequestId: 3,
            requestId: 3,
        }),
        false,
    );

    assert.equal(
        shouldApplyTransactionsResponse({
            cancelled: false,
            latestRequestId: 3,
            requestId: 3,
        }),
        true,
    );
});

test('canAppendTransactionsPage blocks duplicate appends while a page load is active', () => {
    assert.equal(
        canAppendTransactionsPage({
            nextPageUrl: 'https://feenans.test/api/v1/ledgers/1/transactions?page=2',
            processing: false,
            isAppending: false,
        }),
        true,
    );

    assert.equal(
        canAppendTransactionsPage({
            nextPageUrl: 'https://feenans.test/api/v1/ledgers/1/transactions?page=2',
            processing: true,
            isAppending: false,
        }),
        false,
    );

    assert.equal(
        canAppendTransactionsPage({
            nextPageUrl: 'https://feenans.test/api/v1/ledgers/1/transactions?page=2',
            processing: false,
            isAppending: true,
        }),
        false,
    );
});

test('mergeTransactionPageData deduplicates repeated appended rows by id', () => {
    const merged = mergeTransactionPageData(
        [
            { id: 11, label: 'first page' },
            { id: 22, label: 'still first page' },
        ],
        [
            { id: 22, label: 'duplicate second page row' },
            { id: 33, label: 'new second page row' },
        ],
    );

    assert.deepEqual(merged, [
        { id: 11, label: 'first page' },
        { id: 22, label: 'still first page' },
        { id: 33, label: 'new second page row' },
    ]);
});

test('shouldContinueTransactionsReload stops a stale reload batch after a newer operation starts', () => {
    assert.equal(
        shouldContinueTransactionsReload({
            operationId: 4,
            latestOperationId: 5,
            wasSuccessful: true,
        }),
        false,
    );

    assert.equal(
        shouldContinueTransactionsReload({
            operationId: 5,
            latestOperationId: 5,
            wasSuccessful: false,
        }),
        false,
    );

    assert.equal(
        shouldContinueTransactionsReload({
            operationId: 5,
            latestOperationId: 5,
            wasSuccessful: true,
        }),
        true,
    );
});
