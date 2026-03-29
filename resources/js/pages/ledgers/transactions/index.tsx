import { Deferred, Head, router, usePage } from '@inertiajs/react';
import {
    MoreVertical,
    Paperclip,
    Receipt,
    Search,
    SlidersHorizontal,
    X,
} from 'lucide-react';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { toast } from 'sonner';
import type { DuplicateData } from '@/components/add-transaction-modal';
import { AddTransactionModal } from '@/components/add-transaction-modal';
import Heading from '@/components/heading';
import { SearchableSelect } from '@/components/searchable-select';
import { TagPill } from '@/components/tag-pill';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { DatePicker } from '@/components/ui/date-picker';
import { DateRangePicker } from '@/components/ui/date-range-picker';
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
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { EmptyState } from '@/components/ui/empty-state';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetFooter,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { Skeleton } from '@/components/ui/skeleton';
import { Switch } from '@/components/ui/switch';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { usePrivacyMode } from '@/contexts/privacy-mode-context';
import { useAttachments } from '@/hooks/use-attachments';
import AppLayout from '@/layouts/app-layout';
import { formatAbsAmount, formatDate } from '@/lib/format';
import { cn } from '@/lib/utils';
import { dashboard as ledgerDashboard } from '@/routes/ledgers';
import {
    bulkDestroy as bulkDestroyRoute,
    bulkUpdate as bulkUpdateRoute,
    destroy as destroyRoute,
    exportMethod as exportTransactions,
    index as transactionsIndex,
    update as updateRoute,
} from '@/routes/ledgers/transactions';
import attachmentRoutes from '@/routes/ledgers/transactions/attachments';
import type {
    Account,
    Attachment,
    BreadcrumbItem,
    Category,
    Ledger,
    Pagination,
    Payee,
    Tag,
    Transaction,
    TransactionSplit,
} from '@/types';
import { MobileTransactionList } from './mobile-transaction-list';
import {
    buildQueryParams,
    deriveSelectionState,
    EMPTY_FILTERS,
} from './query-params';
import type { Filters } from './query-params';

// ─── Types ───────────────────────────────────────────────────────────────────

type TransactionPageProps = {
    filters: Filters;
    accounts: Account[];
    categories: Category[];
    payees: Payee[];
    tags: Tag[];
    transactions?: Pagination<Transaction>;
};

type EditFormData = {
    transaction_type: 'expense' | 'income' | 'transfer';
    transaction_date: string;
    account_id: string;
    to_account_id: string;
    category_id: string;
    payee_id: string;
    new_payee_name: string;
    amount: string;
    description: string;
    notes: string;
    tag_ids: number[];
};

type EditableSplit = {
    id: string;
    amount: string;
    category_id: string;
    payee_id: string;
    description: string;
};

type FilterChip = {
    key: string;
    label: string;
    onRemove: (filters: Filters) => Filters;
};

// ─── Helpers ─────────────────────────────────────────────────────────────────

function amountColor(value: number): string {
    return value < 0 ? 'text-red-500 dark:text-red-400' : 'text-foreground';
}

function buildExportUrl(ledgerId: number, filters: Filters): string {
    const params = new URLSearchParams();

    for (const [key, val] of Object.entries(buildQueryParams(filters))) {
        if (Array.isArray(val)) {
            for (const v of val) {
                params.append(`${key}[]`, v);
            }
        } else if (val != null && val !== '') {
            params.append(key, val);
        }
    }

    const qs = params.toString();

    return qs
        ? `${exportTransactions.url(ledgerId)}?${qs}`
        : exportTransactions.url(ledgerId);
}

function createEditableSplit(
    split?: TransactionSplit,
    index = 0,
): EditableSplit {
    return {
        id: split ? String(split.id) : `new-${index}`,
        amount: split?.amount ?? '',
        category_id: split?.category_id ? String(split.category_id) : '',
        payee_id: split?.payee_id ? String(split.payee_id) : '',
        description: split?.description ?? '',
    };
}

// Build grouped category structure: parents with their children (matches add-transaction-modal)
function useGroupedCategories(
    categories: Category[],
    mode: EditFormData['transaction_type'],
) {
    return useMemo(() => {
        const filtered = categories.filter((c) => c.transaction_type === mode);

        // Separate parents and children
        const parents = filtered.filter((c) => c.parent_id === null);
        const childrenMap = new Map<number, Category[]>();

        filtered
            .filter((c) => c.parent_id !== null)
            .forEach((c) => {
                const pid = c.parent_id!;

                if (!childrenMap.has(pid)) {
                    childrenMap.set(pid, []);
                }

                childrenMap.get(pid)!.push(c);
            });

        return parents.map((parent) => ({
            parent,
            children: childrenMap.get(parent.id) ?? [],
        }));
    }, [categories, mode]);
}

// Build SearchableSelect options for categories (matches add-transaction-modal)
function useCategoryOptions(
    categories: Category[],
    mode: EditFormData['transaction_type'],
) {
    const grouped = useGroupedCategories(categories, mode);

    return useMemo(() => {
        return grouped.flatMap(({ parent, children }) => {
            const items = [
                {
                    value: String(parent.id),
                    label:
                        children.length > 0
                            ? `${parent.name} (general)`
                            : parent.name,
                    group: children.length > 0 ? parent.name : undefined,
                    color: parent.color,
                },
            ];

            children.forEach((child) => {
                items.push({
                    value: String(child.id),
                    label: child.name,
                    group: parent.name,
                    color: child.color,
                });
            });

            return items;
        });
    }, [grouped]);
}

// ─── EditTransactionModal ────────────────────────────────────────────────────

