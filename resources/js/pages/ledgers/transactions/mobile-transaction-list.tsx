import {
    ArrowRightLeft,
    ChevronDown,
    ChevronUp,
    MoreVertical,
    Paperclip,
} from 'lucide-react';
import { useState } from 'react';
import { TagPill } from '@/components/tag-pill';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { usePrivacyMode } from '@/contexts/privacy-mode-context';
import { amountColor, formatAbsAmount, formatDate } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { Transaction } from '@/types';
import { groupTransactionsForMobile } from './mobile-transaction-groups';
import { resolveMobileTransactionTitle } from './mobile-transaction-row-data';

type MobileTransactionListProps = {
    transactions: Transaction[];
    allSelected: boolean;
    someSelected: boolean;
    allAcrossPages: boolean;
    selectedIds: number[];
    excludedIds: number[];
    runningBalances: Map<number, number> | null;
    onSelectAll: (checked: boolean | 'indeterminate') => void;
    onSelectOne: (id: number, checked: boolean | 'indeterminate') => void;
    onEdit: (transaction: Transaction) => void;
    onDuplicate: (transaction: Transaction) => void;
    onDelete: (transaction: Transaction) => void;
    onAttachmentClick: (transaction: Transaction) => void;
};

export function MobileTransactionList({
    transactions,
    allSelected,
    someSelected,
    allAcrossPages,
    selectedIds,
    excludedIds,
    runningBalances,
    onSelectAll,
    onSelectOne,
    onEdit,
    onDuplicate,
    onDelete,
    onAttachmentClick,
}: MobileTransactionListProps) {
    const { privacyMode } = usePrivacyMode();
    const [expandedSplitIds, setExpandedSplitIds] = useState<number[]>([]);
    const groups = groupTransactionsForMobile(transactions);

    function isSelected(transaction: Transaction): boolean {
        return allAcrossPages
            ? !excludedIds.includes(transaction.id)
            : selectedIds.includes(transaction.id);
    }

    function toggleSplit(transactionId: number): void {
        setExpandedSplitIds((current) =>
            current.includes(transactionId)
                ? current.filter((id) => id !== transactionId)
                : [...current, transactionId],
        );
    }

    function renderTransactionRow(
        transaction: Transaction,
        pairedTransactions: Transaction[] = [],
    ) {
        const selected = isSelected(transaction);
        const amount = parseFloat(transaction.amount || '0');
        const runningBalance = runningBalances?.get(transaction.id) ?? null;
        const hasAttachments =
            (transaction.attachments?.length ?? 0) > 0 ||
            (transaction.attachments_count ?? 0) > 0;
        const hasTags = (transaction.tags?.length ?? 0) > 0;
        const isSplit =
            transaction.is_split === true && (transaction.splits?.length ?? 0) > 0;
        const isExpanded = expandedSplitIds.includes(transaction.id);
        const title = resolveMobileTransactionTitle(transaction, pairedTransactions);
        const meta = [
            transaction.transaction_type === 'transfer'
                ? transaction.description
                : transaction.payee?.name,
            transaction.transaction_type === 'transfer' ? null : transaction.description,
            transaction.transaction_type === 'transfer'
                ? null
                : transaction.account?.name,
        ]
            .filter((value): value is string => Boolean(value))
            .join(' · ');

        return (
            <div
                key={transaction.id}
                className={cn(
                    'px-3 py-2.5 transition-colors',
                    selected && 'bg-primary/5',
                )}
            >
                <div className="grid grid-cols-[auto_1fr_auto] gap-2">
                    <div
                        className="flex size-10 items-start justify-center pt-1"
                        onClick={() => onSelectOne(transaction.id, !selected)}
                    >
                        <Checkbox
                            checked={selected}
                            onCheckedChange={(checked) =>
                                onSelectOne(transaction.id, checked)
                            }
                            onClick={(event) => event.stopPropagation()}
                            aria-label={`Select transaction ${transaction.id}`}
                        />
                    </div>

                    <div className="min-w-0">
                        <button
                            type="button"
                            className="flex w-full items-start justify-between gap-3 text-left"
                            onClick={() => onEdit(transaction)}
                        >
                            <div className="min-w-0 space-y-0.5">
                                <div className="flex min-w-0 items-center gap-1.5">
                                    {transaction.transaction_type === 'transfer' && (
                                        <ArrowRightLeft className="size-3.5 shrink-0 text-muted-foreground" />
                                    )}
                                    <span
                                        className={cn(
                                            'truncate text-sm font-medium',
                                            !transaction.category &&
                                                transaction.transaction_type !== 'transfer' &&
                                                'text-muted-foreground',
                                        )}
                                    >
                                        {title}
                                    </span>
                                </div>

                                {meta !== '' && (
                                    <p className="truncate text-xs text-muted-foreground">
                                        {meta}
                                    </p>
                                )}

                                {runningBalance !== null && (
                                    <p
                                        className={cn(
                                            'text-[11px] tabular-nums',
                                            amountColor(runningBalance),
                                        )}
                                    >
                                        Balance {formatAbsAmount(runningBalance, privacyMode)}
                                    </p>
                                )}

                                {hasTags && (
                                    <div className="flex flex-wrap items-center gap-1 pt-0.5">
                                        {transaction.tags?.slice(0, 2).map((tag) => (
                                            <TagPill key={tag.id} tag={tag} />
                                        ))}
                                        {(transaction.tags?.length ?? 0) > 2 && (
                                            <Badge
                                                variant="secondary"
                                                className="h-5 px-1.5 text-[10px]"
                                            >
                                                +{(transaction.tags?.length ?? 0) - 2}
                                            </Badge>
                                        )}
                                    </div>
                                )}
                            </div>

                            <div className="shrink-0 text-right">
                                <p
                                    className={cn(
                                        'text-sm font-semibold tabular-nums',
                                        amountColor(amount),
                                    )}
                                >
                                    {formatAbsAmount(transaction.amount, privacyMode)}
                                </p>
                            </div>
                        </button>

                        {isSplit && (
                            <div className="mt-1.5">
                                <button
                                    type="button"
                                    className="inline-flex min-h-8 items-center gap-1 text-[11px] text-muted-foreground hover:text-foreground"
                                    onClick={() => toggleSplit(transaction.id)}
                                >
                                    {isExpanded ? (
                                        <ChevronUp className="size-3" />
                                    ) : (
                                        <ChevronDown className="size-3" />
                                    )}
                                    {transaction.splits?.length} split
                                    {transaction.splits?.length === 1 ? '' : 's'}
                                </button>

                                {isExpanded && (
                                    <div className="mt-1.5 space-y-1 rounded-lg bg-muted/40 px-2.5 py-2">
                                        {transaction.splits?.map((split) => {
                                            const splitAmount = parseFloat(split.amount || '0');
                                            const splitMeta = [
                                                split.payee?.name,
                                                split.description,
                                            ]
                                                .filter(
                                                    (value): value is string =>
                                                        Boolean(value),
                                                )
                                                .join(' · ');

                                            return (
                                                <div
                                                    key={split.id}
                                                    className="flex items-start justify-between gap-2"
                                                >
                                                    <div className="min-w-0">
                                                        <p className="truncate text-[11px] font-medium text-foreground">
                                                            {split.category?.name ?? 'Uncategorized'}
                                                        </p>
                                                        {splitMeta !== '' && (
                                                            <p className="truncate text-[11px] text-muted-foreground">
                                                                {splitMeta}
                                                            </p>
                                                        )}
                                                    </div>
                                                    <span
                                                        className={cn(
                                                            'shrink-0 text-[11px] tabular-nums',
                                                            amountColor(splitAmount),
                                                        )}
                                                    >
                                                        {formatAbsAmount(split.amount, privacyMode)}
                                                    </span>
                                                </div>
                                            );
                                        })}
                                    </div>
                                )}
                            </div>
                        )}
                    </div>

                    <div className="flex min-h-10 items-start gap-0.5">
                        {hasAttachments && (
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                className="size-8 text-muted-foreground"
                                onClick={() => onAttachmentClick(transaction)}
                            >
                                <Paperclip className="size-3.5" />
                            </Button>
                        )}

                        <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    className="size-8 text-muted-foreground"
                                >
                                    <MoreVertical className="size-4" />
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end">
                                <DropdownMenuItem onClick={() => onEdit(transaction)}>
                                    Edit
                                </DropdownMenuItem>
                                <DropdownMenuItem
                                    onClick={() => onDuplicate(transaction)}
                                >
                                    Duplicate
                                </DropdownMenuItem>
                                <DropdownMenuSeparator />
                                <DropdownMenuItem
                                    className="text-destructive focus:text-destructive"
                                    onClick={() => onDelete(transaction)}
                                >
                                    Delete
                                </DropdownMenuItem>
                            </DropdownMenuContent>
                        </DropdownMenu>
                    </div>
                </div>
            </div>
        );
    }

    return (
        <div className="space-y-3 sm:hidden">
            <div className="flex items-center gap-2 px-1">
                <Checkbox
                    checked={allSelected ? true : someSelected ? 'indeterminate' : false}
                    onCheckedChange={onSelectAll}
                    aria-label="Select all"
                />
                <span className="text-xs text-muted-foreground">Select all</span>
            </div>

            {groups.map((group) => (
                <section key={group.date} className="space-y-1.5">
                    <div className="px-1 text-[11px] font-medium tracking-[0.12em] text-muted-foreground uppercase">
                        {formatDate(group.date)}
                    </div>

                    <div className="overflow-hidden rounded-xl border border-border bg-card/90">
                        {group.items.map((item, index) => {
                            const bordered = index > 0 ? 'border-t border-border/70' : '';

                            if (item.kind === 'transfer_pair') {
                                return (
                                    <div key={item.pairId} className={cn('bg-muted/20', bordered)}>
                                        <div className="px-3 py-1 text-[10px] font-medium tracking-[0.14em] text-muted-foreground uppercase">
                                            Transfer
                                        </div>
                                        <div className="divide-y divide-border/70">
                                            {item.transactions.map((transaction) =>
                                                renderTransactionRow(
                                                    transaction,
                                                    item.transactions,
                                                ),
                                            )}
                                        </div>
                                    </div>
                                );
                            }

                            return (
                                <div key={item.transaction.id} className={bordered}>
                                    {renderTransactionRow(item.transaction)}
                                </div>
                            );
                        })}
                    </div>
                </section>
            ))}
        </div>
    );
}
