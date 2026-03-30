import assert from 'node:assert/strict';
import test from 'node:test';

const { buildAddTransactionSubmitOptions } = await import(
    new URL('./add-transaction-modal-options.ts', import.meta.url).href
);

test('add transaction submit keeps modal data props during validation redirects', () => {
    assert.deepEqual(buildAddTransactionSubmitOptions(), {
        preserveState: true,
        except: ['transactionModalData'],
    });
});