function EditTransactionModal({
    ledger,
    transaction,
    accounts,
    categories,
    payees,
    tags,
    onClose,
}: {
    ledger: Ledger;
    transaction: Transaction;
    accounts: Account[];
    categories: Category[];
    payees: Payee[];
    tags: Tag[];
    onClose: () => void;
}) {
    const isTransfer = transaction.transfer_pair_id !== null;
    const isIncomingSide =
        isTransfer && parseFloat(transaction.amount || '0') > 0;

    const [form, setForm] = useState<EditFormData>({
        transaction_type: transaction.transaction_type as
            | 'expense'
            | 'income'
            | 'transfer',
        transaction_date: transaction.transaction_date.slice(0, 10),
        account_id:
            isIncomingSide && transaction.transfer_pair
                ? String(transaction.transfer_pair.account_id)
                : String(transaction.account_id),
        to_account_id: isIncomingSide
            ? String(transaction.account_id)
            : transaction.transfer_pair
              ? String(transaction.transfer_pair.account_id)
              : '',
        category_id: transaction.category_id
            ? String(transaction.category_id)
            : '',
        payee_id: transaction.payee_id ? String(transaction.payee_id) : '',
        new_payee_name: '',
        amount: String(Math.abs(parseFloat(transaction.amount || '0'))),
        description: transaction.description ?? '',
        notes: transaction.notes ?? '',
        tag_ids: (transaction.tags ?? []).map((t) => t.id),
    });
    const [showDeleteConfirm, setShowDeleteConfirm] = useState(false);
    const [processing, setProcessing] = useState(false);
    const [deleting, setDeleting] = useState(false);
    const [attachments, setAttachments] = useState<Attachment[]>(
        transaction.attachments ?? [],
    );
    const [uploadingAttachments, setUploadingAttachments] = useState(false);
    const [attachmentError, setAttachmentError] = useState<string | null>(null);
    const attachmentInputRef = useRef<HTMLInputElement>(null);
    const [isSplitTransaction, setIsSplitTransaction] = useState(
        (transaction.splits ?? []).length > 0,
    );
    const [splits, setSplits] = useState<EditableSplit[]>(
        (transaction.splits ?? []).length > 0
            ? (transaction.splits ?? []).map((split, idx) =>
                  createEditableSplit(split, idx),
              )
            : [
                  createEditableSplit(undefined, 0),
                  createEditableSplit(undefined, 1),
              ],
    );

    const splitOptions = useCategoryOptions(categories, form.transaction_type);
    const splitTotal = splits.reduce(
        (total, split) => total + (Number(split.amount || 0) || 0),
        0,
    );

    function uploadFiles(files: FileList | null) {
        if (!files || files.length === 0) {
            return;
        }

        setUploadingAttachments(true);
        setAttachmentError(null);

        router.post(
            attachmentRoutes.store.url({
                ledger: ledger.id,
                transaction: transaction.id,
            }),
            {
                attachments: Array.from(files),
            },
            {
                forceFormData: true,
                only: ['flash'],
                preserveState: true,
                preserveScroll: true,
                onSuccess: ({ props }) => {
                    const uploads = props.flash?.attachment_uploads ?? [];

                    if (uploads.length > 0) {
                        setAttachments((current) => [...current, ...uploads]);
                    }

                    setAttachmentError(null);

                    if (attachmentInputRef.current) {
                        attachmentInputRef.current.value = '';
                    }
                },
                onError: (errors) => {
                    const firstError =
                        errors.attachments ??
                        errors['attachments.0'] ??
                        Object.values(errors)[0];

                    setAttachmentError(
                        typeof firstError === 'string'
                            ? firstError
                            : 'Attachment upload failed.',
                    );
                },
                onFinish: () => setUploadingAttachments(false),
            },
        );
    }

    function deleteAttachment(attachmentId: number) {
        router.delete(
            attachmentRoutes.destroy.url({
                ledger: ledger.id,
                transaction: transaction.id,
                attachment: attachmentId,
            }),
            {
                only: ['flash'],
                preserveState: true,
                preserveScroll: true,
                onSuccess: ({ props }) => {
                    const deletedAttachmentId =
                        props.flash?.deleted_attachment_id;

                    if (deletedAttachmentId) {
                        setAttachments((current) =>
                            current.filter(
                                (attachment) =>
                                    attachment.id !== deletedAttachmentId,
                            ),
                        );
                    }

                    setAttachmentError(null);
                },
            },
        );
    }

    function addSplit() {
        setSplits((current) => [
            ...current,
            createEditableSplit(undefined, current.length),
        ]);
    }

    function updateSplit(
        id: string,
        key: keyof Omit<EditableSplit, 'id'>,
        value: string,
    ) {
        setSplits((current) =>
            current.map((split) =>
                split.id === id ? { ...split, [key]: value } : split,
            ),
        );
    }

    function removeSplit(id: string) {
        setSplits((current) => current.filter((split) => split.id !== id));
    }

    function handleInputChange(
        e: React.ChangeEvent<HTMLInputElement | HTMLTextAreaElement>,
    ) {
        const { name, value } = e.target;
        setForm((prev) => ({ ...prev, [name]: value }));
    }

    function handleSubmit(e: React.FormEvent) {
        e.preventDefault();
        setProcessing(true);

        const body = {
            transaction_type: form.transaction_type,
            transaction_date: form.transaction_date,
            account_id: form.account_id,
            ...(form.transaction_type === 'transfer'
                ? { to_account_id: form.to_account_id }
                : {
                      category_id: form.category_id || null,
                      payee_id: form.payee_id || null,
                      new_payee_name: form.new_payee_name || null,
                      splits: isSplitTransaction
                          ? splits.map((split) => ({
                                amount: split.amount || null,
                                category_id: split.category_id || null,
                                payee_id: split.payee_id || null,
                                description: split.description || null,
                            }))
                          : null,
                  }),
            amount: form.amount,
            description: form.description || null,
            notes: form.notes || null,
            tag_ids: form.tag_ids,
        };

        router.put(
            updateRoute.url({ ledger: ledger.id, transaction: transaction.id }),
            body,
            {
                preserveScroll: true,
                onSuccess: () => {
                    toast.success('Transaction updated');
                    onClose();
                },
                onError: (errors) => {
                    const firstError = Object.values(errors)[0];

                    if (firstError) {
                        toast.error(
                            typeof firstError === 'string'
                                ? firstError
                                : 'Failed to update transaction',
                        );
                    }
                },
                onFinish: () => setProcessing(false),
            },
        );
    }

    function handleDelete() {
        setDeleting(true);

        router.delete(
            destroyRoute.url({
                ledger: ledger.id,
                transaction: transaction.id,
            }),
            {
                preserveScroll: true,
                onSuccess: () => {
                    toast.success('Transaction deleted');
                    onClose();
                },
                onError: () => {
                    toast.error('Failed to delete transaction');
                },
                onFinish: () => setDeleting(false),
            },
        );
    }

    return (
        <Dialog open modal onOpenChange={(open) => !open && onClose()}>
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>Edit Transaction</DialogTitle>
                    <DialogDescription>
                        Update the details of this transaction.
                    </DialogDescription>
                </DialogHeader>

                <form onSubmit={handleSubmit} className="space-y-6">
                    {/* Transaction type selector - matches add-transaction-modal */}
                    <div className="grid grid-cols-3 gap-1">
                        {(['expense', 'income', 'transfer'] as const).map(
                            (type) => (
                                <Button
                                    key={type}
                                    type="button"
                                    variant={
                                        form.transaction_type === type
                                            ? 'default'
                                            : 'outline'
                                    }
                                    className="capitalize"
                                    onClick={() =>
                                        setForm((prev) => ({
                                            ...prev,
                                            transaction_type: type,
                                            category_id:
                                                type === 'transfer'
                                                    ? ''
                                                    : prev.category_id,
                                            payee_id:
                                                type === 'transfer'
                                                    ? ''
                                                    : prev.payee_id,
                                            to_account_id:
                                                type === 'transfer'
                                                    ? prev.to_account_id ||
                                                      (accounts.length > 1
                                                          ? String(
                                                                accounts.find(
                                                                    (a) =>
                                                                        String(
                                                                            a.id,
                                                                        ) !==
                                                                        prev.account_id,
                                                                )?.id ?? '',
                                                            )
                                                          : '')
                                                    : prev.to_account_id,
                                        }))
                                    }
                                >
                                    {type}
                                </Button>
                            ),
                        )}
                    </div>

                    {/* Amount with currency prefix - matches add-transaction-modal */}
                    <div className="grid gap-2">
                        <Label htmlFor="edit-amount">Amount</Label>
                        <div className="relative">
                            <span className="pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-sm text-muted-foreground">
                                {ledger.currency_code}
                            </span>
                            <Input
                                id="edit-amount"
                                name="amount"
                                type="number"
                                inputMode="decimal"
                                step="0.01"
                                min="0.01"
                                required
                                className="pl-14"
                                value={form.amount}
                                onChange={handleInputChange}
                            />
                        </div>
                    </div>

                    {/* Account and Date row - matches add-transaction-modal */}
                    <div className="grid gap-2 sm:grid-cols-2">
                        <div className="grid gap-2">
                            <Label>Account</Label>
                            <SearchableSelect
                                options={accounts.map((account) => ({
                                    value: String(account.id),
                                    label: account.name,
                                    color: account.color,
                                }))}
                                value={form.account_id}
                                onValueChange={(value) =>
                                    setForm((prev) => ({
                                        ...prev,
                                        account_id: value ?? '',
                                    }))
                                }
                                placeholder="Select account"
                                searchPlaceholder="Search accounts..."
                            />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="edit-date">Date</Label>
                            <DatePicker
                                id="edit-date"
                                name="transaction_date"
                                value={form.transaction_date}
                                onChange={(date) =>
                                    setForm((prev) => ({
                                        ...prev,
                                        transaction_date: date,
                                    }))
                                }
                            />
                        </div>
                    </div>

                    {/* Transfer: Destination Account */}
                    {form.transaction_type === 'transfer' && (
                        <div className="grid gap-2">
                            <Label>Destination account</Label>
                            <SearchableSelect
                                options={accounts
                                    .filter(
                                        (a) => String(a.id) !== form.account_id,
                                    )
                                    .map((account) => ({
                                        value: String(account.id),
                                        label: account.name,
                                        color: account.color,
                                    }))}
                                value={form.to_account_id || null}
                                onValueChange={(value) =>
                                    setForm((prev) => ({
                                        ...prev,
                                        to_account_id: value ?? '',
                                    }))
                                }
                                placeholder="Select destination"
                                searchPlaceholder="Search accounts..."
                            />
                        </div>
                    )}

                    {/* Non-transfer fields: splits, category, payee */}
                    {form.transaction_type !== 'transfer' && (
                        <div className="space-y-4">
                            <div className="flex items-center justify-between rounded-lg border border-dashed border-border px-4 py-3">
                                <div>
                                    <Label htmlFor={`split-${transaction.id}`}>
                                        Split transaction
                                    </Label>
                                    <p className="text-sm text-muted-foreground">
                                        Break this transaction into multiple
                                        category lines.
                                    </p>
                                </div>
                                <Switch
                                    id={`split-${transaction.id}`}
                                    checked={isSplitTransaction}
                                    onCheckedChange={setIsSplitTransaction}
                                />
                            </div>

                            {!isSplitTransaction && (
                                <div className="grid gap-2 sm:grid-cols-2">
                                    {/* Category */}
                                    <div className="grid gap-2">
                                        <Label>Category</Label>
                                        <SearchableSelect
                                            options={splitOptions}
                                            value={form.category_id || null}
                                            onValueChange={(value) =>
                                                setForm((prev) => ({
                                                    ...prev,
                                                    category_id: value ?? '',
                                                }))
                                            }
                                            placeholder="No category"
                                            searchPlaceholder="Search categories..."
                                            allOption="No category"
                                        />
                                    </div>

                                    {/* Payee */}
                                    <div className="grid gap-2">
                                        <Label>Payee</Label>
                                        <SearchableSelect
                                            options={payees.map((p) => ({
                                                value: String(p.id),
                                                label: p.name,
                                            }))}
                                            value={
                                                form.payee_id ||
                                                (form.new_payee_name
                                                    ? `new:${form.new_payee_name}`
                                                    : null)
                                            }
                                            onValueChange={(value) =>
                                                setForm((prev) => ({
                                                    ...prev,
                                                    payee_id: value ?? '',
                                                    new_payee_name: '',
                                                }))
                                            }
                                            placeholder="No payee"
                                            searchPlaceholder="Search payees..."
                                            allOption="No payee"
                                            creatable
                                            onCreate={(name) =>
                                                setForm((prev) => ({
                                                    ...prev,
                                                    payee_id: '',
                                                    new_payee_name: name,
                                                }))
                                            }
                                            createLabel={
                                                form.new_payee_name
                                                    ? `${form.new_payee_name} (new)`
                                                    : undefined
                                            }
                                        />
                                    </div>
                                </div>
                            )}

                            {isSplitTransaction && (
                                <div className="space-y-3 rounded-xl border border-border bg-muted/30 p-4">
                                    <div className="flex items-center justify-between">
                                        <div>
                                            <Label>Split lines</Label>
                                            <p className="text-sm text-muted-foreground">
                                                Total allocated:{' '}
                                                {splitTotal.toFixed(2)}
                                            </p>
                                        </div>
                                        <Button
                                            type="button"
                                            size="sm"
                                            variant="outline"
                                            onClick={addSplit}
                                        >
                                            Add split
                                        </Button>
                                    </div>

                                    <div className="space-y-3">
                                        {splits.map((split, index) => (
                                            <div
                                                key={split.id}
                                                className="space-y-3 rounded-lg border p-3"
                                            >
                                                <div className="flex items-center justify-between">
                                                    <span className="text-sm font-medium">
                                                        Split {index + 1}
                                                    </span>
                                                    <button
                                                        type="button"
                                                        disabled={
                                                            splits.length <= 2
                                                        }
                                                        className="text-muted-foreground hover:text-foreground disabled:opacity-50"
                                                        onClick={() =>
                                                            removeSplit(
                                                                split.id,
                                                            )
                                                        }
                                                    >
                                                        <X className="size-4" />
                                                    </button>
                                                </div>
                                                <div className="grid grid-cols-2 gap-2">
                                                    <div className="grid gap-1">
                                                        <Label className="text-xs">
                                                            Amount
                                                        </Label>
                                                        <Input
                                                            type="number"
                                                            inputMode="decimal"
                                                            step="0.01"
                                                            min="0.01"
                                                            value={split.amount}
                                                            onChange={(event) =>
                                                                updateSplit(
                                                                    split.id,
                                                                    'amount',
                                                                    event.target
                                                                        .value,
                                                                )
                                                            }
                                                        />
                                                    </div>
                                                    <div className="grid gap-1">
                                                        <Label className="text-xs">
                                                            Category
                                                        </Label>
                                                        <SearchableSelect
                                                            options={
                                                                splitOptions
                                                            }
                                                            value={
                                                                split.category_id ||
                                                                null
                                                            }
                                                            onValueChange={(
                                                                value,
                                                            ) =>
                                                                updateSplit(
                                                                    split.id,
                                                                    'category_id',
                                                                    value ?? '',
                                                                )
                                                            }
                                                            placeholder="No category"
                                                            searchPlaceholder="Search categories..."
                                                            allOption="No category"
                                                        />
                                                    </div>
                                                </div>
                                                <div className="grid grid-cols-2 gap-2">
                                                    <div className="grid gap-1">
                                                        <Label className="text-xs">
                                                            Payee
                                                        </Label>
                                                        <SearchableSelect
                                                            options={payees.map(
                                                                (p) => ({
                                                                    value: String(
                                                                        p.id,
                                                                    ),
                                                                    label: p.name,
                                                                }),
                                                            )}
                                                            value={
                                                                split.payee_id ||
                                                                null
                                                            }
                                                            onValueChange={(
                                                                value,
                                                            ) =>
                                                                updateSplit(
                                                                    split.id,
                                                                    'payee_id',
                                                                    value ?? '',
                                                                )
                                                            }
                                                            placeholder="No payee"
                                                            searchPlaceholder="Search payees..."
                                                            allOption="No payee"
                                                        />
                                                    </div>
                                                    <div className="grid gap-1">
                                                        <Label className="text-xs">
                                                            Description
                                                        </Label>
                                                        <Input
                                                            value={
                                                                split.description
                                                            }
                                                            onChange={(event) =>
                                                                updateSplit(
                                                                    split.id,
                                                                    'description',
                                                                    event.target
                                                                        .value,
                                                                )
                                                            }
                                                            placeholder="Optional split detail"
                                                        />
                                                    </div>
                                                </div>
                                            </div>
                                        ))}
                                    </div>

                                    <div className="flex items-center justify-between rounded-lg bg-background px-3 py-2 text-sm">
                                        <span className="text-muted-foreground">
                                            Remaining to allocate
                                        </span>
                                        <span
                                            className={cn(
                                                'font-medium tabular-nums',
                                                splitTotal.toFixed(2) ===
                                                    form.amount
                                                    ? 'text-green-600'
                                                    : 'text-amber-600',
                                            )}
                                        >
                                            {(
                                                Number(form.amount || 0) -
                                                splitTotal
                                            ).toFixed(2)}
                                        </span>
                                    </div>
                                </div>
                            )}
                        </div>
                    )}

                    {/* Description */}
                    <div className="grid gap-2">
                        <Label htmlFor="edit-description">Description</Label>
                        <Input
                            id="edit-description"
                            name="description"
                            value={form.description}
                            onChange={handleInputChange}
                            placeholder="Coffee, salary, or transfer note"
                        />
                    </div>

                    {/* Notes */}
                    <div className="grid gap-2">
                        <Label htmlFor="edit-notes">Notes</Label>
                        <Input
                            id="edit-notes"
                            name="notes"
                            value={form.notes}
                            onChange={handleInputChange}
                            placeholder="Optional details"
                        />
                    </div>

                    {/* Attachments - matches add-transaction-modal */}
                    <div className="grid gap-2">
                        <Label>Attachments</Label>
                        <input
                            ref={attachmentInputRef}
                            type="file"
                            multiple
                            accept=".pdf,.jpg,.jpeg,.png,.gif,.webp"
                            className="hidden"
                            onChange={(e) => uploadFiles(e.target.files)}
                        />
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            className="w-fit gap-1.5"
                            onClick={() => {
                                if (attachmentInputRef.current) {
                                    attachmentInputRef.current.value = '';
                                }

                                attachmentInputRef.current?.click();
                            }}
                        >
                            <Paperclip className="size-3.5" />
                            Attach files
                        </Button>
                        <p className="text-xs text-muted-foreground">
                            Max 5 MB per file. PDF, JPG, PNG, GIF, WebP
                            accepted.
                        </p>

                        {attachmentError && (
                            <p className="text-sm text-destructive">
                                {attachmentError}
                            </p>
                        )}

                        {/* Existing attachments */}
                        {attachments.length > 0 && (
                            <div className="space-y-1.5">
                                {attachments.map((attachment) => (
                                    <div
                                        key={attachment.id}
                                        className="flex items-center justify-between rounded-lg border border-border px-3 py-1.5 text-sm"
                                    >
                                        <a
                                            href={attachment.url}
                                            target="_blank"
                                            rel="noreferrer"
                                            className="min-w-0 truncate hover:underline"
                                        >
                                            {attachment.filename}
                                        </a>
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="sm"
                                            className="ml-2 size-6 shrink-0 p-0"
                                            onClick={() =>
                                                deleteAttachment(attachment.id)
                                            }
                                        >
                                            <X className="size-3.5" />
                                        </Button>
                                    </div>
                                ))}
                            </div>
                        )}

                        {uploadingAttachments && (
                            <p className="text-sm text-muted-foreground">
                                Uploading...
                            </p>
                        )}
                    </div>

                    {/* Tags - matches add-transaction-modal */}
                    {tags.length > 0 && (
                        <div className="grid gap-2">
                            <Label>Tags</Label>
                            <div className="flex flex-wrap gap-2">
                                {tags.map((tag) => (
                                    <label
                                        key={tag.id}
                                        className="flex cursor-pointer items-center gap-1.5"
                                    >
                                        <Checkbox
                                            checked={form.tag_ids.includes(
                                                tag.id,
                                            )}
                                            onCheckedChange={(checked) =>
                                                setForm((prev) => ({
                                                    ...prev,
                                                    tag_ids:
                                                        checked === true
                                                            ? [
                                                                  ...prev.tag_ids,
                                                                  tag.id,
                                                              ]
                                                            : prev.tag_ids.filter(
                                                                  (id) =>
                                                                      id !==
                                                                      tag.id,
                                                              ),
                                                }))
                                            }
                                        />
                                        <TagPill tag={tag} />
                                    </label>
                                ))}
                            </div>
                        </div>
                    )}

                    {/* Footer - matches add-transaction-modal simple layout */}
                    <div className="flex items-center justify-between pt-2">
                        <Button
                            type="button"
                            variant="destructive"
                            size="sm"
                            onClick={() => setShowDeleteConfirm(true)}
                            disabled={processing || deleting}
                        >
                            Delete
                        </Button>
                        <div className="flex gap-2">
                            <Button
                                type="button"
                                variant="outline"
                                onClick={onClose}
                            >
                                Cancel
                            </Button>
                            <Button type="submit" disabled={processing}>
                                Save changes
                            </Button>
                        </div>
                    </div>
                </form>
            </DialogContent>

            {/* Delete confirmation nested dialog */}
            <Dialog
                open={showDeleteConfirm}
                onOpenChange={(open) => !open && setShowDeleteConfirm(false)}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Delete transaction</DialogTitle>
                        <DialogDescription>
                            Are you sure you want to delete this transaction?
                            This action cannot be undone.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button
                            variant="outline"
                            onClick={() => setShowDeleteConfirm(false)}
                        >
                            Cancel
                        </Button>
                        <Button
                            variant="destructive"
                            onClick={handleDelete}
                            disabled={deleting}
                        >
                            Delete
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </Dialog>
    );
}

