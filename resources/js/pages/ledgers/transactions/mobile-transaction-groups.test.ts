import assert from 'node:assert/strict';
import test from 'node:test';
import type { Transaction } from '../../../types/ledger';

const { groupTransactionsForMobile } = await import(
    new URL('./mobile-transaction-groups.ts', import.meta.url).href,
);

function makeTransaction(overrides: Partial<Transaction>): Transaction {
    return {
        id: 1,
        ledger_id: 1,
        account_id: 1,
        category_id: null,
        payee_id: null,
        transaction_type: 'expense',
        amount: '-10.00',
        description: null,
        notes: null,
        transaction_date: '2026-03-29',
        transfer_pair_id: null,
        bill_id: null,
        ...overrides,
    };
}

test('groups transactions by date while preserving incoming order', () => {
    const first = makeTransaction({ id: 11, transaction_date: '2026-03-03' });
    const second = makeTransaction({ id: 22, transaction_date: '2026-03-03' });
    const third = makeTransaction({ id: 33, transaction_date: '2026-03-02' });

    const groups = groupTransactionsForMobile([first, second, third]);

    assert.deepEqual(groups, [
        {
            date: '2026-03-03',
            items: [
                { kind: 'transaction', transaction: first },
                { kind: 'transaction', transaction: second },
            ],
        },
        {
            date: '2026-03-02',
            items: [{ kind: 'transaction', transaction: third }],
        },
    ]);
});

test('groups transfer pairs into a wrapper using transfer_pair_id', () => {
    const before = makeTransaction({ id: 1, transaction_date: '2026-03-03' });
    const firstTransfer = makeTransaction({
        id: 2,
        transaction_date: '2026-03-03',
        transaction_type: 'transfer',
        transfer_pair_id: 'pair-1',
    });
    const secondTransfer = makeTransaction({
        id: 3,
        transaction_date: '2026-03-03',
        transaction_type: 'transfer',
        transfer_pair_id: 'pair-1',
    });

    const groups = groupTransactionsForMobile([before, firstTransfer, secondTransfer]);

    assert.deepEqual(groups, [
        {
            date: '2026-03-03',
            items: [
                { kind: 'transaction', transaction: before },
                {
                    kind: 'transfer_pair',
                    pairId: 'pair-1',
                    transactions: [firstTransfer, secondTransfer],
                },
            ],
        },
    ]);
});

test('keeps non-adjacent transfer pair rows in chronological order', () => {
    const firstTransfer = makeTransaction({
        id: 2,
        transaction_date: '2026-03-03',
        transaction_type: 'transfer',
        transfer_pair_id: 'pair-1',
    });
    const between = makeTransaction({ id: 3, transaction_date: '2026-03-03' });
    const secondTransfer = makeTransaction({
        id: 4,
        transaction_date: '2026-03-03',
        transaction_type: 'transfer',
        transfer_pair_id: 'pair-1',
    });

    const groups = groupTransactionsForMobile([
        firstTransfer,
        between,
        secondTransfer,
    ]);

    assert.deepEqual(groups, [
        {
            date: '2026-03-03',
            items: [
                {
                    kind: 'transfer_pair',
                    pairId: 'pair-1',
                    transactions: [firstTransfer],
                },
                { kind: 'transaction', transaction: between },
                {
                    kind: 'transfer_pair',
                    pairId: 'pair-1',
                    transactions: [secondTransfer],
                },
            ],
        },
    ]);
});

test('keeps orphaned transfer rows as singleton wrappers', () => {
    const orphanedTransfer = makeTransaction({
        id: 9,
        transaction_date: '2026-03-03',
        transaction_type: 'transfer',
        transfer_pair_id: 'pair-orphaned',
    });
    const regularTransaction = makeTransaction({
        id: 10,
        transaction_date: '2026-03-03',
    });

    const groups = groupTransactionsForMobile([
        orphanedTransfer,
        regularTransaction,
    ]);

    assert.deepEqual(groups, [
        {
            date: '2026-03-03',
            items: [
                {
                    kind: 'transfer_pair',
                    pairId: 'pair-orphaned',
                    transactions: [orphanedTransfer],
                },
                { kind: 'transaction', transaction: regularTransaction },
            ],
        },
    ]);
});

test('keeps transfer rows without transfer_pair_id as standalone items', () => {
    const transferWithoutPair = makeTransaction({
        id: 14,
        transaction_date: '2026-03-03',
        transaction_type: 'transfer',
        transfer_pair_id: null,
    });

    const groups = groupTransactionsForMobile([transferWithoutPair]);

    assert.deepEqual(groups, [
        {
            date: '2026-03-03',
            items: [
                {
                    kind: 'transaction',
                    transaction: transferWithoutPair,
                },
            ],
        },
    ]);
});

test('keeps non-transfer rows with transfer_pair_id as standalone items', () => {
    const taggedExpense = makeTransaction({
        id: 15,
        transaction_date: '2026-03-03',
        transaction_type: 'expense',
        transfer_pair_id: 'pair-not-transfer',
    });
    const actualTransfer = makeTransaction({
        id: 16,
        transaction_date: '2026-03-03',
        transaction_type: 'transfer',
        transfer_pair_id: 'pair-not-transfer',
    });

    const groups = groupTransactionsForMobile([taggedExpense, actualTransfer]);

    assert.deepEqual(groups, [
        {
            date: '2026-03-03',
            items: [
                { kind: 'transaction', transaction: taggedExpense },
                {
                    kind: 'transfer_pair',
                    pairId: 'pair-not-transfer',
                    transactions: [actualTransfer],
                },
            ],
        },
    ]);
});

test('scopes transfer wrappers within each date group', () => {
    const firstDateTransfer = makeTransaction({
        id: 21,
        transaction_date: '2026-03-03',
        transaction_type: 'transfer',
        transfer_pair_id: 'shared-pair',
    });
    const secondDateTransfer = makeTransaction({
        id: 22,
        transaction_date: '2026-03-02',
        transaction_type: 'transfer',
        transfer_pair_id: 'shared-pair',
    });

    const groups = groupTransactionsForMobile([
        firstDateTransfer,
        secondDateTransfer,
    ]);

    assert.deepEqual(groups, [
        {
            date: '2026-03-03',
            items: [
                {
                    kind: 'transfer_pair',
                    pairId: 'shared-pair',
                    transactions: [firstDateTransfer],
                },
            ],
        },
        {
            date: '2026-03-02',
            items: [
                {
                    kind: 'transfer_pair',
                    pairId: 'shared-pair',
                    transactions: [secondDateTransfer],
                },
            ],
        },
    ]);
});
