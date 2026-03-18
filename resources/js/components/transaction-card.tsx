import { Link } from '@inertiajs/react';
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
import { Checkbox } from '@/components/ui/checkbox';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { formatAbsAmount, formatDate } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { Transaction } from '@/types';

// ─── Types ────────────────────────────────────────────────────────────────────

type TransactionCardAction = {
    label: string;
    onClick: () => void;
    variant?: 'default' | 'destructive';
    separator?: boolean;
};

type TransactionCardProps = {
    transaction: Transaction;
    /** Show checkbox and enable selection mode */
    selectable?: boolean;
    /** Whether this card is currently selected */
    selected?: boolean;
    /** Callback when selection changes */
    onSelectChange?: (checked: boolean) => void;
    /** Actions shown in the ⋮ menu */
    actions?: TransactionCardAction[];
    /** Running balance for this transaction (shown when single account filtered) */
    runningBalance?: number | null;
    /** Edit URL for the transaction */
    editUrl?: string;
};

// ─── Helpers ──────────────────────────────────────────────────────────────────

function amountColor(value: number): string {
    return value < 0
        ? 'text-red-500 dark:text-red-400'
        : 'text-foreground';
}

// ─── Component ────────────────────────────────────────────────────────────────

export function TransactionCard({
    transaction,
    selectable = false,
    selected = false,
    onSelectChange,
    actions = [],
    runningBalance = null,
    editUrl,
}: TransactionCardProps) {
    const [splitsExpanded, setSplitsExpanded] = useState(false);

    const isTransfer = transaction.transaction_type === 'transfer';
    const isSplit = transaction.is_split && (transaction.splits?.length ?? 0) > 0;
    const hasTags = (transaction.tags?.length ?? 0) > 0;
    const hasAttachments = (transaction.attachments?.length ?? 0) > 0;
    const hasDescription = !!transaction.description;
    const amount = parseFloat(transaction.amount);

    return (
        <div
            className={cn(
                'group relative flex rounded-lg border transition-colors',
                selected
                    ? 'border-primary/30 bg-primary/5'
                    : 'border-border bg-card hover:bg-muted/50',
            )}
        >
            {/* Checkbox zone */}
            {selectable && (
                <button
                    type="button"
                    className="relative z-10 flex w-12 shrink-0 items-center justify-center border-r border-border sm:w-14"
                    onClick={() => onSelectChange?.(!selected)}
                >
                    <Checkbox
                        checked={selected}
                        onCheckedChange={(checked) =>
                            onSelectChange?.(checked === true)
                        }
                        onClick={(e) => e.stopPropagation()}
                    />
                </button>
            )}

            {/* Content */}
            <div className="relative z-10 flex min-w-0 flex-1 flex-col gap-1.5 px-3 py-3 sm:px-4">
                {/* Row 1: Category + Amount */}
                <div className="flex items-start justify-between gap-2">
                    <div className="flex min-w-0 items-center gap-1.5">
                        {isTransfer ? (
                            <>
                                <ArrowRightLeft className="size-3.5 shrink-0 text-muted-foreground" />
                                <span className="truncate text-sm font-semibold text-muted-foreground">
                                    Transfer
                                    {transaction.transfer_pair?.account
                                        ? ` → ${transaction.transfer_pair.account.name}`
                                        : ''}
                                </span>
                            </>
                        ) : transaction.category ? (
                            <>
                                {transaction.category.color && (
                                    <span
                                        className="size-2.5 shrink-0 rounded-full"
                                        style={{
                                            backgroundColor:
                                                transaction.category.color,
                                        }}
                                    />
                                )}
                                <span className="truncate text-sm font-semibold">
                                    {transaction.category.name}
                                </span>
                            </>
                        ) : (
                            <span className="truncate text-sm font-semibold italic text-muted-foreground">
                                Uncategorized
                            </span>
                        )}
                    </div>

                    <div className="flex shrink-0 items-center gap-1.5">
                        <span
                            className={cn(
                                'text-sm font-bold tabular-nums',
                                amountColor(amount),
                            )}
                        >
                            {formatAbsAmount(transaction.amount)}
                        </span>

                        {/* Action menu */}
                        {actions.length > 0 && (
                            <DropdownMenu>
                                <DropdownMenuTrigger asChild>
                                    <button
                                        type="button"
                                        className="relative z-20 flex size-6 items-center justify-center rounded text-muted-foreground opacity-0 transition-opacity hover:bg-muted group-hover:opacity-100 data-[state=open]:opacity-100"
                                    >
                                        <MoreVertical className="size-4" />
                                    </button>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent align="end">
                                    {actions.map((action, idx) => (
                                        <span key={action.label}>
                                            {action.separator && idx > 0 && (
                                                <DropdownMenuSeparator />
                                            )}
                                            <DropdownMenuItem
                                                className={
                                                    action.variant ===
                                                    'destructive'
                                                        ? 'text-destructive focus:text-destructive'
                                                        : ''
                                                }
                                                onClick={action.onClick}
                                            >
                                                {action.label}
                                            </DropdownMenuItem>
                                        </span>
                                    ))}
                                </DropdownMenuContent>
                            </DropdownMenu>
                        )}
                    </div>
                </div>

                {/* Row 2: Payee (skip for transfers) */}
                {!isTransfer && transaction.payee && (
                    <p className="truncate text-sm text-foreground">
                        {transaction.payee.name}
                    </p>
                )}

                {/* Row 3: Description */}
                {hasDescription && (
                    <p className="truncate text-xs text-muted-foreground">
                        {transaction.description}
                    </p>
                )}

                {/* Row 4: Account · Date + Tags + Attachment */}
                <div className="flex items-center justify-between gap-2">
                    <div className="flex min-w-0 items-center gap-1 text-xs text-muted-foreground">
                        {transaction.account && (
                            <>
                                <span className="truncate">
                                    {transaction.account.name}
                                </span>
                                <span>·</span>
                            </>
                        )}
                        <span className="shrink-0">
                            {formatDate(transaction.transaction_date)}
                        </span>
                    </div>

                    <div className="flex shrink-0 items-center gap-1.5">
                        {hasTags && (
                            <div className="hidden items-center gap-1 sm:flex">
                                {transaction.tags!.slice(0, 2).map((tag) => (
                                    <TagPill key={tag.id} tag={tag} />
                                ))}
                                {transaction.tags!.length > 2 && (
                                    <Badge
                                        variant="secondary"
                                        className="px-1 py-0 text-[10px]"
                                    >
                                        +{transaction.tags!.length - 2}
                                    </Badge>
                                )}
                            </div>
                        )}
                        {hasAttachments && (
                            <Paperclip className="size-3.5 text-muted-foreground" />
                        )}
                    </div>
                </div>

                {/* Running balance */}
                {runningBalance !== null && (
                    <p
                        className={`text-right text-xs tabular-nums ${amountColor(runningBalance)}`}
                    >
                        Balance: {formatAbsAmount(runningBalance)}
                    </p>
                )}

                {/* Split details (expandable) */}
                {isSplit && (
                    <div className="mt-1">
                        <button
                            type="button"
                            className="relative z-20 flex items-center gap-1 text-xs text-muted-foreground hover:text-foreground"
                            onClick={() => setSplitsExpanded(!splitsExpanded)}
                        >
                            {splitsExpanded ? (
                                <ChevronUp className="size-3" />
                            ) : (
                                <ChevronDown className="size-3" />
                            )}
                            {transaction.splits!.length} split
                            {transaction.splits!.length > 1 ? 's' : ''}
                        </button>

                        {splitsExpanded && (
                            <div className="mt-2 space-y-1.5 border-l-2 border-border pl-3">
                                {transaction.splits!.map((split) => {
                                    const splitAmount = parseFloat(split.amount);

                                    return (
                                        <div
                                            key={split.id}
                                            className="flex items-center justify-between text-xs"
                                        >
                                            <div className="flex min-w-0 items-center gap-1.5">
                                                {split.category?.color && (
                                                    <span
                                                        className="size-2 shrink-0 rounded-full"
                                                        style={{
                                                            backgroundColor:
                                                                split.category
                                                                    .color,
                                                        }}
                                                    />
                                                )}
                                                <span className="truncate">
                                                    {split.category?.name ??
                                                        'Uncategorized'}
                                                </span>
                                                {split.payee && (
                                                    <>
                                                        <span className="text-muted-foreground">
                                                            ·
                                                        </span>
                                                        <span className="truncate text-muted-foreground">
                                                            {split.payee.name}
                                                        </span>
                                                    </>
                                                )}
                                                {split.description && (
                                                    <>
                                                        <span className="text-muted-foreground">
                                                            ·
                                                        </span>
                                                        <span className="truncate text-muted-foreground italic">
                                                            {split.description}
                                                        </span>
                                                    </>
                                                )}
                                            </div>
                                            <span
                                                className={cn(
                                                    'shrink-0 tabular-nums',
                                                    amountColor(splitAmount),
                                                )}
                                            >
                                                {formatAbsAmount(split.amount)}
                                            </span>
                                        </div>
                                    );
                                })}
                            </div>
                        )}
                    </div>
                )}
            </div>

            {/* Click to edit overlay */}
            {editUrl && !selectable && (
                <Link
                    href={editUrl}
                    className="absolute inset-0 z-0 rounded-lg"
                    aria-label={`Edit transaction: ${transaction.description ?? 'transaction'}`}
                />
            )}
        </div>
    );
}