// ─── TransactionListSkeleton ─────────────────────────────────────────────────

function TransactionListSkeleton() {
    return (
        <>
            {/* Desktop skeleton */}
            <div className="hidden sm:block">
                <div className="space-y-1">
                    {Array.from({ length: 8 }).map((_, i) => (
                        <div
                            key={i}
                            className="flex items-center gap-3 border-b border-border px-2 py-3"
                        >
                            <Skeleton className="size-4 rounded" />
                            <Skeleton className="h-4 w-20" />
                            <Skeleton className="h-4 w-28" />
                            <Skeleton className="h-4 w-40 flex-1" />
                            <Skeleton className="hidden h-4 w-24 md:block" />
                            <Skeleton className="hidden h-4 w-24 lg:block" />
                            <Skeleton className="h-4 w-20" />
                            <Skeleton className="size-4" />
                        </div>
                    ))}
                </div>
            </div>

            {/* Mobile skeleton */}
            <div className="space-y-3 sm:hidden">
                <div className="flex items-center gap-2 px-1">
                    <Skeleton className="size-4 rounded" />
                    <Skeleton className="h-3 w-16" />
                </div>

                {Array.from({ length: 2 }).map((_, groupIndex) => (
                    <div key={groupIndex} className="space-y-1.5">
                        <Skeleton className="h-3 w-20" />

                        <div className="overflow-hidden rounded-xl border border-border">
                            {Array.from({ length: groupIndex === 0 ? 3 : 2 }).map(
                                (_, rowIndex) => (
                                    <div
                                        key={`${groupIndex}-${rowIndex}`}
                                        className="border-t border-border px-3 py-3 first:border-t-0"
                                    >
                                        <div className="grid grid-cols-[auto_1fr_auto] gap-2">
                                            <Skeleton className="mt-1 size-4 rounded" />
                                            <div className="space-y-1.5">
                                                <Skeleton className="h-4 w-32" />
                                                <Skeleton className="h-3 w-40" />
                                                <Skeleton className="h-3 w-24" />
                                            </div>
                                            <div className="space-y-1.5">
                                                <Skeleton className="ml-auto h-4 w-18" />
                                                <div className="flex justify-end gap-1">
                                                    <Skeleton className="size-8 rounded" />
                                                    <Skeleton className="size-8 rounded" />
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                ),
                            )}
                        </div>
                    </div>
                ))}
            </div>
        </>
    );
}

