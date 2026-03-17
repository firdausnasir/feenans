import { Head, Link, router } from '@inertiajs/react';
import {
    ChevronDown,
    ChevronRight,
    MoreHorizontal,
    Receipt,
} from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import Heading from '@/components/heading';
import { PayBillDialog } from '@/components/pay-bill-dialog';
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
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { EmptyState } from '@/components/ui/empty-state';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import AppLayout from '@/layouts/app-layout';
import {
    formatAbsAmount,
    formatAmount,
    formatDate,
    parseDate,
} from '@/lib/format';
import { cn } from '@/lib/utils';
import { dashboard as ledgerDashboard } from '@/routes/ledgers';
import {
    create,
    destroy,
    edit as editRoute,
    index as billsIndex,
    toggle,
} from '@/routes/ledgers/bills';
import { index as transactionsIndex } from '@/routes/ledgers/transactions';
import type { Account, Bill, BreadcrumbItem, Ledger } from '@/types';

const COLUMN_COUNT = 9;

const ACTION_LABELS: Record<string, { pay: string; paid: string }> = {
    expense: { pay: 'Record Payment', paid: 'paid' },
    income: { pay: 'Record Income', paid: 'received' },
};

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

function getDueStatus(
    nextDueDate: string,
): 'overdue' | 'due-soon' | 'upcoming' {
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    const due = parseDate(nextDueDate);
    const diffDays = Math.floor(
        (due.getTime() - today.getTime()) / (1000 * 60 * 60 * 24),
    );

    if (diffDays < 0) {
        return 'overdue';
    }

    if (diffDays <= 3) {
        return 'due-soon';
    }

    return 'upcoming';
}

type DueStatus = ReturnType<typeof getDueStatus>;

const dueStatusStyles: Record<DueStatus, string> = {
    overdue: 'border-l-2 border-l-red-500',
    'due-soon': 'border-l-2 border-l-amber-500',
    upcoming: '',
};

const dueDateStyles: Record<DueStatus, string> = {
    overdue: 'text-red-500 font-medium',
    'due-soon': 'text-amber-500',
    upcoming: 'text-muted-foreground',
};

