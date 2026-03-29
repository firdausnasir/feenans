import assert from 'node:assert/strict';
import test from 'node:test';
import type { Transaction } from '../../../types/ledger';

const { resolveMobileTransactionTitle } = await import(
    new URL('./mobile-transaction-row-data.ts', import.meta.url).href,
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

test('resolveMobileTransactionTitle uses counterpart account for transfers', () => {
    const transaction = makeTransaction({
        transaction_type: 'transfer',
        amount: '-50.00',
        transfer_pair: {
            ...makeTransaction({
                id: 2,
                transaction_type: 'transfer',
                amount: '50.00',
            }),
            account: {
                id: 2,
                ledger_id: 1,
                account_type_id: 1,
                name: 'Savings',
                color: null,
                initial_balance: '0.00',
                statement_day: null,
                payment_due_day: null,
                include_in_totals: true,
                is_hidden: false,
                position: 1,
                current_balance: '0.00',
            },
        },
    });

    assert.equal(resolveMobileTransactionTitle(transaction), 'To Savings');
});

test('resolveMobileTransactionTitle falls back to a neutral transfer label', () => {
    const transaction = makeTransaction({
        transaction_type: 'transfer',
        amount: '-50.00',
        transfer_pair_id: 'pair-1',
        account: {
            id: 1,
            ledger_id: 1,
            account_type_id: 1,
            name: 'Checking',
            color: null,
            initial_balance: '0.00',
            statement_day: null,
            payment_due_day: null,
            include_in_totals: true,
            is_hidden: false,
            position: 1,
            current_balance: '0.00',
        },
    });

    assert.equal(resolveMobileTransactionTitle(transaction), 'Transfer');
});