// ─── Filter panel (shared between desktop inline and mobile sheet) ───────────

function FilterFields({
    localFilters,
    setLocalFilters,
    accounts,
    categories,
    payees,
    tags,
}: {
    localFilters: Filters;
    setLocalFilters: React.Dispatch<React.SetStateAction<Filters>>;
    accounts: Account[];
    categories: Category[];
    payees: Payee[];
    tags: Tag[];
}) {
    const flatCategories = categories.flatMap((parent) => [
        parent,
        ...(parent.children ?? []),
    ]);

    return (
        <div className="grid gap-2.5 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6">
            {/* Date range */}
            <div className="grid gap-1 sm:col-span-2">
                <Label className="text-xs">Date Range</Label>
                <DateRangePicker
                    from={localFilters.date_from}
                    to={localFilters.date_to}
                    onChange={(range) =>
                        setLocalFilters((prev) => ({
                            ...prev,
                            date_from: range.from,
                            date_to: range.to,
                        }))
                    }
                />
            </div>

            {/* Account */}
            <div className="grid gap-1">
                <Label className="text-xs">Account</Label>
                <SearchableSelect
                    multiple
                    options={accounts.map((a) => ({
                        value: String(a.id),
                        label: a.name,
                        color: a.color,
                    }))}
                    value={localFilters.account_ids}
                    onValueChange={(value) =>
                        setLocalFilters((prev) => ({
                            ...prev,
                            account_ids: value,
                        }))
                    }
                    placeholder="All accounts"
                    searchPlaceholder="Search accounts..."
                    emptyMessage="No accounts found."
                />
            </div>

            {/* Category */}
            <div className="grid gap-1">
                <Label className="text-xs">Category</Label>
                <SearchableSelect
                    multiple
                    options={flatCategories.map((c) => ({
                        value: String(c.id),
                        label: c.name,
                        color: c.color,
                        group: c.parent_id
                            ? categories.find((p) => p.id === c.parent_id)?.name
                            : undefined,
                    }))}
                    value={localFilters.category_ids}
                    onValueChange={(value) =>
                        setLocalFilters((prev) => ({
                            ...prev,
                            category_ids: value,
                        }))
                    }
                    placeholder="All categories"
                    searchPlaceholder="Search categories..."
                    emptyMessage="No categories found."
                />
            </div>

            {/* Type */}
            <div className="grid gap-1">
                <Label className="text-xs">Type</Label>
                <SearchableSelect
                    multiple
                    options={[
                        { value: 'expense', label: 'Expense' },
                        { value: 'income', label: 'Income' },
                        { value: 'transfer', label: 'Transfer' },
                    ]}
                    value={localFilters.transaction_types}
                    onValueChange={(value) =>
                        setLocalFilters((prev) => ({
                            ...prev,
                            transaction_types: value,
                        }))
                    }
                    placeholder="All types"
                    searchPlaceholder="Search types..."
                    emptyMessage="No types found."
                />
            </div>

            {/* Payee */}
            <div className="grid gap-1">
                <Label className="text-xs">Payee</Label>
                <SearchableSelect
                    multiple
                    options={payees.map((p) => ({
                        value: String(p.id),
                        label: p.name,
                    }))}
                    value={localFilters.payee_ids}
                    onValueChange={(value) =>
                        setLocalFilters((prev) => ({
                            ...prev,
                            payee_ids: value,
                        }))
                    }
                    placeholder="All payees"
                    searchPlaceholder="Search payees..."
                    emptyMessage="No payees found."
                />
            </div>

            {/* Tags */}
            {tags.length > 0 && (
                <div className="grid gap-1">
                    <Label className="text-xs">Tag</Label>
                    <SearchableSelect
                        multiple
                        options={tags.map((t) => ({
                            value: String(t.id),
                            label: t.name,
                        }))}
                        value={localFilters.tag_ids}
                        onValueChange={(value) =>
                            setLocalFilters((prev) => ({
                                ...prev,
                                tag_ids: value,
                            }))
                        }
                        placeholder="All tags"
                        searchPlaceholder="Search tags..."
                        emptyMessage="No tags found."
                    />
                </div>
            )}
        </div>
    );
}

// ─── Main Page Component ─────────────────────────────────────────────────────

