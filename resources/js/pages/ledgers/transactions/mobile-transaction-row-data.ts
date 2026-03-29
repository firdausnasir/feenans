import type { Transaction } from '../../../types/ledger';

export function resolveTransferCounterpart(
    transaction: Transaction,
    pairedTransactions: Transaction[] = [],
): string | null {
    return (
        transaction.transfer_pair?.account?.name ??
        pairedTransactions.find((item) => item.id !== transaction.id)?.account?.name ??
        null
    );
}

export function resolveMobileTransactionTitle(
    transaction: Transaction,
    pairedTransactions: Transaction[] = [],
): string {
    if (transaction.transaction_type === 'transfer') {
        const counterpart = resolveTransferCounterpart(transaction, pairedTransactions);

        return counterpart ? `${parseFloat(transaction.amount || '0') < 0 ? 'To' : 'From'} ${counterpart}` : 'Transfer';
    }

    return transaction.category?.name ?? 'Uncategorized';
}
