import type { Account, AccountType } from '@/types';

export function resolveAccountTypeIsCredit(
    selectedType: AccountType | undefined,
    account: Account,
): boolean {
    return selectedType?.is_credit ?? account.account_type?.is_credit ?? false;
}

export function shouldShowAccountsEmptyState({
    hasResolvedInitialLoad,
    processing,
    groupsCount,
    hasError,
}: {
    hasResolvedInitialLoad: boolean;
    processing: boolean;
    groupsCount: number;
    hasError: boolean;
}): boolean {
    return (
        hasResolvedInitialLoad &&
        !processing &&
        !hasError &&
        groupsCount === 0
    );
}

export function getMutationRefreshNotice({
    refreshed,
    successMessage,
    staleDataMessage,
}: {
    refreshed: boolean;
    successMessage: string;
    staleDataMessage: string;
}): {
    level: 'success' | 'error';
    message: string;
} {
    return refreshed
        ? { level: 'success', message: successMessage }
        : { level: 'error', message: staleDataMessage };
}
