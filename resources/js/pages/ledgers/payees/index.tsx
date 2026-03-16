import { Head, Link, router } from '@inertiajs/react';
import { Loader2, Pencil, Search, Trash2, Users } from 'lucide-react';
import { useMemo, useState } from 'react';
import { toast } from 'sonner';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { EmptyState } from '@/components/ui/empty-state';
import { Input } from '@/components/ui/input';
import {
    Sheet,
    SheetContent,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import AppLayout from '@/layouts/app-layout';
import { formatAmount } from '@/lib/format';
import { cn } from '@/lib/utils';
import { dashboard as ledgerDashboard } from '@/routes/ledgers';
import {
    destroy,
    index as payeesIndex,
    merge,
    store,
    update,
} from '@/routes/ledgers/payees';
import { index as transactionsIndex } from '@/routes/ledgers/transactions';
import type { BreadcrumbItem, Ledger, Payee, Transaction } from '@/types';

type PayeeWithCount = Payee & { transactions_count: number };

type MergeState = {
    source: PayeeWithCount;
    target: PayeeWithCount;
};

export default function PayeesIndex({
    ledger,
    payees,
    filters,
}: {
    ledger: Ledger;
    payees: PayeeWithCount[];
    filters: { search: string };
}) {
    const [editingId, setEditingId] = useState<number | null>(null);
    const [editingName, setEditingName] = useState('');
    const [payeeToDelete, setPayeeToDelete] = useState<PayeeWithCount | null>(
        null,
    );
    const [isDeleting, setIsDeleting] = useState(false);
    const [showAddForm, setShowAddForm] = useState(false);
    const [newPayeeName, setNewPayeeName] = useState('');
    const [searchQuery, setSearchQuery] = useState(filters.search ?? '');
    const [selectedIds, setSelectedIds] = useState<Set<number>>(new Set());
    const [mergeState, setMergeState] = useState<MergeState | null>(null);
    const [isMerging, setIsMerging] = useState(false);
    const [selectionMode, setSelectionMode] = useState(false);

    // Transaction drill-down state
    const [selectedPayee, setSelectedPayee] = useState<PayeeWithCount | null>(
        null,
    );
    const [payeeTransactions, setPayeeTransactions] = useState<Transaction[]>(
        [],
    );
    const [loadingTransactions, setLoadingTransactions] = useState(false);

    const breadcrumbs: BreadcrumbItem[] = [
        { title: ledger.name, href: ledgerDashboard.url(ledger.id) },
        { title: 'Payees', href: payeesIndex.url(ledger.id) },
    ];

    const selectedPayees = useMemo(
        () => payees.filter((p) => selectedIds.has(p.id)),
        [payees, selectedIds],
    );

    const canMerge = selectedPayees.length === 2;

    function handleSearch(value: string) {
        setSearchQuery(value);
        router.get(
            payeesIndex.url(ledger.id),
            value.trim() ? { search: value.trim() } : {},
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
            },
        );
    }

    function toggleSelection(payeeId: number) {
        setSelectedIds((prev) => {
            const next = new Set(prev);

            if (next.has(payeeId)) {
                next.delete(payeeId);
            } else {
                next.add(payeeId);
            }

            return next;
        });
    }

    function clearSelection() {
        setSelectedIds(new Set());
        setSelectionMode(false);
    }

    function startMerge() {
        if (!canMerge) {
            return;
        }

        const [first, second] = selectedPayees;
        setMergeState({ source: first, target: second });
    }

    function swapMergeDirection() {
        if (!mergeState) {
            return;
        }

        setMergeState({
            source: mergeState.target,
            target: mergeState.source,
        });
    }

    function handleMerge() {
        if (!mergeState) {
            return;
        }

        setIsMerging(true);

        router.post(
            merge.url(ledger.id),
            {
                source_id: mergeState.source.id,
                target_id: mergeState.target.id,
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setMergeState(null);
                    setIsMerging(false);
                    clearSelection();
                    toast.success(
                        `Merged "${mergeState.source.name}" into "${mergeState.target.name}"`,
                    );
                },
                onError: (errors) => {
                    const message =
                        errors.source_id ??
                        errors.target_id ??
                        'Failed to merge payees.';
                    toast.error(message);
                    setIsMerging(false);
                },
            },
        );
    }

    function startEditing(payee: PayeeWithCount, event: React.MouseEvent) {
        event.stopPropagation();
        setEditingId(payee.id);
        setEditingName(payee.name);
    }

    function cancelEditing() {
        setEditingId(null);
        setEditingName('');
    }

    function submitEdit(payee: PayeeWithCount) {
        const trimmed = editingName.trim();

        if (!trimmed || trimmed === payee.name) {
            cancelEditing();

            return;
        }

        router.put(
            update.url({ ledger: ledger.id, payee: payee.id }),
            { name: trimmed },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setEditingId(null);
                    setEditingName('');
                    toast.success('Payee updated');
                },
                onError: (errors) => {
                    const message = errors.name ?? 'Failed to update payee.';
                    toast.error(message);
                },
            },
        );
    }

    async function handlePayeeClick(payee: PayeeWithCount) {
        if (selectionMode) {
            toggleSelection(payee.id);

            return;
        }

        if (editingId === payee.id) {
            return;
        }

        setSelectedPayee(payee);
        setLoadingTransactions(true);
        setPayeeTransactions([]);

        try {
            const url = transactionsIndex.url(ledger.id, {
                query: { 'payee_ids[]': String(payee.id) },
            });
            const response = await fetch(url, {
                headers: { Accept: 'application/json' },
            });

            if (!response.ok) {
                throw new Error('Failed to fetch transactions');
            }

            const data = await response.json();
            setPayeeTransactions(data.transactions?.data ?? []);
        } catch {
            setPayeeTransactions([]);
            toast.error('Failed to load transactions.');
        } finally {
            setLoadingTransactions(false);
        }
    }

    function handleDelete() {
        if (!payeeToDelete) {
            return;
        }

        setIsDeleting(true);

        router.delete(
            destroy.url({ ledger: ledger.id, payee: payeeToDelete.id }),
            {
                preserveScroll: true,
                onSuccess: () => {
                    setPayeeToDelete(null);
                    setIsDeleting(false);

                    if (selectedPayee?.id === payeeToDelete.id) {
                        setSelectedPayee(null);
                        setPayeeTransactions([]);
                    }

                    toast.success('Payee deleted');
                },
                onError: () => {
                    toast.error('Failed to delete payee.');
                    setIsDeleting(false);
                },
            },
        );
    }

    function handleAddPayee() {
        const trimmed = newPayeeName.trim();

        if (!trimmed) {
            toast.error('Payee name is required.');

            return;
        }

        router.post(
            store.url(ledger.id),
            { name: trimmed },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setNewPayeeName('');
                    setShowAddForm(false);
                    toast.success('Payee added');
                },
                onError: (errors) => {
                    const message = errors.name ?? 'Failed to add payee.';
                    toast.error(message);
                },
            },
        );
    }

    const hasPayees = payees.length > 0 || filters.search;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`${ledger.name} payees`} />

            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6 lg:p-8">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <Heading
                        title="Payees"
                        description="Manage payees for this ledger."
                    />

                    <div className="flex w-full items-center gap-2 sm:w-auto">
                        {selectionMode && (
                            <>
                                {canMerge && (
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        onClick={startMerge}
                                    >
                                        Merge Selected
                                    </Button>
                                )}
                                {selectedIds.size > 0 && !canMerge && (
                                    <span className="text-sm text-muted-foreground">
                                        Select exactly 2 to merge
                                    </span>
                                )}
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    onClick={clearSelection}
                                >
                                    Cancel
                                </Button>
                            </>
                        )}
                        {!selectionMode && (
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                onClick={() => setSelectionMode(true)}
                            >
                                Select to Merge
                            </Button>
                        )}
                        <Button
                            type="button"
                            className="flex-1 sm:flex-initial"
                            onClick={() => {
                                setShowAddForm(true);
                                setNewPayeeName('');
                            }}
                        >
                            Add Payee
                        </Button>
                    </div>
                </div>

                {/* Search bar */}
                {hasPayees && (
                    <div className="relative max-w-sm">
                        <Search className="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            placeholder="Search payees..."
                            value={searchQuery}
                            onChange={(e) => handleSearch(e.target.value)}
                            className="pl-9"
                        />
                    </div>
                )}

                {/* Inline add form */}
                {showAddForm && (
                    <Card className="py-3">
                        <CardContent>
                            <div className="flex items-center gap-2">
                                <Input
                                    autoFocus
                                    placeholder="Payee name"
                                    value={newPayeeName}
                                    onChange={(e) =>
                                        setNewPayeeName(e.target.value)
                                    }
                                    onKeyDown={(e) => {
                                        if (e.key === 'Enter') {
                                            handleAddPayee();
                                        } else if (e.key === 'Escape') {
                                            setShowAddForm(false);
                                            setNewPayeeName('');
                                        }
                                    }}
                                    className="h-8 max-w-xs"
                                />
                                <Button
                                    type="button"
                                    size="sm"
                                    onClick={handleAddPayee}
                                >
                                    Save
                                </Button>
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    onClick={() => {
                                        setShowAddForm(false);
                                        setNewPayeeName('');
                                    }}
                                >
                                    Cancel
                                </Button>
                            </div>
                        </CardContent>
                    </Card>
                )}

                {payees.length === 0 && !showAddForm ? (
                    <EmptyState
                        icon={<Users className="size-6" />}
                        title={
                            filters.search
                                ? 'No payees match your search'
                                : 'No payees yet'
                        }
                        description={
                            filters.search
                                ? 'Try a different search term.'
                                : 'Payees will appear here as you create transactions.'
                        }
                    />
                ) : (
                    <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        {payees.map((payee) => (
                            <Card
                                key={payee.id}
                                className={cn(
                                    'cursor-pointer transition-shadow hover:shadow-md',
                                    selectionMode &&
                                        selectedIds.has(payee.id) &&
                                        'bg-accent/50 ring-2 ring-primary',
                                )}
                                onClick={() => handlePayeeClick(payee)}
                            >
                                <CardContent className="flex items-center justify-between p-4">
                                    <div className="flex min-w-0 flex-1 items-center gap-3">
                                        {selectionMode && (
                                            <div
                                                onClick={(e) =>
                                                    e.stopPropagation()
                                                }
                                            >
                                                <Checkbox
                                                    checked={selectedIds.has(
                                                        payee.id,
                                                    )}
                                                    onCheckedChange={() =>
                                                        toggleSelection(
                                                            payee.id,
                                                        )
                                                    }
                                                    aria-label={`Select ${payee.name}`}
                                                />
                                            </div>
                                        )}
                                        <div className="min-w-0 flex-1">
                                            {editingId === payee.id ? (
                                                <div
                                                    className="flex items-center gap-2"
                                                    onClick={(e) =>
                                                        e.stopPropagation()
                                                    }
                                                >
                                                    <Input
                                                        autoFocus
                                                        value={editingName}
                                                        onChange={(e) =>
                                                            setEditingName(
                                                                e.target.value,
                                                            )
                                                        }
                                                        onKeyDown={(e) => {
                                                            if (
                                                                e.key ===
                                                                'Enter'
                                                            ) {
                                                                submitEdit(
                                                                    payee,
                                                                );
                                                            } else if (
                                                                e.key ===
                                                                'Escape'
                                                            ) {
                                                                cancelEditing();
                                                            }
                                                        }}
                                                        className="h-7 text-sm"
                                                    />
                                                    <Button
                                                        type="button"
                                                        size="sm"
                                                        className="h-7 px-2 text-xs"
                                                        onClick={(e) => {
                                                            e.stopPropagation();
                                                            submitEdit(payee);
                                                        }}
                                                    >
                                                        Save
                                                    </Button>
                                                    <Button
                                                        type="button"
                                                        variant="outline"
                                                        size="sm"
                                                        className="h-7 px-2 text-xs"
                                                        onClick={(e) => {
                                                            e.stopPropagation();
                                                            cancelEditing();
                                                        }}
                                                    >
                                                        Cancel
                                                    </Button>
                                                </div>
                                            ) : (
                                                <>
                                                    <p className="truncate font-medium">
                                                        {payee.name}
                                                    </p>
                                                    <p className="text-sm text-muted-foreground">
                                                        {
                                                            payee.transactions_count
                                                        }{' '}
                                                        transaction
                                                        {payee.transactions_count !==
                                                        1
                                                            ? 's'
                                                            : ''}
                                                    </p>
                                                </>
                                            )}
                                        </div>
                                    </div>
                                    {editingId !== payee.id && (
                                        <div className="flex items-center gap-1">
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="icon"
                                                className="size-7"
                                                onClick={(e) =>
                                                    startEditing(payee, e)
                                                }
                                                title="Rename"
                                            >
                                                <Pencil className="size-3.5" />
                                            </Button>
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="icon"
                                                className="size-7 text-destructive hover:text-destructive"
                                                onClick={(e) => {
                                                    e.stopPropagation();
                                                    setPayeeToDelete(payee);
                                                }}
                                                title="Delete"
                                            >
                                                <Trash2 className="size-3.5" />
                                            </Button>
                                        </div>
                                    )}
                                </CardContent>
                            </Card>
                        ))}
                    </div>
                )}
            </div>

            {/* Transaction drill-down sheet */}
            <Sheet
                open={selectedPayee !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setSelectedPayee(null);
                        setPayeeTransactions([]);
                    }
                }}
            >
                <SheetContent
                    side="right"
                    className="overflow-y-auto sm:max-w-md"
                >
                    <SheetHeader>
                        <SheetTitle>{selectedPayee?.name}</SheetTitle>
                    </SheetHeader>

                    <div className="px-4 pb-6">
                        {loadingTransactions ? (
                            <div className="flex items-center justify-center py-8">
                                <Loader2 className="size-5 animate-spin text-muted-foreground" />
                            </div>
                        ) : payeeTransactions.length === 0 ? (
                            <p className="py-6 text-center text-sm text-muted-foreground">
                                No transactions found for this payee.
                            </p>
                        ) : (
                            <div className="space-y-2">
                                {payeeTransactions.map((txn) => (
                                    <div
                                        key={txn.id}
                                        className="flex items-center justify-between rounded-lg border px-3 py-2 text-sm"
                                    >
                                        <div className="min-w-0 flex-1">
                                            <div className="flex items-center gap-2">
                                                <span className="truncate font-medium">
                                                    {txn.description ||
                                                        'No description'}
                                                </span>
                                                <Badge
                                                    variant="outline"
                                                    className="shrink-0 text-xs"
                                                >
                                                    {txn.transaction_type}
                                                </Badge>
                                            </div>
                                            <div className="mt-0.5 flex items-center gap-2 text-xs text-muted-foreground">
                                                <span>
                                                    {txn.transaction_date?.slice(
                                                        0,
                                                        10,
                                                    )}
                                                </span>
                                                {txn.account && (
                                                    <>
                                                        <span>-</span>
                                                        <span>
                                                            {txn.account.name}
                                                        </span>
                                                    </>
                                                )}
                                                {txn.category && (
                                                    <>
                                                        <span>-</span>
                                                        <span>
                                                            {txn.category.name}
                                                        </span>
                                                    </>
                                                )}
                                            </div>
                                        </div>
                                        <span
                                            className={cn(
                                                'ml-3 shrink-0 font-mono text-sm font-medium',
                                                txn.transaction_type ===
                                                    'income'
                                                    ? 'text-green-600 dark:text-green-400'
                                                    : txn.transaction_type ===
                                                        'expense'
                                                      ? 'text-red-600 dark:text-red-400'
                                                      : '',
                                            )}
                                        >
                                            {formatAmount(txn.amount)}
                                        </span>
                                    </div>
                                ))}
                                <div className="pt-2 text-center">
                                    <Button
                                        type="button"
                                        variant="link"
                                        size="sm"
                                        asChild
                                    >
                                        <Link
                                            href={transactionsIndex.url(
                                                ledger.id,
                                                {
                                                    query: {
                                                        'payee_ids[]': String(
                                                            selectedPayee?.id ??
                                                                '',
                                                        ),
                                                    },
                                                },
                                            )}
                                        >
                                            View all transactions
                                        </Link>
                                    </Button>
                                </div>
                            </div>
                        )}
                    </div>
                </SheetContent>
            </Sheet>

            {/* Delete confirmation dialog */}
            <Dialog
                open={payeeToDelete !== null}
                onOpenChange={(open) => {
                    if (!open && !isDeleting) {
                        setPayeeToDelete(null);
                    }
                }}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Delete payee</DialogTitle>
                        <DialogDescription>
                            Are you sure you want to delete{' '}
                            <strong>{payeeToDelete?.name}</strong>?
                            {payeeToDelete &&
                                payeeToDelete.transactions_count > 0 && (
                                    <>
                                        {' '}
                                        This payee has{' '}
                                        <strong>
                                            {payeeToDelete.transactions_count}{' '}
                                            {payeeToDelete.transactions_count ===
                                            1
                                                ? 'transaction'
                                                : 'transactions'}
                                        </strong>
                                        . The transactions will not be deleted
                                        but will no longer be associated with a
                                        payee.
                                    </>
                                )}
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button
                            variant="outline"
                            onClick={() => {
                                if (!isDeleting) {
                                    setPayeeToDelete(null);
                                }
                            }}
                            disabled={isDeleting}
                        >
                            Cancel
                        </Button>
                        <Button
                            variant="destructive"
                            onClick={handleDelete}
                            disabled={isDeleting}
                        >
                            {isDeleting ? 'Deleting...' : 'Delete'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            {/* Merge confirmation dialog */}
            <Dialog
                open={mergeState !== null}
                onOpenChange={(open) => {
                    if (!open && !isMerging) {
                        setMergeState(null);
                    }
                }}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Merge payees</DialogTitle>
                        <DialogDescription>
                            All{' '}
                            <strong>
                                {mergeState?.source.transactions_count ?? 0}
                            </strong>{' '}
                            {(mergeState?.source.transactions_count ?? 0) === 1
                                ? 'transaction'
                                : 'transactions'}{' '}
                            from <strong>{mergeState?.source.name}</strong> will
                            be reassigned to{' '}
                            <strong>{mergeState?.target.name}</strong>, and{' '}
                            <strong>{mergeState?.source.name}</strong> will be
                            deleted.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button
                            variant="outline"
                            onClick={swapMergeDirection}
                            disabled={isMerging}
                        >
                            Swap Direction
                        </Button>
                        <Button
                            variant="outline"
                            onClick={() => {
                                if (!isMerging) {
                                    setMergeState(null);
                                }
                            }}
                            disabled={isMerging}
                        >
                            Cancel
                        </Button>
                        <Button onClick={handleMerge} disabled={isMerging}>
                            {isMerging ? 'Merging...' : 'Merge'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}
