import assert from 'node:assert/strict';
import test from 'node:test';

const {
    resolveTransactionModalLoadError,
    shouldLoadTransactionModalData,
    shouldShowTransactionModalLoading,
} = await import(new URL('./add-transaction-modal-state.ts', import.meta.url).href);

test('idle modal data state triggers a fetch and shows loading until data arrives', () => {
    assert.equal(
        shouldLoadTransactionModalData({
            open: true,
            hasData: false,
            requestState: 'idle',
        }),
        true,
    );
    assert.equal(
        shouldShowTransactionModalLoading({
            open: true,
            hasData: false,
            requestState: 'idle',
        }),
        true,
    );
    assert.equal(
        resolveTransactionModalLoadError({
            open: true,
            hasData: false,
            requestState: 'idle',
        }),
        null,
    );
});

test('error modal data state stops the spinner and exposes a retryable error', () => {
    assert.equal(
        shouldLoadTransactionModalData({
            open: true,
            hasData: false,
            requestState: 'error',
        }),
        false,
    );
    assert.equal(
        shouldShowTransactionModalLoading({
            open: true,
            hasData: false,
            requestState: 'error',
        }),
        false,
    );
    assert.equal(
        resolveTransactionModalLoadError({
            open: true,
            hasData: false,
            requestState: 'error',
        }),
        'Failed to load transaction form data.',
    );
});
