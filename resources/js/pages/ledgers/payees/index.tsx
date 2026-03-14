import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import { toast } from 'sonner';
import { Users } from 'lucide-react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
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
    store,
    update,
} from '@/routes/ledgers/payees';
import type { BreadcrumbItem, Ledger, Payee } from '@/types';

type PayeeWithCount = Payee & { transactions_count: number };

export default function PayeesIndex({
    ledger,
    payees,
}: {
    ledger: Ledger;
    payees: PayeeWithCount[];
}) {
    const [editingId, setEditingId] = useState<number | null>(null);
    const [editingName, setEditingName] = useState('');
    const [payeeToDelete, setPayeeToDelete] = useState<PayeeWithCount | null>(
        null,
    );
    const [isDeleting, setIsDeleting] = useState(false);
    const [showAddForm, setShowAddForm] = useState(false);
    const [newPayeeName, setNewPayeeName] = useState('');

    const breadcrumbs: BreadcrumbItem[] = [
        { title: ledger.name, href: ledgerDashboard.url(ledger.id) },
        { title: 'Payees', href: payeesIndex.url(ledger.id) },
    ];

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

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`${ledger.name} payees`} />

            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <div className="flex items-center justify-between">
                    <Heading
                        title="Payees"
                        description="Manage payees for this ledger."
                    />

                    <Button
                        type="button"
                        onClick={() => {
                            setShowAddForm(true);
                            setNewPayeeName('');
                        }}
                    >
                        Add Payee
                    </Button>
                </div>

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
                        title="No payees yet"
                        description="Payees will appear here as you create transactions."
                    />
                ) : (
                    <Card>
                        <CardContent className="p-0">
                            <Table>
                                <TableHeader>
                                    <TableRow>
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
                                                    <button
                                                        type="button"
                                                        className="cursor-text rounded px-1 hover:bg-muted"
                                                        onClick={() =>
                                                            startEditing(payee)
                                                        }
                                                        title="Click to rename"
                                                    >
                                                        {payee.name}
                                                    </button>
                                                )}
                                            </TableCell>
                                            <TableCell className="text-muted-foreground">
                                                {payee.transactions_count}
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
        </AppLayout>
    );
}
