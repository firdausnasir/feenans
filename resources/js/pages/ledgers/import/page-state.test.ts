import assert from 'node:assert/strict';
import test from 'node:test';
import type { shouldBlockImportStepTwo as ShouldBlockImportStepTwo } from './page-state';

const { createInitialImportPageState, shouldBlockImportStepTwo } = await import(
    new URL('./page-state.ts', import.meta.url).href
);

type ImportStepTwoArgs = Parameters<typeof ShouldBlockImportStepTwo>[0];

test('createInitialImportPageState resets to the upload step when there is no parse result', () => {
    assert.deepEqual(createInitialImportPageState(null), {
        step: 1,
        parseResult: null,
        mapping: {
            date: '__not_mapped__',
            amount: '__not_mapped__',
            description: '__not_mapped__',
            category: '__not_mapped__',
            payee: '__not_mapped__',
            type: '__not_mapped__',
        },
        detectedBank: null,
    });
});

test('createInitialImportPageState restores mapping defaults from a parse result', () => {
    assert.deepEqual(
        createInitialImportPageState({
            headers: ['Transaction Date', 'Debit', 'Description'],
            preview_rows: [['2026-01-01', '25.00', 'Coffee']],
            total_rows: 1,
            file_path: 'imports/temp/example.csv',
            detected_bank: 'Maybank',
            suggested_mapping: {
                date: 'Transaction Date',
                amount: 'Debit',
                description: 'Description',
            },
        }),
        {
            step: 2,
            parseResult: {
                headers: ['Transaction Date', 'Debit', 'Description'],
                preview_rows: [['2026-01-01', '25.00', 'Coffee']],
                total_rows: 1,
                file_path: 'imports/temp/example.csv',
                detected_bank: 'Maybank',
                suggested_mapping: {
                    date: 'Transaction Date',
                    amount: 'Debit',
                    description: 'Description',
                },
            },
            mapping: {
                date: 'Transaction Date',
                amount: 'Debit',
                description: 'Description',
                category: '__not_mapped__',
                payee: '__not_mapped__',
                type: '__not_mapped__',
            },
            detectedBank: 'Maybank',
        },
    );
});

test('shouldBlockImportStepTwo only blocks on accounts loader failures without any accounts', () => {
    const args: ImportStepTwoArgs = {
        accountsError: 'Failed to load accounts.',
        accountsCount: 0,
    };

    assert.equal(shouldBlockImportStepTwo(args), true);
});

test('shouldBlockImportStepTwo keeps manual mapping available when saved mappings fail', () => {
    const args: ImportStepTwoArgs = {
        accountsError: null,
        accountsCount: 1,
    };

    assert.deepEqual(args, {
        accountsError: null,
        accountsCount: 1,
    });

    assert.equal(shouldBlockImportStepTwo(args), false);
});
