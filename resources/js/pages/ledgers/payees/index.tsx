import { Head, router } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import { toast } from 'sonner';
import { Search, Users } from 'lucide-react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { EmptyState } from '@/components/ui/empty-state';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import AppLayout from '@/layouts/app-layout';
import { dashboard as ledgerDashboard } from '@/routes/ledgers';
import {
    destroy,
    index as payeesIndex,
    merge,
    store,
    update,
} from '@/routes/ledgers/payees';
import { index as transactionsIndex } from '@/routes/ledgers/transactions';
import type { BreadcrumbItem, Ledger, Payee } from '@/types';

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

    function startEditing(payee: PayeeWithCount) {
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

    function navigateToTransactions(payee: PayeeWithCount) {
        router.get(
            transactionsIndex.url(ledger.id, {
                query: { payee_id: String(payee.id) },
            }),
        );
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
                        {canMerge && (
                            <Button
                                type="button"
                                variant="outline"
                                className="flex-1 sm:flex-initial"
                                onClick={startMerge}
                            >
                                Merge Selected
                            </Button>
                        )}
                        {selectedIds.size > 0 && !canMerge && (
                            <span className="text-sm text-muted-foreground">
                                Select exactly 2 payees to merge
                            </span>
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
                    <Card>
                        <CardContent className="p-0">
                            {/* Mobile card list */}
                            <div className="divide-y sm:hidden">
                                {payees.map((payee) => (
                                    <div
                                        key={payee.id}
                                        className="flex items-center gap-3 px-4 py-3"
                                    >
                                        <div
                                            onClick={(e) => e.stopPropagation()}
                                        >
                                            <Checkbox
                                                checked={selectedIds.has(
                                                    payee.id,
                                                )}
                                                onCheckedChange={() =>
                                                    toggleSelection(payee.id)
                                                }
                                                aria-label={`Select ${payee.name}`}
                                            />
                                        </div>
                                        <div className="min-w-0 flex-1">
                                            {editingId === payee.id ? (
                                                <div className="flex items-center gap-2">
                                                    <Input
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
                                                        className="h-8 text-sm"
                                                        autoFocus
                                                    />
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        className="h-8"
                                                        onClick={() =>
                                                            submitEdit(payee)
                                                        }
                                                    >
                                                        Save
                                                    </Button>
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        className="h-8"
                                                        onClick={cancelEditing}
                                                    >
                                                        Cancel
                                                    </Button>
                                                </div>
                                            ) : (
                                                <button
                                                    className="text-left text-sm font-medium"
                                                    onClick={() =>
                                                        startEditing(payee)
                                                    }
                                                >
                                                    {payee.name}
                                                </button>
                                            )}
                                            <p className="mt-0.5 text-xs text-muted-foreground">
                                                {payee.transactions_count}{' '}
                                                transaction
                                                {payee.transactions_count !== 1
                                                    ? 's'
                                                    : ''}
                                            </p>
                                        </div>
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            className="h-8 shrink-0 text-xs text-destructive"
                                            onClick={() =>
                                                setPayeeToDelete(payee)
                                            }
                                        >
                                            Delete
                                        </Button>
                                    </div>
                                ))}
                            </div>

                            {/* Desktop table */}
                            <Table className="hidden sm:table">
                                <TableHeader>
                                    <TableRow>
                                        <TableHead className="w-10">
                                            <span className="sr-only">
                                                Select
                                            </span>
                                        </TableHead>
                                        <TableHead>Name</TableHead>
                                        <TableHead>Transactions</TableHead>
                                        <TableHead className="sr-only">
                                            Actions
                                        </TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {payees.map((payee) => (
                                        <TableRow key={payee.id}>
                                            <TableCell>
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
                                            </TableCell>
                                            <TableCell className="font-medium">
                                                {editingId === payee.id ? (
                                                    <div className="flex items-center gap-2">
                                                        <Input
                                                            autoFocus
                                                            value={editingName}
                                                            onChange={(e) =>
                                                                setEditingName(
                                                                    e.target
                                                                        .value,
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
                                                            className="h-7 max-w-xs"
                                                        />
                                                        <Button
                                                            type="button"
                                                            size="sm"
                                                            className="h-auto px-2 py-0.5 text-xs"
                                                            onClick={() =>
                                                                submitEdit(
                                                                    payee,
                                                                )
                                                            }
                                                        >
                                                            Save
                                                        </Button>
                                                        <Button
                                                            type="button"
                                                            variant="outline"
                                                            size="sm"
                                                            className="h-auto px-2 py-0.5 text-xs"
                                                            onClick={
                                                                cancelEditing
                                                            }
                                                        >
                                                            Cancel
                                                        </Button>
                                                    </div>
                                                ) : (
                                                    <Button
                                                        type="button"
                                                        variant="ghost"
                                                        className="h-auto cursor-text rounded px-1 py-0 font-medium hover:bg-muted"
                                                        onClick={() =>
                                                            startEditing(payee)
                                                        }
                                                        title="Click to rename"
                                                    >
                                                        {payee.name}
                                                    </Button>
                                                )}
                                            </TableCell>
                                            <TableCell>
                                                <Button
                                                    type="button"
                                                    variant="ghost"
                                                    className="h-auto p-0 text-muted-foreground hover:bg-transparent hover:text-foreground hover:underline"
                                                    onClick={() =>
                                                        navigateToTransactions(
                                                            payee,
                                                        )
                                                    }
                                                    title="View transactions for this payee"
                                                >
                                                    {payee.transactions_count}
                                                </Button>
                                            </TableCell>
                                            <TableCell>
                                                <Button
                                                    type="button"
                                                    variant="ghost"
                                                    size="sm"
                                                    className="h-auto px-2 py-0.5 text-xs text-destructive hover:text-destructive"
                                                    onClick={() =>
                                                        setPayeeToDelete(payee)
                                                    }
                                                >
                                                    Delete
                                                </Button>
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </CardContent>
                    </Card>
                )}
            </div>

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
