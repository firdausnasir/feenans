import assert from 'node:assert/strict';
import test from 'node:test';

const {
    getMutationRefreshNotice,
    resolveAccountTypeIsCredit,
    shouldShowAccountsEmptyState,
} = await import(
    new URL('./page-state.ts', import.meta.url).href
);

test('resolveAccountTypeIsCredit falls back to account payload metadata when the loader type is unavailable', () => {
    const account = {
        account_type: {
            is_credit: true,
        },
    };

    assert.equal(
        resolveAccountTypeIsCredit(
            undefined,
            account as {
                account_type?: { is_credit?: boolean };
            },
        ),
        true,
    );
});

test('shouldShowAccountsEmptyState stays false before the first accounts load resolves', () => {
    assert.equal(
        shouldShowAccountsEmptyState({
            hasResolvedInitialLoad: false,
            processing: false,
            groupsCount: 0,
            hasError: false,
        }),
        false,
    );
});

test('shouldShowAccountsEmptyState becomes true after an empty accounts load completes', () => {
    assert.equal(
        shouldShowAccountsEmptyState({
            hasResolvedInitialLoad: true,
            processing: false,
            groupsCount: 0,
            hasError: false,
        }),
        true,
    );
});

test('getMutationRefreshNotice returns an error notice when refresh fails after a successful mutation', () => {
    assert.deepEqual(
        getMutationRefreshNotice({
            refreshed: false,
            successMessage: 'Account updated',
            staleDataMessage: 'Account updated, but failed to refresh account data.',
        }),
        {
            level: 'error',
            message: 'Account updated, but failed to refresh account data.',
        },
    );
});
