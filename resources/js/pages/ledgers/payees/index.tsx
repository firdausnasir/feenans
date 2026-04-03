import { Head, Link, router, useHttp, usePage } from '@inertiajs/react';
import { ExternalLink, Pencil, Search, Trash2, Users } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { toast } from 'sonner';
import { index as payeesLoader } from '@/actions/App/Http/Controllers/Api/V1/Ledger/PayeeController';
import {
    destroy as destroyPayee,
    store as storePayee,
    update as updatePayee,
} from '@/actions/App/Http/Controllers/Ledger/PayeeController';
import { Button } from '@/components/ui/button';
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
import { Skeleton } from '@/components/ui/skeleton';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import AppLayout from '@/layouts/app-layout';
import { dashboard as ledgerDashboard } from '@/routes/ledgers';
import { index as payeesIndex } from '@/routes/ledgers/payees';
import { index as transactionsIndex } from '@/routes/ledgers/transactions';
import type { BreadcrumbItem, Payee } from '@/types';

type PayeeWithCount = Payee & { transactions_count: number };
type ApiEnvelope<T> = { data: T };

type PayeesPageProps = {
    search: string;
};

function buildPayeesQuery(search: string): Record<string, string> {
    if (!search) {
        return {};
    }

    return { search };
}

function updatePayeesUrl(ledgerId: number, search: string): void {
    if (typeof window === 'undefined') {
        return;
    }

    const url = new URL(payeesIndex.url(ledgerId), window.location.origin);

    for (const [key, value] of Object.entries(buildPayeesQuery(search))) {
        url.searchParams.set(key, value);
    }

    window.history.replaceState(window.history.state, '', `${url.pathname}${url.search}`);
}

function PayeesLoadingSkeleton() {
    return (
        <div className="grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-3">
            {Array.from({ length: 6 }).map((_, i) => (
                <div
                    key={i}
                    className="flex items-center rounded-lg border border-border bg-card"
                >
                    <div className="flex-1 space-y-1.5 px-3 py-2.5">
                        <Skeleton className="h-4 w-32" />
                        <Skeleton className="h-3 w-20" />
                    </div>
                    <div className="w-10 border-l border-border" />
                </div>
            ))}
        </div>
    );
}

function PayeesErrorState({ onRetry }: { onRetry: () => void }) {
    return (
        <div className="rounded-lg border border-border bg-card p-4">
            <div className="flex flex-col gap-3">
                <p className="text-sm text-muted-foreground">
                    Failed to load payees.
                </p>
                <div>
                    <Button variant="outline" size="sm" onClick={onRetry}>
                        Retry
                    </Button>
                </div>
            </div>
        </div>
    );
}

function PayeesContent({
    ledgerId,
    payees,
    onEdit,
    onDelete,
}: {
    ledgerId: number;
    payees: PayeeWithCount[];
    onEdit: (payee: PayeeWithCount) => void;
    onDelete: (payee: PayeeWithCount) => void;
}) {
    if (payees.length === 0) {
        return (
            <EmptyState
                icon={<Users className="size-6" />}
                title="No payees found"
                description="Payees will appear here as you create transactions."
            />
        );
    }

    return (
        <div className="grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-3">
            {payees.map((payee) => (
                <div
                    key={payee.id}
                    className="group flex rounded-lg border border-border bg-card"
                >
                    {/* Content */}
                    <div className="min-w-0 flex-1 px-3 py-2.5">
                        <p className="truncate text-sm font-medium">
                            {payee.name}
                        </p>
                        <p className="text-xs text-muted-foreground">
                            {payee.transactions_count} transaction
                            {payee.transactions_count !== 1 ? 's' : ''}
                        </p>
                    </div>

                    {/* Action strip */}
                    <div className="flex shrink-0 flex-col items-center justify-center gap-0.5 border-l border-border px-1.5">
                        <Tooltip>
                            <TooltipTrigger asChild>
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    className="size-8"
                                    asChild
                                >
                                    <Link
                                        href={transactionsIndex.url(ledgerId, {
                                            query: {
                                                'payee_ids[]': String(payee.id),
                                            },
                                        })}
                                    >
                                        <ExternalLink className="size-3.5" />
                                    </Link>
                                </Button>
                            </TooltipTrigger>
                            <TooltipContent>Transactions</TooltipContent>
                        </Tooltip>
                        <Tooltip>
                            <TooltipTrigger asChild>
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    className="size-8"
                                    onClick={() => onEdit(payee)}
                                >
                                    <Pencil className="size-3.5" />
                                </Button>
                            </TooltipTrigger>
                            <TooltipContent>Edit</TooltipContent>
                        </Tooltip>
                        <Tooltip>
                            <TooltipTrigger asChild>
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    className="size-8 text-red-500 hover:text-red-600 dark:text-red-400 dark:hover:text-red-300"
                                    onClick={() => onDelete(payee)}
                                >
                                    <Trash2 className="size-3.5" />
                                </Button>
                            </TooltipTrigger>
                            <TooltipContent>Delete</TooltipContent>
                        </Tooltip>
                    </div>
                </div>
            ))}
        </div>
    );
}