function BillRow({
    bill,
    ledgerId,
    onPay,
    onDelete,
    onToggle,
}: {
    bill: Bill;
    ledgerId: number;
    onPay: (bill: Bill) => void;
    onDelete: (bill: Bill) => void;
    onToggle: (bill: Bill) => void;
}) {
    const [expanded, setExpanded] = useState(false);
    const transactions = bill.transactions ?? [];
    const hasTransactions = transactions.length > 0;
    const dueStatus = bill.is_active
        ? getDueStatus(bill.next_due_date)
        : 'upcoming';

    return (
        <>
            <TableRow
                className={cn(
                    'group',
                    hasTransactions ? 'cursor-pointer' : undefined,
                    bill.is_active ? dueStatusStyles[dueStatus] : '',
                )}
                onClick={() => {
                    if (hasTransactions) {
                        setExpanded((prev) => !prev);
                    }
                }}
            >
                <TableCell className="font-medium">
                    <div className="flex items-center gap-1.5">
                        {hasTransactions ? (
                            expanded ? (
                                <ChevronDown className="size-4 shrink-0 text-muted-foreground" />
                            ) : (
                                <ChevronRight className="size-4 shrink-0 text-muted-foreground" />
                            )
                        ) : (
                            <span className="inline-block w-4 shrink-0" />
                        )}
                        {bill.name}
                    </div>
                </TableCell>
                <TableCell>{formatAmount(bill.amount)}</TableCell>
                <TableCell>
                    <Badge
                        variant="outline"
                        className={
                            bill.transaction_type === 'income'
                                ? 'border-green-200 text-green-700 dark:border-green-800 dark:text-green-400'
                                : 'border-red-200 text-red-700 dark:border-red-800 dark:text-red-400'
                        }
                    >
                        {bill.transaction_type === 'income'
                            ? 'Income'
                            : 'Expense'}
                    </Badge>
                </TableCell>
                <TableCell className="hidden text-muted-foreground md:table-cell">
                    {bill.account?.name ?? '-'}
                </TableCell>
                <TableCell className="hidden text-muted-foreground md:table-cell">
                    {recurrenceDescription(
                        bill.recurrence_type,
                        bill.recurrence_interval,
                    )}
                </TableCell>
                <TableCell className={dueDateStyles[dueStatus]}>
                    {formatDate(bill.next_due_date)}
                </TableCell>
                <TableCell className="hidden lg:table-cell">
                    <Badge variant={bill.is_active ? 'default' : 'secondary'}>
                        {bill.is_active ? 'Active' : 'Inactive'}
                    </Badge>
                </TableCell>
                <TableCell>
                    <Tooltip>
                        <TooltipTrigger asChild>
                            <Badge
                                variant={
                                    bill.auto_create ? 'outline' : 'secondary'
                                }
                            >
                                {bill.auto_create ? 'Auto' : 'Manual'}
                            </Badge>
                        </TooltipTrigger>
                        <TooltipContent>
                            {bill.auto_create
                                ? 'Transactions are created automatically when due'
                                : 'You must manually pay this bill each cycle'}
                        </TooltipContent>
                    </Tooltip>
                </TableCell>
                <TableCell>
                    <div
                        className="flex items-center gap-2"
                        onClick={(e) => e.stopPropagation()}
                    >
                        {bill.is_active && (
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                className="h-auto px-2 py-0.5 text-xs"
                                onClick={() => onPay(bill)}
                            >
                                {ACTION_LABELS[bill.transaction_type]?.pay ??
                                    'Record Payment'}
                            </Button>
                        )}
                        <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    className="h-auto px-1.5 py-0.5 opacity-0 transition-opacity group-hover:opacity-100 data-[state=open]:opacity-100"
                                >
                                    <MoreHorizontal className="size-4" />
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end">
                                <DropdownMenuItem asChild>
                                    <Link
                                        href={editRoute.url({
                                            ledger: ledgerId,
                                            bill: bill.id,
                                        })}
                                    >
                                        Edit
                                    </Link>
                                </DropdownMenuItem>
                                <DropdownMenuItem
                                    onClick={() => onToggle(bill)}
                                >
                                    {bill.is_active ? 'Deactivate' : 'Activate'}
                                </DropdownMenuItem>
                                <DropdownMenuItem
                                    className="text-destructive focus:text-destructive"
                                    onClick={() => onDelete(bill)}
                                >
                                    Delete
                                </DropdownMenuItem>
                            </DropdownMenuContent>
                        </DropdownMenu>
                    </div>
                </TableCell>
            </TableRow>
            {expanded && hasTransactions && (
                <TableRow className="bg-muted/50 hover:bg-muted/50">
                    <TableCell colSpan={COLUMN_COUNT} className="p-0">
                        <div className="px-10 py-3">
                            <p className="mb-2 text-xs font-medium tracking-wide text-muted-foreground uppercase">
                                Payment History (last {transactions.length})
                            </p>
                            <div className="divide-y sm:hidden">
                                {transactions.map((txn) => (
                                    <div
                                        key={txn.id}
                                        className="flex items-center justify-between py-2"
                                    >
                                        <span className="text-xs text-muted-foreground">
                                            {formatDate(txn.transaction_date)}
                                        </span>
                                        <div className="text-right">
                                            <span className="text-xs font-medium tabular-nums">
                                                {formatAbsAmount(txn.amount)}
                                            </span>
                                            <span className="ml-2 text-xs text-muted-foreground">
                                                {txn.account?.name ?? '-'}
                                            </span>
                                        </div>
                                    </div>
                                ))}
                            </div>
                            <Table className="hidden sm:table">
                                <TableHeader>
                                    <TableRow className="hover:bg-transparent">
                                        <TableHead className="h-8 text-xs">
                                            Date
                                        </TableHead>
                                        <TableHead className="h-8 text-xs">
                                            Amount
                                        </TableHead>
                                        <TableHead className="h-8 text-xs">
                                            Account
                                        </TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {transactions.map((txn) => (
                                        <TableRow
                                            key={txn.id}
                                            className="hover:bg-transparent"
                                        >
                                            <TableCell className="py-1.5 text-sm">
                                                {formatDate(
                                                    txn.transaction_date,
                                                )}
                                            </TableCell>
                                            <TableCell className="py-1.5 text-sm">
                                                {formatAbsAmount(txn.amount)}
                                            </TableCell>
                                            <TableCell className="py-1.5 text-sm text-muted-foreground">
                                                {txn.account?.name ?? '-'}
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                            <div className="pt-2 text-center">
                                <Link
                                    href={transactionsIndex.url(ledgerId, {
                                        query: { bill_id: String(bill.id) },
                                    })}
                                    className="text-xs font-medium text-primary hover:underline"
                                >
                                    View all transactions
                                </Link>
                            </div>
                        </div>
                    </TableCell>
                </TableRow>
            )}
        </>
    );
}

export default function BillsIndex({
    ledger,
    bills,
    accounts,
}: {
    ledger: Ledger;
    bills: Bill[];
    accounts: Account[];
}) {
    const [billToDelete, setBillToDelete] = useState<Bill | null>(null);
    const [billToPay, setBillToPay] = useState<Bill | null>(null);

    const breadcrumbs: BreadcrumbItem[] = [
        { title: ledger.name, href: ledgerDashboard.url(ledger.id) },
        {
            title: 'Recurring Transactions',
            href: billsIndex.url(ledger.id),
        },
    ];

    function handleToggle(bill: Bill) {
        router.patch(
            toggle.url({ ledger: ledger.id, bill: bill.id }),
            {},
            {
                preserveScroll: true,
                onSuccess: () => {
                    toast.success(
                        bill.is_active
                            ? 'Recurring transaction deactivated'
                            : 'Recurring transaction activated',
                    );
                },
            },
        );
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
                    toast.success('Recurring transaction deleted');
                },
            },
        );

        setBillToDelete(null);
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Recurring Transactions — ${ledger.name}`} />

            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6 lg:p-8">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <Heading
                        title="Recurring Transactions"
                        description="Manage recurring expenses and income for this ledger."
                    />

                    <Button className="w-full sm:w-auto" asChild>
                        <Link href={create.url(ledger.id)}>
                            New Recurring Transaction
                        </Link>
                    </Button>
                </div>

                {bills.length === 0 ? (
                    <EmptyState
                        icon={<Receipt className="size-6" />}
                        title="No recurring transactions yet"
                        description="Set up recurring transactions to track regular expenses and income."
                        action={{
                            label: 'New recurring transaction',
                            href: create.url(ledger.id),
                        }}
                    />
                ) : (
                    <Card>
                        <CardContent className="p-0">
                            <div className="divide-y px-4 sm:hidden">
                                {bills.map((bill) => (
                                    <div
                                        key={bill.id}
                                        className="space-y-2 py-3"
                                    >
                                        <div className="flex items-center justify-between gap-3">
                                            <div className="min-w-0 flex-1">
                                                <p className="truncate text-sm font-medium">
                                                    {bill.name}
                                                </p>
                                                <p className="text-xs text-muted-foreground">
                                                    {bill.account?.name ?? '-'}{' '}
                                                    ·{' '}
                                                    {recurrenceDescription(
                                                        bill.recurrence_type,
                                                        bill.recurrence_interval,
                                                    )}
                                                </p>
                                            </div>
                                            <span
                                                className={`shrink-0 text-sm font-semibold tabular-nums ${bill.transaction_type === 'expense' ? 'text-red-600 dark:text-red-400' : 'text-green-600 dark:text-green-400'}`}
                                            >
                                                {formatAmount(bill.amount)}
                                            </span>
                                        </div>
                                        <div className="flex items-center justify-between gap-2">
                                            <div className="flex items-center gap-2">
                                                <Badge
                                                    variant={
                                                        bill.is_active
                                                            ? 'default'
                                                            : 'secondary'
                                                    }
                                                    className="text-[10px]"
                                                >
                                                    {bill.is_active
                                                        ? 'Active'
                                                        : 'Inactive'}
                                                </Badge>
                                                <Badge
                                                    variant="outline"
                                                    className="text-[10px]"
                                                >
                                                    {bill.transaction_type ===
                                                    'expense'
                                                        ? 'Expense'
                                                        : 'Income'}
                                                </Badge>
                                                {bill.auto_create && (
                                                    <Badge
                                                        variant="outline"
                                                        className="text-[10px]"
                                                    >
                                                        Auto
                                                    </Badge>
                                                )}
                                            </div>
                                            <span className="text-xs text-muted-foreground">
                                                Due:{' '}
                                                {formatDate(bill.next_due_date)}
                                            </span>
                                        </div>
                                        <div className="flex items-center gap-1 pt-1">
                                            {bill.is_active && (
                                                <Button
                                                    variant="outline"
                                                    size="sm"
                                                    className="h-7 text-xs"
                                                    onClick={() =>
                                                        setBillToPay(bill)
                                                    }
                                                >
                                                    {ACTION_LABELS[
                                                        bill.transaction_type
                                                    ]?.pay ?? 'Record Payment'}
                                                </Button>
                                            )}
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                className="h-7 text-xs"
                                                asChild
                                            >
                                                <Link
                                                    href={editRoute.url({
                                                        ledger: ledger.id,
                                                        bill: bill.id,
                                                    })}
                                                >
                                                    Edit
                                                </Link>
                                            </Button>
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                className="h-7 text-xs"
                                                onClick={() =>
                                                    handleToggle(bill)
                                                }
                                            >
                                                {bill.is_active
                                                    ? 'Deactivate'
                                                    : 'Activate'}
                                            </Button>
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                className="h-7 text-xs text-destructive hover:text-destructive"
                                                onClick={() =>
                                                    setBillToDelete(bill)
                                                }
                                            >
                                                Delete
                                            </Button>
                                        </div>
                                    </div>
                                ))}
                            </div>
                            <Table className="hidden sm:table">
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Name</TableHead>
                                        <TableHead>Amount</TableHead>
                                        <TableHead className="hidden lg:table-cell">
                                            Type
                                        </TableHead>
                                        <TableHead className="hidden md:table-cell">
                                            Account
                                        </TableHead>
                                        <TableHead className="hidden md:table-cell">
                                            Recurrence
                                        </TableHead>
                                        <TableHead>Next Due</TableHead>
                                        <TableHead className="hidden lg:table-cell">
                                            Status
                                        </TableHead>
                                        <TableHead className="hidden sm:table-cell">
                                            Auto
                                        </TableHead>
                                        <TableHead className="sr-only">
                                            Actions
                                        </TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {bills.map((bill) => (
                                        <BillRow
                                            key={bill.id}
                                            bill={bill}
                                            ledgerId={ledger.id}
                                            onPay={setBillToPay}
                                            onDelete={setBillToDelete}
                                            onToggle={handleToggle}
                                        />
                                    ))}
                                </TableBody>
                            </Table>
                        </CardContent>
                    </Card>
                )}
            </div>

            <PayBillDialog
                bill={billToPay}
                ledgerId={ledger.id}
                accounts={accounts}
                onClose={() => setBillToPay(null)}
            />

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
                        <DialogTitle>Delete recurring transaction</DialogTitle>
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
