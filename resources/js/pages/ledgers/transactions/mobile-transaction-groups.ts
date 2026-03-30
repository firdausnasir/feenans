import type { Transaction } from '../../../types/ledger';

export type MobileTransactionListItem =
    | {
          kind: 'transaction';
          transaction: Transaction;
      }
    | {
          kind: 'transfer_pair';
          pairId: string;
          transactions: Transaction[];
      };

export type MobileTransactionDateGroup = {
    date: string;
    items: MobileTransactionListItem[];
};

export function groupTransactionsForMobile(
    transactions: Transaction[],
): MobileTransactionDateGroup[] {
    const groups: MobileTransactionDateGroup[] = [];
    const groupsByDate = new Map<string, MobileTransactionDateGroup>();

    for (const transaction of transactions) {
        let group = groupsByDate.get(transaction.transaction_date);

        if (!group) {
            group = {
                date: transaction.transaction_date,
                items: [],
            };

            groupsByDate.set(transaction.transaction_date, group);
            groups.push(group);
        }

        if (
            transaction.transaction_type !== 'transfer' ||
            !transaction.transfer_pair_id
        ) {
            group.items.push({
                kind: 'transaction',
                transaction,
            });

            continue;
        }

        const previousItem = group.items.at(-1);
        const wrapper =
            previousItem?.kind === 'transfer_pair' &&
            previousItem.pairId === transaction.transfer_pair_id
                ? previousItem
                : {
                      kind: 'transfer_pair' as const,
                      pairId: transaction.transfer_pair_id,
                      transactions: [],
                  };

        if (wrapper !== previousItem) {
            group.items.push(wrapper);
        }

        wrapper.transactions.push(transaction);
    }

    return groups;
}
