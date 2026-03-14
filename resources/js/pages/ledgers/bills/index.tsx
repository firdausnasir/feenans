import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { toast } from 'sonner';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import AppLayout from '@/layouts/app-layout';
import { formatAmount, formatDate } from '@/lib/format';
import { dashboard as ledgerDashboard } from '@/routes/ledgers';
import {
    create,
    destroy,
    edit as editRoute,
    index as billsIndex,
    pay,
    toggle,
} from '@/routes/ledgers/bills';
import type { Bill, BreadcrumbItem, Ledger } from '@/types';

function recurrenceDescription(
    type: Bill['recurrence_type'],
    interval: number,
): string {
    if (type === 'custom') {
        return 'Custom';
    }

    const labels: Record<Bill['recurrence_type'], [string, string]> = {
        daily: ['Daily', 'Every {n} days'],
        weekly: ['Weekly', 'Every {n} weeks'],
        monthly: ['Monthly', 'Every {n} months'],
        yearly: ['Yearly', 'Every {n} years'],
        custom: ['Custom', 'Custom'],
    };

    const [singular, plural] = labels[type];

    if (interval === 1) {
        return singular;
    }

    return plural.replace('{n}', String(interval));
}

export default function BillsIndex({
    ledger,
    bills,
}: {
    ledger: Ledger;
    bills: Bill[];
}) {
    const [billToDelete, setBillToDelete] = useState<Bill | null>(null);
    const [billToPay, setBillToPay] = useState<Bill | null>(null);

    const breadcrumbs: BreadcrumbItem[] = [
        { title: ledger.name, href: ledgerDashboard.url(ledger.id) },
        { title: 'Bills', href: billsIndex.url(ledger.id) },
    ];

    function handleToggle(bill: Bill) {
        router.patch(
            toggle.url({ ledger: ledger.id, bill: bill.id }),
            {},
            {
                preserveScroll: true,
                onSuccess: () => {
                    toast.success(
                        bill.is_active ? 'Bill deactivated' : 'Bill activated',
                    );
                },
            },
        );
    }

    function handlePay() {
        if (!billToPay) {
            return;
        }

        router.post(
            pay.url({ ledger: ledger.id, bill: billToPay.id }),
            {},
            {
                preserveScroll: true,
                onSuccess: () => {
                    toast.success('Bill paid');
                },
            },
        );

        setBillToPay(null);
    }

    function handleDelete() {
        if (!billToDelete) {
            return;
        }

        router.delete(
            destroy.url({ ledger: ledger.id, bill: billToDelete.id }),
            {
                preserveScroll: true,
                onSuccess: () => {
                    toast.success('Bill deleted');
                },
            },
        );

        setBillToDelete(null);
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`${ledger.name} bills`} />

            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <div className="flex items-center justify-between">
                    <Heading
                        title="Bills"
                        description="Manage recurring bills for this ledger."
                    />

                    <Button asChild>
                        <Link href={create.url(ledger.id)}>New Bill</Link>
                    </Button>
                </div>

                {bills.length === 0 ? (
                    <p className="text-muted-foreground">No bills yet.</p>
                ) : (
                    <Card>
                        <CardContent className="p-0">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Name</TableHead>
                                        <TableHead>Amount</TableHead>
                                        <TableHead>Recurrence</TableHead>
                                        <TableHead>Next Due</TableHead>
                                        <TableHead>Status</TableHead>
                                        <TableHead>Auto</TableHead>
                                        <TableHead className="sr-only">
                                            Actions
                                        </TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {bills.map((bill) => (
                                        <TableRow key={bill.id}>
                                            <TableCell className="font-medium">
                                                {bill.name}
                                            </TableCell>
                                            <TableCell>
                                                {formatAmount(bill.amount)}
                                            </TableCell>
                                            <TableCell className="text-muted-foreground">
                                                {recurrenceDescription(
                                                    bill.recurrence_type,
                                                    bill.recurrence_interval,
                                                )}
                                            </TableCell>
                                            <TableCell className="text-muted-foreground">
                                                {formatDate(bill.next_due_date)}
                                            </TableCell>
                                            <TableCell>
                                                <Badge
                                                    variant={
                                                        bill.is_active
                                                            ? 'default'
                                                            : 'secondary'
                                                    }
                                                >
                                                    {bill.is_active
                                                        ? 'Active'
                                                        : 'Inactive'}
                                                </Badge>
                                            </TableCell>
                                            <TableCell>
                                                <Badge
                                                    variant={
                                                        bill.auto_create
                                                            ? 'outline'
                                                            : 'secondary'
                                                    }
                                                >
                                                    {bill.auto_create
                                                        ? 'Auto'
                                                        : 'Manual'}
                                                </Badge>
                                            </TableCell>
                                            <TableCell>
                                                <div className="flex items-center gap-2">
                                                    {bill.is_active && (
                                                        <Button
                                                            type="button"
                                                            variant="outline"
                                                            size="sm"
                                                            className="h-auto px-2 py-0.5 text-xs"
                                                            onClick={() =>
                                                                setBillToPay(
                                                                    bill,
                                                                )
                                                            }
                                                        >
                                                            Pay
                                                        </Button>
                                                    )}
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        className="h-auto px-2 py-0.5 text-xs"
                                                        asChild
                                                    >
                                                        <Link
                                                            href={editRoute.url(
                                                                {
                                                                    ledger: ledger.id,
                                                                    bill: bill.id,
                                                                },
                                                            )}
                                                        >
                                                            Edit
                                                        </Link>
                                                    </Button>
                                                    <Button
                                                        type="button"
                                                        variant="ghost"
                                                        size="sm"
                                                        className="h-auto px-2 py-0.5 text-xs"
                                                        onClick={() =>
                                                            handleToggle(bill)
                                                        }
                                                    >
                                                        {bill.is_active
                                                            ? 'Deactivate'
                                                            : 'Activate'}
                                                    </Button>
                                                    <Button
                                                        type="button"
                                                        variant="ghost"
                                                        size="sm"
                                                        className="h-auto px-2 py-0.5 text-xs text-destructive hover:text-destructive"
                                                        onClick={() =>
                                                            setBillToDelete(
                                                                bill,
                                                            )
                                                        }
                                                    >
                                                        Delete
                                                    </Button>
                                                </div>
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </CardContent>
                    </Card>
                )}
            </div>

            {/* Pay confirmation dialog */}
            <Dialog
                open={billToPay !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setBillToPay(null);
                    }
                }}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Pay bill</DialogTitle>
                        <DialogDescription>
                            This will create an expense transaction for{' '}
                            <strong>{billToPay?.name}</strong> (
                            {formatAmount(billToPay?.amount ?? 0)}) and advance
                            the next due date.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button
                            variant="outline"
                            onClick={() => setBillToPay(null)}
                        >
                            Cancel
                        </Button>
                        <Button onClick={handlePay}>Confirm Payment</Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            {/* Delete confirmation dialog */}
            <Dialog
                open={billToDelete !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setBillToDelete(null);
                    }
                }}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Delete bill</DialogTitle>
                        <DialogDescription>
                            Are you sure you want to delete{' '}
                            <strong>{billToDelete?.name}</strong>? This action
                            cannot be undone.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button
                            variant="outline"
                            onClick={() => setBillToDelete(null)}
                        >
                            Cancel
                        </Button>
                        <Button variant="destructive" onClick={handleDelete}>
                            Delete
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}