export default function TransactionsIndex() {
    const { currentLedger } = usePage().props;
    const ledger = currentLedger as Ledger;
    const { privacyMode } = usePrivacyMode();
    const {
        filters: committedFilters,
        accounts,
        categories,
        payees,
        tags,
        transactions,
    } = usePage<TransactionPageProps>().props;

    // Local draft filter state (initialized from committed)
    const [localFilters, setLocalFilters] = useState<Filters>(committedFilters);

    // Selection state
    const [selectedIds, setSelectedIds] = useState<number[]>([]);
    const [allAcrossPages, setAllAcrossPages] = useState(false);
    const [loadingSelectAll, setLoadingSelectAll] = useState(false);
    const [excludedIds, setExcludedIds] = useState<number[]>([]);

    // Modal state
    const [editTransaction, setEditTransaction] = useState<Transaction | null>(
        null,
    );
    const [deleteConfirmTransaction, setDeleteConfirmTransaction] =
        useState<Transaction | null>(null);
    const [showBulkDeleteConfirm, setShowBulkDeleteConfirm] = useState(false);
    const [bulkAction, setBulkAction] = useState<
        'change_category' | 'change_account' | 'change_payee' | null
    >(null);
    const [bulkActionValue, setBulkActionValue] = useState<string | null>(null);
    const [duplicateTransaction, setDuplicateTransaction] =
        useState<DuplicateData | null>(null);
    const [showDuplicateModal, setShowDuplicateModal] = useState(false);
    const [attachmentModalTransaction, setAttachmentModalTransaction] =
        useState<Transaction | null>(null);
    const {
        attachments: modalAttachments,
        loading: loadingAttachments,
        deleteAttachment,
    } = useAttachments(ledger.id, attachmentModalTransaction);

    // Filter panel open/closed
    const [filtersOpen, setFiltersOpen] = useState(false);
    const [isMobile, setIsMobile] = useState(() => {
        if (typeof window === 'undefined') {
            return false;
        }

        return !window.matchMedia('(min-width: 640px)').matches;
    });

    // Track mobile viewport
    useEffect(() => {
        if (typeof window === 'undefined') {
            return;
        }

        const mql = window.matchMedia('(min-width: 640px)');

        const handler = (e: MediaQueryListEvent) => setIsMobile(!e.matches);
        mql.addEventListener('change', handler);

        return () => mql.removeEventListener('change', handler);
    }, []);

    // Check if filters have changed from committed
    const filtersChanged =
        JSON.stringify(localFilters) !== JSON.stringify(committedFilters);

    // Flat categories for filter dropdowns and bulk actions
    const flatCategories = useMemo(
        () =>
            categories.flatMap((parent) => [
                parent,
                ...(parent.children ?? []),
            ]),
        [categories],
    );

    const breadcrumbs: BreadcrumbItem[] = [
        { title: ledger.name, href: ledgerDashboard.url(ledger.id) },
        { title: 'Transactions', href: transactionsIndex.url(ledger.id) },
    ];

    // ─── Filter actions ──────────────────────────────────────────────────

    function applyFilters() {
        applyFiltersWith(localFilters);
    }

    function applyFiltersWith(filters: Filters) {
        const params = buildQueryParams(filters);

        router.get(transactionsIndex.url(ledger.id), params, {
            only: ['transactions', 'filters'],
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
        setSelectedIds([]);
        setAllAcrossPages(false);
        setExcludedIds([]);
    }

    function handleResetFilters() {
        setLocalFilters({ ...EMPTY_FILTERS });

        router.get(
            transactionsIndex.url(ledger.id),
            {},
            {
                only: ['transactions', 'filters'],
                preserveState: true,
                preserveScroll: true,
                replace: true,
            },
        );
        setSelectedIds([]);
        setAllAcrossPages(false);
        setExcludedIds([]);
    }

    const handleSearchChange = useCallback((value: string | null) => {
        setLocalFilters((prev) => ({ ...prev, search: value }));
    }, []);

    function handlePageChange(newPage: number) {
        const params: Record<string, string | string[]> =
            buildQueryParams(committedFilters);
        params.page = String(newPage);

        router.get(transactionsIndex.url(ledger.id), params, {
            only: ['transactions'],
            preserveState: true,
            replace: true,
        });
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    // ─── Selection ───────────────────────────────────────────────────────

    const allVisibleIds = useMemo(
        () => (transactions?.data ?? []).map((t) => t.id),
        [transactions],
    );

    const { allSelected, someSelected } = deriveSelectionState({
        allVisibleIds,
        selectedIds,
        excludedIds,
        allAcrossPages,
    });
    const selectedCount = allAcrossPages
        ? Math.max((transactions?.total ?? 0) - excludedIds.length, 0)
        : selectedIds.length;

    function handleSelectAll(checked: boolean | 'indeterminate') {
        if (checked === true) {
            if (allAcrossPages) {
                setExcludedIds((prev) =>
                    prev.filter((id) => !allVisibleIds.includes(id)),
                );
            } else {
                setSelectedIds((prev) => [
                    ...new Set([...prev, ...allVisibleIds]),
                ]);
            }
        } else {
            if (allAcrossPages) {
                setExcludedIds((prev) => [
                    ...new Set([...prev, ...allVisibleIds]),
                ]);
            } else {
                setSelectedIds((prev) =>
                    prev.filter((id) => !allVisibleIds.includes(id)),
                );
            }
        }
    }

    function handleSelectOne(id: number, checked: boolean | 'indeterminate') {
        if (allAcrossPages) {
            if (checked === true) {
                setExcludedIds((prev) => prev.filter((item) => item !== id));
            } else {
                setExcludedIds((prev) => [...new Set([...prev, id])]);
            }
        } else {
            if (checked === true) {
                setSelectedIds((prev) => [...prev, id]);
            } else {
                setSelectedIds((prev) => prev.filter((i) => i !== id));
                setAllAcrossPages(false);
            }
        }
    }

    function handleSelectAllAcrossPages() {
        setLoadingSelectAll(true);

        setAllAcrossPages(true);
        setSelectedIds([]);
        setExcludedIds([]);
        setLoadingSelectAll(false);
    }

    function clearSelection() {
        setSelectedIds([]);
        setAllAcrossPages(false);
        setExcludedIds([]);
    }

    // ─── CRUD ────────────────────────────────────────────────────────────

    function handleBulkDelete() {
        router.post(
            bulkDestroyRoute.url(ledger.id),
            allAcrossPages
                ? {
                      apply_to_all_matching: true,
                      excluded_ids: excludedIds,
                      filters: committedFilters,
                  }
                : {
                      ids: selectedIds,
                  },
            {
                preserveScroll: true,
                onSuccess: () => {
                    clearSelection();
                    setShowBulkDeleteConfirm(false);
                    toast.success('Transactions deleted');
                },
                onError: () => {
                    toast.error('Failed to delete transactions');
                },
            },
        );
    }

    function handleBulkUpdate() {
        if (!bulkAction || !bulkActionValue) {
            return;
        }

        router.post(
            bulkUpdateRoute.url(ledger.id),
            allAcrossPages
                ? {
                      apply_to_all_matching: true,
                      excluded_ids: excludedIds,
                      filters: committedFilters,
                      action: bulkAction,
                      value: Number(bulkActionValue),
                  }
                : {
                      ids: selectedIds,
                      action: bulkAction,
                      value: Number(bulkActionValue),
                  },
            {
                preserveScroll: true,
                onSuccess: () => {
                    const actionLabel = {
                        change_category: 'Category',
                        change_account: 'Account',
                        change_payee: 'Payee',
                    }[bulkAction];

                    clearSelection();
                    setBulkAction(null);
                    setBulkActionValue(null);
                    toast.success(
                        `${actionLabel} updated for selected transactions`,
                    );
                },
                onError: () => {
                    toast.error('Failed to update transactions');
                },
            },
        );
    }

    function openBulkActionModal(
        action: 'change_category' | 'change_account' | 'change_payee',
    ) {
        setBulkAction(action);
        setBulkActionValue(null);
    }

    function handleContextDelete() {
        const transaction = deleteConfirmTransaction;

        if (!transaction) {
            return;
        }

        setDeleteConfirmTransaction(null);

        router.delete(
            destroyRoute.url({
                ledger: ledger.id,
                transaction: transaction.id,
            }),
            {
                preserveScroll: true,
                onSuccess: () => {
                    toast.success('Transaction deleted');
                },
                onError: () => {
                    toast.error('Failed to delete transaction');
                },
            },
        );
    }

    function handleDuplicate(transaction: Transaction) {
        setDuplicateTransaction({
            transaction_type: transaction.transaction_type,
            account_id: transaction.account_id,
            to_account_id: transaction.transfer_pair
                ? transaction.transfer_pair.account_id
                : null,
            category_id: transaction.category_id,
            payee_id: transaction.payee_id,
            amount: transaction.amount,
            description: transaction.description,
            notes: transaction.notes,
            tag_ids: (transaction.tags ?? []).map((t) => t.id),
        });
        setShowDuplicateModal(true);
    }

    // ─── Running balance ─────────────────────────────────────────────────

    const isAccountFiltered = committedFilters.account_ids.length === 1;
    const filteredAccount = isAccountFiltered
        ? accounts.find((a) => String(a.id) === committedFilters.account_ids[0])
        : null;

    const runningBalances = useMemo(() => {
        if (!filteredAccount || !transactions) {
            return null;
        }

        const txns = transactions.data;

        if (txns.length === 0 || transactions.current_page !== 1) {
            return null;
        }

        const balances = new Map<number, number>();
        const currentBalance = parseFloat(filteredAccount.current_balance);
        let cumulativeBefore = 0;

        for (const txn of txns) {
            balances.set(txn.id, currentBalance - cumulativeBefore);
            cumulativeBefore += parseFloat(txn.amount);
        }

        return balances;
    }, [filteredAccount, transactions]);

    // ─── Filter chips (from committed filters) ──────────────────────────

    const filterChips: FilterChip[] = useMemo(() => {
        const chips: FilterChip[] = [];

        if (committedFilters.date_from || committedFilters.date_to) {
            const from = committedFilters.date_from
                ? formatDate(committedFilters.date_from)
                : 'Start';
            const to = committedFilters.date_to
                ? formatDate(committedFilters.date_to)
                : 'Now';
            chips.push({
                key: 'date_range',
                label: `${from} - ${to}`,
                onRemove: (filters) => ({
                    ...filters,
                    date_from: '',
                    date_to: '',
                }),
            });
        }

        if (committedFilters.search) {
            chips.push({
                key: 'search',
                label: `"${committedFilters.search}"`,
                onRemove: (filters) => ({
                    ...filters,
                    search: null,
                }),
            });
        }

        for (const id of committedFilters.account_ids) {
            const account = accounts.find((a) => String(a.id) === id);

            if (account) {
                chips.push({
                    key: `account_${id}`,
                    label: account.name,
                    onRemove: (filters) => ({
                        ...filters,
                        account_ids: filters.account_ids.filter(
                            (aid) => aid !== id,
                        ),
                    }),
                });
            }
        }

        const allCats = categories.flatMap((p) => [p, ...(p.children ?? [])]);

        for (const id of committedFilters.category_ids) {
            const cat = allCats.find((c) => String(c.id) === id);

            if (cat) {
                chips.push({
                    key: `category_${id}`,
                    label: cat.name,
                    onRemove: (filters) => ({
                        ...filters,
                        category_ids: filters.category_ids.filter(
                            (cid) => cid !== id,
                        ),
                    }),
                });
            }
        }

        for (const type of committedFilters.transaction_types) {
            chips.push({
                key: `type_${type}`,
                label: type.charAt(0).toUpperCase() + type.slice(1),
                onRemove: (filters) => ({
                    ...filters,
                    transaction_types: filters.transaction_types.filter(
                        (t) => t !== type,
                    ),
                }),
            });
        }

        for (const id of committedFilters.payee_ids) {
            const payee = payees.find((p) => String(p.id) === id);

            if (payee) {
                chips.push({
                    key: `payee_${id}`,
                    label: payee.name,
                    onRemove: (filters) => ({
                        ...filters,
                        payee_ids: filters.payee_ids.filter(
                            (pid) => pid !== id,
                        ),
                    }),
                });
            }
        }

        for (const id of committedFilters.tag_ids) {
            const tag = tags.find((t) => String(t.id) === id);

            if (tag) {
                chips.push({
                    key: `tag_${id}`,
                    label: tag.name,
                    onRemove: (filters) => ({
                        ...filters,
                        tag_ids: filters.tag_ids.filter((tid) => tid !== id),
                    }),
                });
            }
        }

        if (committedFilters.uncategorized === '1') {
            chips.push({
                key: 'uncategorized',
                label: 'Uncategorized',
                onRemove: (filters) => ({
                    ...filters,
                    uncategorized: null,
                }),
            });
        }

        if (committedFilters.bill_id) {
            chips.push({
                key: 'bill',
                label: 'Recurring',
                onRemove: (filters) => ({
                    ...filters,
                    bill_id: null,
                }),
            });
        }

        return chips;
    }, [committedFilters, accounts, categories, payees, tags]);

    const activeFilterCount = filterChips.length;

    // ─── Render transaction content ──────────────────────────────────────

    function renderTransactionList(txs: Pagination<Transaction>) {
        if (txs.data.length === 0) {
            return (
                <EmptyState
                    icon={<Receipt className="size-6" />}
                    title="No transactions yet"
                    description="Start tracking your spending by adding your first transaction."
                />
            );
        }

        return (
            <>
                {/* Showing X-Y of Z */}
                {txs.total > 0 && (
                    <div className="mb-3 text-xs text-muted-foreground">
                        Showing {txs.from}-{txs.to} of {txs.total}
                    </div>
                )}

                {/* Select all across pages banner */}
                {allSelected &&
                    !allAcrossPages &&
                    txs.total > txs.data.length && (
                        <div className="mb-3 rounded-lg border border-primary/20 bg-primary/5 px-4 py-2 text-center text-sm">
                            All {txs.data.length} transactions on this page are
                            selected.{' '}
                            <button
                                type="button"
                                className="font-medium text-primary hover:underline"
                                disabled={loadingSelectAll}
                                onClick={handleSelectAllAcrossPages}
                            >
                                {loadingSelectAll
                                    ? 'Loading...'
                                    : `Select all ${txs.total} matching transactions`}
                            </button>
                        </div>
                    )}

                {/* Desktop table */}
                <Table className="hidden sm:table">
                    <TableHeader>
                        <TableRow>
                            <TableHead className="w-8">
                                <Checkbox
                                    checked={
                                        allSelected
                                            ? true
                                            : someSelected
                                              ? 'indeterminate'
                                              : false
                                    }
                                    onCheckedChange={handleSelectAll}
                                    aria-label="Select all"
                                />
                            </TableHead>
                            <TableHead>Date</TableHead>
                            <TableHead className="hidden md:table-cell">
                                Account
                            </TableHead>
                            <TableHead className="hidden lg:table-cell">
                                Category
                            </TableHead>
                            <TableHead>Payee</TableHead>
                            <TableHead>Description</TableHead>
                            <TableHead className="text-right">Amount</TableHead>
                            <TableHead className="w-16 text-center">
                                Files
                            </TableHead>
                            <TableHead className="w-8"></TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {txs.data.map((tx) => {
                            const amount = parseFloat(tx.amount);

                            return (
                                <TableRow
                                    key={tx.id}
                                    className="cursor-pointer"
                                    onClick={() => setEditTransaction(tx)}
                                >
                                    <TableCell
                                        onClick={(e) => e.stopPropagation()}
                                    >
                                        <Checkbox
                                            checked={
                                                allAcrossPages
                                                    ? !excludedIds.includes(
                                                          tx.id,
                                                      )
                                                    : selectedIds.includes(
                                                          tx.id,
                                                      )
                                            }
                                            onCheckedChange={(c) =>
                                                handleSelectOne(tx.id, c)
                                            }
                                        />
                                    </TableCell>
                                    <TableCell className="whitespace-nowrap">
                                        {formatDate(tx.transaction_date)}
                                    </TableCell>
                                    <TableCell className="hidden md:table-cell">
                                        {tx.account?.name}
                                    </TableCell>
                                    <TableCell className="hidden lg:table-cell">
                                        {tx.category?.name}
                                    </TableCell>
                                    <TableCell>{tx.payee?.name}</TableCell>
                                    <TableCell>{tx.description}</TableCell>
                                    <TableCell
                                        className={`text-right font-medium tabular-nums ${amountColor(amount)}`}
                                    >
                                        {formatAbsAmount(amount, privacyMode)}
                                    </TableCell>
                                    <TableCell
                                        className="text-center"
                                        onClick={(e) => e.stopPropagation()}
                                    >
                                        {(tx.attachments_count ?? 0) > 0 && (
                                            <button
                                                type="button"
                                                onClick={() =>
                                                    setAttachmentModalTransaction(
                                                        tx,
                                                    )
                                                }
                                                className="inline-flex items-center gap-1 rounded-md bg-muted px-2 py-0.5 text-xs font-medium text-muted-foreground transition-colors hover:bg-muted/80 hover:text-foreground"
                                            >
                                                <Paperclip className="size-3" />
                                                {tx.attachments_count}
                                            </button>
                                        )}
                                    </TableCell>
                                    <TableCell
                                        onClick={(e) => e.stopPropagation()}
                                    >
                                        <DropdownMenu>
                                            <DropdownMenuTrigger asChild>
                                                <button
                                                    type="button"
                                                    className="flex size-7 items-center justify-center rounded text-muted-foreground hover:bg-muted"
                                                >
                                                    <MoreVertical className="size-4" />
                                                </button>
                                            </DropdownMenuTrigger>
                                            <DropdownMenuContent align="end">
                                                <DropdownMenuItem
                                                    onClick={() =>
                                                        setEditTransaction(tx)
                                                    }
                                                >
                                                    Edit
                                                </DropdownMenuItem>
                                                <DropdownMenuItem
                                                    onClick={() =>
                                                        handleDuplicate(tx)
                                                    }
                                                >
                                                    Duplicate
                                                </DropdownMenuItem>
                                                <DropdownMenuSeparator />
                                                <DropdownMenuItem
                                                    className="text-destructive focus:text-destructive"
                                                    onClick={() =>
                                                        setDeleteConfirmTransaction(
                                                            tx,
                                                        )
                                                    }
                                                >
                                                    Delete
                                                </DropdownMenuItem>
                                            </DropdownMenuContent>
                                        </DropdownMenu>
                                    </TableCell>
                                </TableRow>
                            );
                        })}
                    </TableBody>
                </Table>

                <MobileTransactionList
                    transactions={txs.data}
                    allSelected={allSelected}
                    someSelected={someSelected}
                    allAcrossPages={allAcrossPages}
                    selectedIds={selectedIds}
                    excludedIds={excludedIds}
                    runningBalances={runningBalances}
                    onSelectAll={handleSelectAll}
                    onSelectOne={handleSelectOne}
                    onEdit={setEditTransaction}
                    onDuplicate={handleDuplicate}
                    onDelete={setDeleteConfirmTransaction}
                    onAttachmentClick={setAttachmentModalTransaction}
                />

                {/* Pagination */}
                {txs.last_page > 1 && (
                    <div className="mt-4 flex items-center justify-between">
                        <span className="text-xs text-muted-foreground">
                            Page {txs.current_page} of {txs.last_page}
                        </span>

                        {/* Mobile: Previous/Next only */}
                        <div className="flex gap-1 sm:hidden">
                            <Button
                                variant="outline"
                                size="sm"
                                disabled={!txs.prev_page_url}
                                onClick={() =>
                                    handlePageChange(txs.current_page - 1)
                                }
                            >
                                Previous
                            </Button>
                            <Button
                                variant="outline"
                                size="sm"
                                disabled={!txs.next_page_url}
                                onClick={() =>
                                    handlePageChange(txs.current_page + 1)
                                }
                            >
                                Next
                            </Button>
                        </div>

                        {/* Desktop: Full page links */}
                        <div className="hidden gap-1 sm:flex">
                            {txs.links.map((link, i) => {
                                if (!link.url) {
                                    return (
                                        <Button
                                            key={i}
                                            variant="outline"
                                            size="sm"
                                            disabled
                                            className="h-7 px-2.5 text-xs"
                                            dangerouslySetInnerHTML={{
                                                __html: link.label,
                                            }}
                                        />
                                    );
                                }

                                const linkUrl = new URL(
                                    link.url,
                                    window.location.origin,
                                );
                                const linkPage = parseInt(
                                    linkUrl.searchParams.get('page') ?? '1',
                                    10,
                                );

                                return (
                                    <Button
                                        key={i}
                                        variant={
                                            link.active ? 'default' : 'outline'
                                        }
                                        size="sm"
                                        className="h-7 px-2.5 text-xs"
                                        onClick={() =>
                                            handlePageChange(linkPage)
                                        }
                                        dangerouslySetInnerHTML={{
                                            __html: link.label,
                                        }}
                                    />
                                );
                            })}
                        </div>
                    </div>
                )}
            </>
        );
    }

    // ─── JSX ─────────────────────────────────────────────────────────────

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`${ledger.name} transactions`} />

            <div className="flex h-full flex-1 flex-col gap-5 p-4 md:p-6 lg:p-8">
                {/* Header */}
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <Heading
                        title="Transactions"
                        description={`Review all activity in ${ledger.name}.`}
                    />
                    <div className="flex w-full flex-col gap-2 sm:w-auto sm:flex-row sm:items-center">
                        <Button
                            variant="outline"
                            size="default"
                            className="flex-1 sm:flex-initial"
                            asChild
                        >
                            <a
                                href={buildExportUrl(
                                    ledger.id,
                                    committedFilters,
                                )}
                                download
                            >
                                Export CSV
                            </a>
                        </Button>
                    </div>
                </div>

                {/* Filters bar */}
                <Card>
                    <CardContent className="px-4 py-2.5">
                        <div className="flex flex-col gap-1.5">
                            {/* Top row: search + filter toggle */}
                            <div className="flex items-center gap-2">
                                <div className="relative flex-1">
                                    <Search className="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground" />
                                    <Input
                                        placeholder="Search transactions..."
                                        value={localFilters.search ?? ''}
                                        onChange={(e) =>
                                            handleSearchChange(
                                                e.target.value || null,
                                            )
                                        }
                                        className="pl-9"
                                    />
                                </div>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    className="shrink-0 gap-1.5"
                                    onClick={() => setFiltersOpen(!filtersOpen)}
                                >
                                    <SlidersHorizontal className="size-4" />
                                    <span className="hidden sm:inline">
                                        Filters
                                    </span>
                                    {activeFilterCount > 0 && (
                                        <Badge
                                            variant="secondary"
                                            className="ml-0.5 size-5 rounded-full p-0 text-[10px]"
                                        >
                                            {activeFilterCount}
                                        </Badge>
                                    )}
                                </Button>
                            </div>

                            {/* Filter chips row (from committed state) */}
                            {filterChips.length > 0 && (
                                <div className="flex items-center gap-1.5">
                                    <div className="flex min-w-0 flex-1 items-center gap-1 overflow-x-auto py-0.5">
                                        {filterChips.map((chip) => (
                                            <Badge
                                                key={chip.key}
                                                variant="secondary"
                                                className="shrink-0 gap-1 py-0 pr-1 text-[11px] font-normal"
                                            >
                                                <span className="max-w-[120px] truncate">
                                                    {chip.label}
                                                </span>
                                                <button
                                                    type="button"
                                                    onClick={() => {
                                                        const nextFilters =
                                                            chip.onRemove(
                                                                localFilters,
                                                            );
                                                        setLocalFilters(
                                                            nextFilters,
                                                        );
                                                        applyFiltersWith(
                                                            nextFilters,
                                                        );
                                                    }}
                                                    className="ml-0.5 rounded-sm p-0.5 hover:bg-muted-foreground/20"
                                                >
                                                    <X className="size-3" />
                                                </button>
                                            </Badge>
                                        ))}
                                    </div>
                                    <button
                                        type="button"
                                        className="shrink-0 text-xs text-muted-foreground hover:text-foreground"
                                        onClick={handleResetFilters}
                                    >
                                        Clear all
                                    </button>
                                </div>
                            )}

                            {/* Unsaved changes indicator */}
                            {filtersChanged && (
                                <div className="flex items-center gap-1.5">
                                    <span className="text-xs text-amber-600 dark:text-amber-400">
                                        Filters changed
                                    </span>
                                    <Button
                                        size="sm"
                                        variant="default"
                                        className="h-6 px-2 text-xs"
                                        onClick={applyFilters}
                                    >
                                        Apply
                                    </Button>
                                </div>
                            )}

                            {/* Desktop filter panel (inline) */}
                            <div
                                className={`flex-col gap-2.5 ${filtersOpen && !isMobile ? 'flex' : 'hidden'}`}
                            >
                                <FilterFields
                                    localFilters={localFilters}
                                    setLocalFilters={setLocalFilters}
                                    accounts={accounts}
                                    categories={categories}
                                    payees={payees}
                                    tags={tags}
                                />
                                <div className="flex items-center gap-1.5">
                                    <Button size="sm" onClick={applyFilters}>
                                        Apply filters
                                    </Button>
                                    <Button
                                        size="sm"
                                        variant="outline"
                                        onClick={handleResetFilters}
                                    >
                                        Reset
                                    </Button>
                                    {activeFilterCount > 0 && (
                                        <span className="text-xs text-muted-foreground">
                                            {activeFilterCount} filter
                                            {activeFilterCount !== 1
                                                ? 's'
                                                : ''}{' '}
                                            active
                                        </span>
                                    )}
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {/* Mobile filter panel (bottom sheet) */}
                <Sheet
                    open={filtersOpen && isMobile}
                    onOpenChange={(open) => {
                        if (!open) {
                            setFiltersOpen(false);
                        }
                    }}
                >
                    <SheetContent
                        side="bottom"
                        className="max-h-[85vh] overflow-y-auto"
                    >
                        <SheetHeader>
                            <SheetTitle>Filters</SheetTitle>
                            <SheetDescription>
                                Narrow down your transactions
                            </SheetDescription>
                        </SheetHeader>
                        <div className="flex flex-col gap-2.5 px-4">
                            <FilterFields
                                localFilters={localFilters}
                                setLocalFilters={setLocalFilters}
                                accounts={accounts}
                                categories={categories}
                                payees={payees}
                                tags={tags}
                            />
                        </div>
                        <SheetFooter>
                            <div className="flex items-center gap-1.5">
                                <Button
                                    size="sm"
                                    onClick={() => {
                                        applyFilters();
                                        setFiltersOpen(false);
                                    }}
                                >
                                    Apply filters
                                </Button>
                                <Button
                                    size="sm"
                                    variant="outline"
                                    onClick={() => {
                                        handleResetFilters();
                                        setFiltersOpen(false);
                                    }}
                                >
                                    Reset
                                </Button>
                            </div>
                        </SheetFooter>
                    </SheetContent>
                </Sheet>

                {/* Bulk actions bar */}
                {selectedCount > 0 && (
                    <div className="flex flex-wrap items-center gap-3 rounded-lg border border-primary/30 bg-primary/5 px-4 py-2">
                        <span className="text-sm font-medium text-muted-foreground">
                            {selectedCount} selected
                            {allAcrossPages && ' (all pages)'}
                        </span>
                        {!allAcrossPages &&
                            transactions &&
                            transactions.total > transactions.data.length && (
                                <Button
                                    size="sm"
                                    variant="link"
                                    className="h-auto p-0 text-xs"
                                    disabled={loadingSelectAll}
                                    onClick={handleSelectAllAcrossPages}
                                >
                                    {loadingSelectAll
                                        ? 'Loading...'
                                        : `Select all ${transactions.total} transactions`}
                                </Button>
                            )}
                        <div className="flex flex-wrap items-center gap-2">
                            <Button
                                size="sm"
                                variant="outline"
                                onClick={() =>
                                    openBulkActionModal('change_category')
                                }
                            >
                                Change category
                            </Button>
                            <Button
                                size="sm"
                                variant="outline"
                                onClick={() =>
                                    openBulkActionModal('change_account')
                                }
                            >
                                Change account
                            </Button>
                            <Button
                                size="sm"
                                variant="outline"
                                onClick={() =>
                                    openBulkActionModal('change_payee')
                                }
                            >
                                Change payee
                            </Button>
                            <Button
                                size="sm"
                                variant="destructive"
                                onClick={() => setShowBulkDeleteConfirm(true)}
                            >
                                Delete selected
                            </Button>
                        </div>
                        <Button
                            size="sm"
                            variant="ghost"
                            onClick={clearSelection}
                        >
                            Clear selection
                        </Button>
                    </div>
                )}

                {/* Transaction list */}
                <div>
                    <Deferred
                        data="transactions"
                        fallback={<TransactionListSkeleton />}
                    >
                        {transactions && renderTransactionList(transactions)}
                    </Deferred>
                </div>
            </div>

            {/* Edit modal */}
            {editTransaction && (
                <EditTransactionModal
                    ledger={ledger}
                    transaction={editTransaction}
                    accounts={accounts}
                    categories={categories}
                    payees={payees}
                    tags={tags}
                    onClose={() => setEditTransaction(null)}
                />
            )}

            {/* Single delete confirmation */}
            <Dialog
                open={deleteConfirmTransaction !== null}
                modal
                onOpenChange={(open) =>
                    !open && setDeleteConfirmTransaction(null)
                }
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Delete this transaction?</DialogTitle>
                        <DialogDescription>
                            This transaction will be permanently deleted. This
                            action cannot be undone.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button
                            variant="outline"
                            onClick={() => setDeleteConfirmTransaction(null)}
                        >
                            Cancel
                        </Button>
                        <Button
                            variant="destructive"
                            onClick={handleContextDelete}
                        >
                            Delete
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            {/* Duplicate transaction modal */}
            <AddTransactionModal
                ledger={ledger}
                externalOpen={showDuplicateModal}
                onExternalOpenChange={(open) => {
                    setShowDuplicateModal(open);

                    if (!open) {
                        setDuplicateTransaction(null);
                    }
                }}
                initialData={duplicateTransaction}
                modal
            />

            {/* Attachment viewer modal */}
            <Dialog
                open={attachmentModalTransaction !== null}
                modal
                onOpenChange={(open) =>
                    !open && setAttachmentModalTransaction(null)
                }
            >
                <DialogContent className="max-w-lg">
                    <DialogHeader>
                        <DialogTitle>Attachments</DialogTitle>
                        <DialogDescription>
                            {attachmentModalTransaction?.description ||
                                'Transaction'}{' '}
                            ·{' '}
                            {formatAbsAmount(
                                attachmentModalTransaction?.amount || '0',
                                privacyMode,
                            )}
                        </DialogDescription>
                    </DialogHeader>
                    <div className="space-y-3 py-4">
                        {loadingAttachments ? (
                            <div className="flex items-center justify-center py-8">
                                <div className="size-6 animate-spin rounded-full border-2 border-primary border-t-transparent" />
                            </div>
                        ) : modalAttachments.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                No attachments for this transaction.
                            </p>
                        ) : (
                            modalAttachments.map((attachment) => (
                                <div
                                    key={attachment.id}
                                    className="flex items-center justify-between rounded-lg border border-border p-3"
                                >
                                    <div className="min-w-0">
                                        <a
                                            href={attachment.url}
                                            target="_blank"
                                            rel="noreferrer"
                                            className="block truncate text-sm font-medium hover:underline"
                                        >
                                            {attachment.filename}
                                        </a>
                                        <p className="text-xs text-muted-foreground">
                                            {(attachment.size / 1024).toFixed(
                                                0,
                                            )}{' '}
                                            KB
                                        </p>
                                    </div>
                                    <div className="flex items-center gap-1">
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            asChild
                                        >
                                            <a
                                                href={attachment.url}
                                                target="_blank"
                                                rel="noreferrer"
                                            >
                                                View
                                            </a>
                                        </Button>
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            className="text-destructive hover:text-destructive"
                                            onClick={() =>
                                                deleteAttachment(attachment.id)
                                            }
                                        >
                                            Delete
                                        </Button>
                                    </div>
                                </div>
                            ))
                        )}
                    </div>
                    <DialogFooter>
                        <Button
                            variant="outline"
                            onClick={() => setAttachmentModalTransaction(null)}
                        >
                            Close
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            {/* Bulk delete confirmation */}
            <Dialog
                open={showBulkDeleteConfirm}
                modal
                onOpenChange={(open) =>
                    !open && setShowBulkDeleteConfirm(false)
                }
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Delete transactions</DialogTitle>
                        <DialogDescription>
                            Delete <strong>{selectedCount}</strong> transaction
                            {selectedCount !== 1 ? 's' : ''}? This cannot be
                            undone.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button
                            variant="outline"
                            onClick={() => setShowBulkDeleteConfirm(false)}
                        >
                            Cancel
                        </Button>
                        <Button
                            variant="destructive"
                            onClick={handleBulkDelete}
                        >
                            Delete
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            {/* Bulk action modal (change category/account/payee) */}
            <Dialog
                open={bulkAction !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setBulkAction(null);
                        setBulkActionValue(null);
                    }
                }}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>
                            {bulkAction === 'change_category' &&
                                'Change category'}
                            {bulkAction === 'change_account' &&
                                'Change account'}
                            {bulkAction === 'change_payee' && 'Change payee'}
                        </DialogTitle>
                        <DialogDescription>
                            Update <strong>{selectedCount}</strong> transaction
                            {selectedCount !== 1 ? 's' : ''}. Transfer
                            transactions will be skipped.
                        </DialogDescription>
                    </DialogHeader>
                    <div className="py-4">
                        {bulkAction === 'change_category' && (
                            <SearchableSelect
                                options={flatCategories.map((c) => ({
                                    value: String(c.id),
                                    label: c.parent_id
                                        ? `${categories.find((p) => p.id === c.parent_id)?.name} > ${c.name}`
                                        : c.name,
                                }))}
                                value={bulkActionValue}
                                onValueChange={setBulkActionValue}
                                placeholder="Select category..."
                                searchPlaceholder="Search categories..."
                            />
                        )}
                        {bulkAction === 'change_account' && (
                            <SearchableSelect
                                options={accounts.map((a) => ({
                                    value: String(a.id),
                                    label: a.name,
                                }))}
                                value={bulkActionValue}
                                onValueChange={setBulkActionValue}
                                placeholder="Select account..."
                                searchPlaceholder="Search accounts..."
                            />
                        )}
                        {bulkAction === 'change_payee' && (
                            <SearchableSelect
                                options={payees.map((p) => ({
                                    value: String(p.id),
                                    label: p.name,
                                }))}
                                value={bulkActionValue}
                                onValueChange={setBulkActionValue}
                                placeholder="Select payee..."
                                searchPlaceholder="Search payees..."
                            />
                        )}
                    </div>
                    <DialogFooter>
                        <Button
                            variant="outline"
                            onClick={() => {
                                setBulkAction(null);
                                setBulkActionValue(null);
                            }}
                        >
                            Cancel
                        </Button>
                        <Button
                            disabled={!bulkActionValue}
                            onClick={handleBulkUpdate}
                        >
                            Update
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}