export default function PayeesIndex() {
    const { currentLedger } = usePage().props;
    const ledger = currentLedger!;
    const { search: committedSearch } = usePage<PayeesPageProps>().props;
    const payeesLoaderState = useHttp<Record<string, never>, ApiEnvelope<PayeeWithCount[]>>({});

    const [localSearch, setLocalSearch] = useState(committedSearch);
    const [showAddForm, setShowAddForm] = useState(false);
    const [newPayeeName, setNewPayeeName] = useState('');
    const [payeesError, setPayeesError] = useState<string | null>(null);
    const [hasLoadedPayees, setHasLoadedPayees] = useState(false);
    const latestRequestRef = useRef(0);

    // Edit state
    const [editingPayee, setEditingPayee] = useState<PayeeWithCount | null>(
        null,
    );
    const [editingName, setEditingName] = useState('');

    // Delete state
    const [payeeToDelete, setPayeeToDelete] = useState<PayeeWithCount | null>(
        null,
    );
    const [isDeleting, setIsDeleting] = useState(false);

    const breadcrumbs: BreadcrumbItem[] = [
        { title: ledger.name, href: ledgerDashboard.url(ledger.id) },
        { title: 'Payees', href: payeesIndex.url(ledger.id) },
    ];

    const payees = payeesLoaderState.response?.data ?? [];

    async function loadPayees(search: string): Promise<boolean> {
        let cancelled = false;
        const requestId = latestRequestRef.current + 1;

        latestRequestRef.current = requestId;

        payeesLoaderState.cancel();
        setPayeesError(null);
        updatePayeesUrl(ledger.id, search);

        try {
            await payeesLoaderState.get(
                payeesLoader.url(
                    { ledger: ledger.id },
                    { query: buildPayeesQuery(search) },
                ),
                {
                    onCancel: () => {
                        cancelled = true;
                    },
                },
            );

            return true;
        } catch {
            if (!cancelled && latestRequestRef.current === requestId) {
                setPayeesError('Failed to load payees.');
            }

            return false;
        } finally {
            if (!cancelled && latestRequestRef.current === requestId) {
                setHasLoadedPayees(true);
            }
        }
    }

    async function refreshPayeesAfterMutation(successMessage: string): Promise<void> {
        const refreshed = await loadPayees(localSearch);

        toast[refreshed ? 'success' : 'error'](
            refreshed
                ? successMessage
                : `${successMessage}, but failed to refresh payees.`,
        );
    }

    useEffect(() => {
        setLocalSearch(committedSearch);
        setPayeesError(null);
        setHasLoadedPayees(false);
        void loadPayees(committedSearch);

        return () => {
            payeesLoaderState.cancel();
        };
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [committedSearch, ledger.id]);

    function handleSearch(value: string) {
        setLocalSearch(value);
        void loadPayees(value);
    }

    function handleAddPayee() {
        const trimmed = newPayeeName.trim();

        if (!trimmed) {
            return;
        }

        router.post(
            storePayee(ledger.id),
            { name: trimmed },
            {
                preserveScroll: true,
                onSuccess: async () => {
                    setNewPayeeName('');
                    setShowAddForm(false);
                    await refreshPayeesAfterMutation('Payee added');
                },
                onError: (errors) => {
                    toast.error(
                        typeof errors.name === 'string'
                            ? errors.name
                            : 'Failed to add payee',
                    );
                },
            },
        );
    }

    function handleStartEdit(payee: PayeeWithCount) {
        setEditingPayee(payee);
        setEditingName(payee.name);
    }

    function handleSubmitEdit() {
        if (!editingPayee) {
            return;
        }

        const trimmed = editingName.trim();

        if (!trimmed || trimmed === editingPayee.name) {
            setEditingPayee(null);

            return;
        }

        router.patch(
            updatePayee({ ledger: ledger.id, payee: editingPayee.id }),
            { name: trimmed },
            {
                preserveScroll: true,
                onSuccess: async () => {
                    setEditingPayee(null);
                    await refreshPayeesAfterMutation('Payee updated');
                },
                onError: (errors) => {
                    toast.error(
                        typeof errors.name === 'string'
                            ? errors.name
                            : 'Failed to update payee',
                    );
                },
            },
        );
    }

    function handleDelete() {
        if (!payeeToDelete) {
            return;
        }

        setIsDeleting(true);

        router.delete(
            destroyPayee({ ledger: ledger.id, payee: payeeToDelete.id }),
            {
                preserveScroll: true,
                onSuccess: async () => {
                    setPayeeToDelete(null);
                    await refreshPayeesAfterMutation('Payee deleted');
                },
                onError: () => {
                    setIsDeleting(false);
                    toast.error('Failed to delete payee');
                },
                onFinish: () => {
                    setIsDeleting(false);
                },
            },
        );
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`${ledger.name} payees`} />

            <div className="flex h-full flex-1 flex-col gap-4 p-4 md:p-6">
                <div className="flex justify-end">
                    <Button
                        className="w-full sm:w-auto"
                        onClick={() => {
                            setShowAddForm(true);
                            setNewPayeeName('');
                        }}
                    >
                        Add Payee
                    </Button>
                </div>

                {/* Search bar - full width on mobile */}
                <div className="relative">
                    <Search className="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                    <Input
                        placeholder="Search payees..."
                        value={localSearch}
                        onChange={(e) => handleSearch(e.target.value)}
                        className="pl-9"
                    />
                </div>

                {/* Inline add form */}
                {showAddForm && (
                    <div className="flex items-center gap-2">
                        <Input
                            autoFocus
                            placeholder="Payee name"
                            value={newPayeeName}
                            onChange={(e) => setNewPayeeName(e.target.value)}
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
                )}

                {payeesLoaderState.processing && hasLoadedPayees ? (
                    <p className="text-xs text-muted-foreground">
                        Refreshing payees...
                    </p>
                ) : null}

                {payeesLoaderState.processing && !hasLoadedPayees ? (
                    <PayeesLoadingSkeleton />
                ) : payeesError && payees.length === 0 ? (
                    <PayeesErrorState onRetry={() => void loadPayees(localSearch)} />
                ) : (
                    <PayeesContent
                        ledgerId={ledger.id}
                        payees={payees}
                        onEdit={handleStartEdit}
                        onDelete={setPayeeToDelete}
                    />
                )}
            </div>

            {/* Edit dialog */}
            <Dialog
                open={editingPayee !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setEditingPayee(null);
                    }
                }}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Edit payee</DialogTitle>
                    </DialogHeader>
                    <div className="py-4">
                        <Input
                            autoFocus
                            value={editingName}
                            onChange={(e) => setEditingName(e.target.value)}
                            onKeyDown={(e) => {
                                if (e.key === 'Enter') {
                                    handleSubmitEdit();
                                }
                            }}
                            placeholder="Payee name"
                        />
                    </div>
                    <DialogFooter>
                        <Button
                            variant="outline"
                            onClick={() => setEditingPayee(null)}
                        >
                            Cancel
                        </Button>
                        <Button onClick={handleSubmitEdit}>Save</Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

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
                                        {payeeToDelete.transactions_count}{' '}
                                        transaction
                                        {payeeToDelete.transactions_count !== 1
                                            ? 's'
                                            : ''}
                                        . The payee will be unlinked from those
                                        transactions.
                                    </>
                                )}
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button
                            variant="outline"
                            onClick={() => setPayeeToDelete(null)}
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
        </AppLayout>
    );
}
