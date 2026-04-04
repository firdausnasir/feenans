export type TransactionModalRequestState = 'idle' | 'loading' | 'error';

export function shouldLoadTransactionModalData({
    open,
    hasData,
    requestState,
}: {
    open: boolean;
    hasData: boolean;
    requestState: TransactionModalRequestState;
}): boolean {
    return open && !hasData && requestState === 'idle';
}

export function shouldShowTransactionModalLoading({
    open,
    hasData,
    requestState,
}: {
    open: boolean;
    hasData: boolean;
    requestState: TransactionModalRequestState;
}): boolean {
    return open && !hasData && requestState !== 'error';
}

export function resolveTransactionModalLoadError({
    open,
    hasData,
    requestState,
}: {
    open: boolean;
    hasData: boolean;
    requestState: TransactionModalRequestState;
}): string | null {
    if (!open || hasData || requestState !== 'error') {
        return null;
    }

    return 'Failed to load transaction form data.';
}
